<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Support\ConcurrentWrite;
use App\Support\MunicipalityAccess;
use App\Support\MunicipalityBoundaryGuard;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FarmPlotController extends Controller
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $concurrentWrite,
        private MunicipalityBoundaryGuard $boundaryGuard
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request, Farmer $farmer)
    {
        $this->authorize('view', $farmer);

        $plots = FarmPlot::where('farmer_id', $farmer->id)
                ->orderByDesc('id')
                ->get()
                ->each(fn (FarmPlot $plot) => $this->attachVersion($plot));

        return response()->json(['plots' => $plots]);
    }

    public function all(Request $request)
    {
        $this->authorize('viewAny', FarmPlot::class);
        $query = FarmPlot::query()->whereHas(
            'farmer',
            function (Builder $farmerQuery) use ($request) {
                $this->municipalityAccess->applyOptionalFilter(
                    $farmerQuery,
                    $request->user(),
                    $request->query('municipality_id'),
                    'farmers.municipality_id'
                );
            }
        );

        $plots = $query
                ->orderByDesc('id')
                ->get([
                    'id',
                    'farmer_id',
                    'name',
                    'color',
                    'polygon_json',
                    'area_ha',
                    'centroid_lat',
                    'centroid_lng',
                    'created_at',
                    'updated_at',
                ])
                ->each(fn (FarmPlot $plot) => $this->attachVersion($plot));

        return response()->json(['plots' => $plots]);
    }

    /**
     * Proxy a Google Static Maps image through the authenticated application.
     *
     * Loading the Google image directly in a browser canvas taints the canvas
     * because the Static Maps response is cross-origin. Returning it from this
     * same-origin endpoint keeps PNG exports usable and keeps the server-side
     * Static Maps key out of generated HTML and JavaScript.
     */
    public function staticMap(FarmPlot $plot)
    {
        $this->authorize('view', $plot);

        $apiKey = (string) config('services.google_maps.static_key', '');
        if ($apiKey === '') {
            return response('Google Maps Static API is not configured.', 503)
                ->header('Cache-Control', 'no-store');
        }

        $polygon = $this->normalizePolygon($plot->polygon_json ?: []);
        if (count($polygon) < 3) {
            return response('The plot has no valid polygon.', 422)
                ->header('Cache-Control', 'no-store');
        }

        $color = ltrim($this->normalizeHexColor($plot->color), '#');
        $points = $this->sampleStaticMapPolygon($polygon);
        $path = [
            'fillcolor:0x'.$color.'66',
            'color:0x'.$color.'FF',
            'weight:5',
        ];

        foreach ($points as $point) {
            $path[] = number_format($point['lat'], 6, '.', '')
                .','.number_format($point['lng'], 6, '.', '');
        }

        $first = $points[0];
        $path[] = number_format($first['lat'], 6, '.', '')
            .','.number_format($first['lng'], 6, '.', '');

        $cacheKey = 'farm-plot-static-map:v2:'
            .$plot->getKey().':'
            .sha1(json_encode([$points, $color]));

        $map = Cache::get($cacheKey);

        if (! is_array($map)) {
            try {
                $map = Cache::lock(
                    $cacheKey.':refresh-lock',
                    30
                )->block(5, function () use (
                    $cacheKey,
                    $apiKey,
                    $path
                ): ?array {
                    $cached = Cache::get($cacheKey);
                    if (is_array($cached)) {
                        return $cached;
                    }

                    $response = Http::withHeaders([
                        'Accept' => 'image/png,image/*;q=0.9',
                        'Referer' => rtrim((string) config('app.url'), '/').'/',
                        'User-Agent' => 'AgriMS-Tarlac/1.0',
                    ])->timeout(20)->get(
                        'https://maps.googleapis.com/maps/api/staticmap',
                        [
                            'size' => '640x360',
                            'scale' => 2,
                            'format' => 'png',
                            'maptype' => 'hybrid',
                            'path' => implode('|', $path),
                            'key' => $apiKey,
                        ]
                    );

                    $contentType = strtolower((string) $response->header(
                        'Content-Type'
                    ));

                    if (
                        ! $response->successful()
                        || ! str_starts_with($contentType, 'image/')
                        || $response->body() === ''
                    ) {
                        report(new \RuntimeException(
                            'Google Static Maps request failed with HTTP '
                            .$response->status().'.'
                        ));

                        return null;
                    }

                    $map = [
                        'body' => $response->body(),
                        'content_type' => $contentType,
                    ];
                    Cache::put($cacheKey, $map, now()->addHours(12));

                    return $map;
                });
            } catch (LockTimeoutException $exception) {
                $map = Cache::get($cacheKey);
            }
        }

        if (! is_array($map) || ! isset($map['body'])) {
            return response(
                'Google could not generate the satellite plot image.',
                502
            )->header('Cache-Control', 'no-store');
        }

        return response($map['body'], 200, [
            'Content-Type' => $map['content_type'] ?: 'image/png',
            'Cache-Control' => 'private, max-age=43200',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, Farmer $farmer)
    {
        $this->authorize('update', $farmer);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'color' => [
                'nullable',
                'string',
                'max:16',
                'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/',
            ],
            'polygon' => ['required', 'array', 'min:3'],
            'polygon.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'polygon.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $polygon = $this->normalizePolygon($data['polygon']);
        $geofence = $this->boundaryGuard->inspect(
            (int) $farmer->municipality_id,
            $polygon
        );

        [$centroidLat, $centroidLng] = $this->computeCentroid($polygon);
        $areaHa = $this->areaHectaresSpherical($polygon);

        $plot = $this->concurrentWrite->transaction(
            fn () => FarmPlot::create([
                'farmer_id' => $farmer->id,
                'name' => $data['name'] ?? null,
                'color' => $this->normalizeHexColor($data['color'] ?? '#22c55e'),
                'polygon_json' => $polygon,
                'area_ha' => $areaHa,
                'centroid_lat' => $centroidLat,
                'centroid_lng' => $centroidLng,
            ])
        );

        $this->attachVersion($plot);

        return response()->json([
            'plot' => $plot,
            'geofence' => [
                'status' => $geofence['status'],
                'message' => $geofence['message'],
            ],
        ], 201);
    }

    public function update(Request $request, FarmPlot $plot)
    {
        $this->authorize('update', $plot);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'color' => [
                'nullable',
                'string',
                'max:16',
                'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/',
            ],
            'polygon' => ['required', 'array', 'min:3'],
            'polygon.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'polygon.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $polygon = $this->normalizePolygon($data['polygon']);
        $geofence = $this->boundaryGuard->inspect(
            (int) $plot->farmer->municipality_id,
            $polygon
        );

        [$centroidLat, $centroidLng] = $this->computeCentroid($polygon);
        $areaHa = $this->areaHectaresSpherical($polygon);

        /** @var FarmPlot $plot */
        $plot = $this->concurrentWrite->execute(
            $plot,
            $request->input('_record_version'),
            function (FarmPlot $current) use (
                $data,
                $polygon,
                $areaHa,
                $centroidLat,
                $centroidLng
            ): FarmPlot {
                $current->update([
                    'name' => $data['name'] ?? null,
                    'color' => $this->normalizeHexColor($data['color'] ?? '#22c55e'),
                    'polygon_json' => $polygon,
                    'area_ha' => $areaHa,
                    'centroid_lat' => $centroidLat,
                    'centroid_lng' => $centroidLng,
                ]);

                return $current;
            }
        );

        $this->attachVersion($plot);

        return response()->json([
            'plot' => $plot,
            'geofence' => [
                'status' => $geofence['status'],
                'message' => $geofence['message'],
            ],
        ]);
    }

    public function destroy(Request $request, FarmPlot $plot)
    {
        $this->authorize('delete', $plot);

        $this->concurrentWrite->execute(
            $plot,
            $request->input('_record_version'),
            function (FarmPlot $current): void {
                $current->delete();
            }
        );

        return response()->json(['ok' => true]);
    }

    public function importKmlForm(Request $request)
    {
        $this->authorize('import', FarmPlot::class);

        return view('farm_plots.import_kml', [
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
        ]);
    }

    public function importKml(Request $request)
    {
        $this->authorize('import', FarmPlot::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:kml,xml'],
            'municipality_id' => ['nullable', 'integer'],
        ]);
        $municipalityId = $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $validated['municipality_id'] ?? null
        );

        $path = $request->file('file')->getRealPath();

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);

        if (! $xml) {
            return back()->with('error', 'Invalid KML file.');
        }

        $namespaces = $xml->getDocNamespaces(true);
        $kmlNs = $namespaces[''] ?? $namespaces['kml'] ?? 'http://www.opengis.net/kml/2.2';
        $xml->registerXPathNamespace('kml', $kmlNs);

        $placemarks = $xml->xpath('//kml:Placemark') ?: [];
        if (empty($placemarks)) {
            return back()->with('error', 'No placemarks found in the KML.');
        }

        $styleLookup = $this->buildKmlStyleLookup($xml, $kmlNs);

        $farmers = Farmer::query()
            ->where('municipality_id', $municipalityId)
            ->get([
                'id',
                'municipality_id',
                'rsbsa_no',
                'ffrs',
                'last_name',
                'first_name',
                'middle_name',
                'ext_name',
                'farm_location',
                'farm_municipality',
                'farm_province',
            ]);

        $created = 0;
        $updated = 0;
        $skippedNoPolygon = 0;
        $skippedNoFarmer = 0;
        $matchedByCode = 0;
        $matchedByFullName = 0;
        $matchedBySurnameBarangay = 0;
        $matchedByUniqueSurname = 0;
        $matchedByLooseSurname = 0;
        $skippedOutsideBoundary = 0;
        $boundaryWarnings = 0;

        DB::transaction(function () use (
            $placemarks,
            $kmlNs,
            $styleLookup,
            $farmers,
            &$created,
            &$updated,
            &$skippedNoPolygon,
            &$skippedNoFarmer,
            &$matchedByCode,
            &$matchedByFullName,
            &$matchedBySurnameBarangay,
            &$matchedByUniqueSurname,
            &$matchedByLooseSurname,
            &$skippedOutsideBoundary,
            &$boundaryWarnings
        ) {
            foreach ($placemarks as $placemark) {
                $baseName = trim((string) ($placemark->name ?? ''));
                $extended = $this->extractExtendedData($placemark, $kmlNs);

                [$parcelCode, $ownerName] = $this->extractParcelCodeAndOwner($baseName, $extended);
                $match = $this->matchFarmerDetailed($farmers, $parcelCode, $ownerName, $extended);
                $farmer = $match['farmer'] ?? null;
                $strategy = $match['strategy'] ?? null;

                $polygons = $this->extractPlacemarkPolygons($placemark, $kmlNs);
                if (empty($polygons)) {
                    $skippedNoPolygon++;

                    continue;
                }

                if (! $farmer) {
                    $skippedNoFarmer++;

                    continue;
                }

                if ($strategy === 'parcel_code') {
                    $matchedByCode++;
                } elseif ($strategy === 'full_name') {
                    $matchedByFullName++;
                } elseif ($strategy === 'surname_barangay') {
                    $matchedBySurnameBarangay++;
                } elseif ($strategy === 'surname_unique') {
                    $matchedByUniqueSurname++;
                } elseif ($strategy === 'surname_loose') {
                    $matchedByLooseSurname++;
                }

                $colorSeed = trim((string) (
                    $extended['GPX_ID']
                    ?? $extended['CONCATENAT']
                    ?? $parcelCode
                    ?? $baseName
                ));

                $resolvedColor = $this->resolvePlacemarkColor(
                    $placemark,
                    $kmlNs,
                    $styleLookup,
                    $colorSeed
                );
                $polygonCount = count($polygons);

                foreach ($polygons as $index => $polygon) {
                    $polygon = $this->normalizePolygon($polygon);

                    if (count($polygon) < 3) {
                        $skippedNoPolygon++;

                        continue;
                    }

                    try {
                        $geofence = $this->boundaryGuard->inspect(
                            (int) $farmer->municipality_id,
                            $polygon
                        );
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        $skippedOutsideBoundary++;

                        continue;
                    }

                    if (in_array($geofence['status'], ['partial', 'near_boundary'], true)) {
                        $boundaryWarnings++;
                    }

                    [$centroidLat, $centroidLng] = $this->computeCentroid($polygon);
                    $areaHa = $this->areaHectaresSpherical($polygon);

                    $plotName = $this->buildImportedPlotName(
                        $baseName,
                        $extended,
                        $parcelCode,
                        $index + 1,
                        $polygonCount
                    );

                    $payload = [
                        'farmer_id' => $farmer->id,
                        'name' => $plotName,
                        'color' => $resolvedColor,
                        'polygon_json' => $polygon,
                        'area_ha' => $areaHa,
                        'centroid_lat' => $centroidLat,
                        'centroid_lng' => $centroidLng,
                    ];

                    $existing = FarmPlot::query()
                        ->where('farmer_id', $farmer->id)
                        ->where('name', $plotName)
                        ->first();

                    if ($existing) {
                        $existing->update($payload);
                        $updated++;
                    } else {
                        FarmPlot::create($payload);
                        $created++;
                    }
                }
            }
        });

        return back()->with(
            'success',
            "KML import finished. Created: {$created}, Updated: {$updated}, "
            ."Skipped (no polygon): {$skippedNoPolygon}, "
            ."Skipped (no farmer match): {$skippedNoFarmer}, "
            ."Matched by code: {$matchedByCode}, "
            ."Matched by full name: {$matchedByFullName}, "
            ."Matched by surname+barangay: {$matchedBySurnameBarangay}, "
            ."Matched by unique surname: {$matchedByUniqueSurname}, "
            ."Matched by loose surname: {$matchedByLooseSurname}, "
            ."Skipped (outside/invalid boundary): {$skippedOutsideBoundary}, "
            ."Boundary review warnings: {$boundaryWarnings}"
        );
    }

    private function extractExtendedData(\SimpleXMLElement $placemark, string $kmlNs): array
    {
        $placemark->registerXPathNamespace('kml', $kmlNs);

        $data = [];

        $simpleDataNodes = $placemark->xpath('.//kml:ExtendedData//kml:SimpleData') ?: [];
        foreach ($simpleDataNodes as $node) {
            $attrs = $node->attributes();
            $key = trim((string) ($attrs['name'] ?? ''));
            if ($key === '') {
                continue;
            }
            $data[$key] = trim((string) $node);
        }

        $dataNodes = $placemark->xpath('.//kml:ExtendedData//kml:Data') ?: [];
        foreach ($dataNodes as $node) {
            $node->registerXPathNamespace('kml', $kmlNs);
            $attrs = $node->attributes();
            $key = trim((string) ($attrs['name'] ?? ''));
            if ($key === '') {
                continue;
            }

            $valueNode = $node->xpath('./kml:value');
            $value = ($valueNode && isset($valueNode[0]))
                ? trim((string) $valueNode[0])
                : trim((string) $node);

            $data[$key] = $value;
        }

        return $data;
    }

    private function buildKmlStyleLookup(\SimpleXMLElement $xml, string $kmlNs): array
    {
        $xml->registerXPathNamespace('kml', $kmlNs);

        $styleById = [];
        $styleMapById = [];

        $styleNodes = $xml->xpath('//kml:Style') ?: [];
        foreach ($styleNodes as $styleNode) {
            $attrs = $styleNode->attributes();
            $id = trim((string) ($attrs['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $styleById['#'.$id] = $this->extractStyleColorsFromStyleNode($styleNode, $kmlNs);
        }

        $styleMapNodes = $xml->xpath('//kml:StyleMap') ?: [];
        foreach ($styleMapNodes as $styleMapNode) {
            $attrs = $styleMapNode->attributes();
            $id = trim((string) ($attrs['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $pairs = $styleMapNode->xpath('./kml:Pair') ?: [];
            foreach ($pairs as $pair) {
                $key = trim((string) ($pair->key ?? ''));
                $styleUrl = trim((string) ($pair->styleUrl ?? ''));

                if ($key === 'normal' && $styleUrl !== '') {
                    $styleMapById['#'.$id] = $styleUrl;
                    break;
                }
            }
        }

        return [
            'styleById' => $styleById,
            'styleMapById' => $styleMapById,
        ];
    }

private function extractStyleColorsFromStyleNode(\SimpleXMLElement $styleNode, string $kmlNs): array
{
    $styleNode->registerXPathNamespace('kml', $kmlNs);

    $polyColor = '';
    $lineColor = '';
    $polyColorMode = 'normal';
    $lineColorMode = 'normal';

    $polyNodes = $styleNode->xpath('.//kml:PolyStyle') ?: [];
    if (! empty($polyNodes)) {
        $polyColorNode = $polyNodes[0]->xpath('./kml:color');
        $polyModeNode = $polyNodes[0]->xpath('./kml:colorMode');

        if ($polyColorNode && isset($polyColorNode[0])) {
            $polyColor = trim((string) $polyColorNode[0]);
        }
        if ($polyModeNode && isset($polyModeNode[0])) {
            $polyColorMode = trim((string) $polyModeNode[0]) ?: 'normal';
        }
    }

    $lineNodes = $styleNode->xpath('.//kml:LineStyle') ?: [];
    if (! empty($lineNodes)) {
        $lineColorNode = $lineNodes[0]->xpath('./kml:color');
        $lineModeNode = $lineNodes[0]->xpath('./kml:colorMode');

        if ($lineColorNode && isset($lineColorNode[0])) {
            $lineColor = trim((string) $lineColorNode[0]);
        }
        if ($lineModeNode && isset($lineModeNode[0])) {
            $lineColorMode = trim((string) $lineModeNode[0]) ?: 'normal';
        }
    }

    return [
        'poly' => $this->parseKmlColorToHex($polyColor),
        'line' => $this->parseKmlColorToHex($lineColor),
        'poly_mode' => strtolower($polyColorMode ?: 'normal'),
        'line_mode' => strtolower($lineColorMode ?: 'normal'),
        'poly_raw' => $polyColor,
        'line_raw' => $lineColor,
    ];
}

private function resolvePlacemarkColor(
    \SimpleXMLElement $placemark,
    string $kmlNs,
    array $styleLookup,
    string $seed = ''
): string {
    $placemark->registerXPathNamespace('kml', $kmlNs);

    $inlineStyles = $placemark->xpath('./kml:Style') ?: [];
    if (! empty($inlineStyles)) {
        $inline = $this->extractStyleColorsFromStyleNode($inlineStyles[0], $kmlNs);

        return $this->resolveColorFromStyleArray($inline, $seed);
    }

    $styleUrl = trim((string) ($placemark->styleUrl ?? ''));
    if ($styleUrl === '') {
        return $this->seededPlotColor($seed ?: (string) ($placemark->name ?? 'plot'));
    }

    $styleUrl = preg_replace('/\s+/', '', $styleUrl);

    if (isset($styleLookup['styleMapById'][$styleUrl])) {
        $styleUrl = $styleLookup['styleMapById'][$styleUrl];
    }

    $style = $styleLookup['styleById'][$styleUrl] ?? null;
    if (! $style) {
        return $this->seededPlotColor($seed ?: (string) ($placemark->name ?? 'plot'));
    }

    return $this->resolveColorFromStyleArray($style, $seed);
}

private function resolveColorFromStyleArray(array $style, string $seed = ''): string
{
    $seed = trim($seed) !== '' ? $seed : 'plot';

    $polyMode = strtolower((string) ($style['poly_mode'] ?? 'normal'));
    $lineMode = strtolower((string) ($style['line_mode'] ?? 'normal'));

    if ($polyMode === 'random') {
        return $this->seededPlotColor($seed, $style['poly_raw'] ?? 'ccffffff');
    }

    if ($lineMode === 'random') {
        return $this->seededPlotColor($seed, $style['line_raw'] ?? 'ffffffff');
    }

    if (! empty($style['line']) && strtoupper($style['line']) !== '#FFFFFF') {
        return $this->normalizeHexColor($style['line']);
    }

    if (! empty($style['poly'])) {
        return $this->normalizeHexColor($style['poly']);
    }

    if (! empty($style['line'])) {
        return $this->normalizeHexColor($style['line']);
    }

    return $this->seededPlotColor($seed);
}

private function seededPlotColor(string $seed, string $baseKmlColor = 'ccffffff'): string
{
    $seed = trim($seed) !== '' ? $seed : 'plot';
    $hash = md5($seed);

    // KML base is aabbggrr
    $baseHex = $this->parseKmlColorToHex($baseKmlColor);
    if ($baseHex === '') {
        $baseHex = '#FFFFFF';
    }

    $baseR = hexdec(substr($baseHex, 1, 2));
    $baseG = hexdec(substr($baseHex, 3, 2));
    $baseB = hexdec(substr($baseHex, 5, 2));

    $randR = hexdec(substr($hash, 0, 2)) / 255;
    $randG = hexdec(substr($hash, 2, 2)) / 255;
    $randB = hexdec(substr($hash, 4, 2)) / 255;

    $r = (int) round($baseR * $randR);
    $g = (int) round($baseG * $randG);
    $b = (int) round($baseB * $randB);

    // avoid colors that are too dark or too close to white
    $r = max(40, min(220, $r));
    $g = max(40, min(220, $g));
    $b = max(40, min(220, $b));

    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

    private function parseKmlColorToHex(?string $kmlColor): string
    {
        $kmlColor = trim((string) $kmlColor);

        // KML format: aabbggrr
        if (! preg_match('/^[0-9a-fA-F]{8}$/', $kmlColor)) {
            return '';
        }

        $bb = substr($kmlColor, 2, 2);
        $gg = substr($kmlColor, 4, 2);
        $rr = substr($kmlColor, 6, 2);

        return '#'.strtoupper($rr.$gg.$bb);
    }

    private function extractPlacemarkPolygons(\SimpleXMLElement $placemark, string $kmlNs): array
    {
        $placemark->registerXPathNamespace('kml', $kmlNs);

        $out = [];
        $polygonNodes = $placemark->xpath('.//kml:Polygon') ?: [];

        foreach ($polygonNodes as $polygonNode) {
            $polygonNode->registerXPathNamespace('kml', $kmlNs);
            $coordsNodes = $polygonNode->xpath('.//kml:outerBoundaryIs/kml:LinearRing/kml:coordinates') ?: [];

            foreach ($coordsNodes as $coordsNode) {
                $ring = $this->parseCoordinates((string) $coordsNode);
                if (count($ring) >= 3) {
                    $out[] = $ring;
                }
            }
        }

        return $out;
    }

    private function parseCoordinates(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $points = [];
        $chunks = preg_split('/\s+/', $text) ?: [];

        foreach ($chunks as $chunk) {
            $parts = explode(',', trim($chunk));
            if (count($parts) < 2) {
                continue;
            }

            $lng = (float) $parts[0];
            $lat = (float) $parts[1];

            if (! is_finite($lat) || ! is_finite($lng)) {
                continue;
            }

            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        if (count($points) > 1) {
            $first = $points[0];
            $last = $points[count($points) - 1];

            if (
                abs($first['lat'] - $last['lat']) < 0.0000000001 &&
                abs($first['lng'] - $last['lng']) < 0.0000000001
            ) {
                array_pop($points);
            }
        }

        return $points;
    }

    private function extractParcelCodeAndOwner(string $baseName, array $extended): array
    {
        $raw = trim((string) (
            $extended['CONCATENAT']
            ?? $extended['CONCATENATE']
            ?? $extended['file_name']
            ?? $baseName
        ));

        $raw = preg_replace('/\s+/', ' ', $raw);

        $parcelCode = null;
        $ownerName = $raw;

        $patterns = [
            '/^\s*((?:[A-Z0-9]{1,6}|\d{1,6})(?:\s*-\s*(?:[A-Z0-9]{1,6}|\d{1,6})){2,})\s+(.+)$/iu',
            '/^\s*((?:\d{2,4})(?:\s*-\s*\d{1,6}){2,})\s+(.+)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $raw, $m)) {
                $parcelCode = $this->normalizeParcelCode($m[1] ?? '');
                $ownerName = trim((string) ($m[2] ?? ''));
                break;
            }
        }

        if (! $parcelCode) {
            $codeFields = [
                'GPX_ID',
                'LGU RSBSA Number',
                'RSBSA',
                'RSBSA_NO',
                'RSBSA NO.',
                'FFRS',
                'FFRS System Generated No.',
            ];

            foreach ($codeFields as $field) {
                if (! empty($extended[$field])) {
                    $parcelCode = $this->normalizeParcelCode((string) $extended[$field]);
                    break;
                }
            }
        }

        $ownerName = preg_replace(
            '/^\s*(?:HEIRS OF|HEIRS|SPOUSES OF|SPOUSES|MAG-ASAWA NI|MAG-ASAWA)\s+/iu',
            '',
            $ownerName
        );

        return [$parcelCode, trim((string) $ownerName)];
    }

    private function matchFarmerDetailed(Collection $farmers, ?string $parcelCode, ?string $ownerName, array $extended = []): array
    {
        if ($parcelCode) {
            $normalizedTarget = $this->normalizeParcelCode($parcelCode);

            $byCode = $farmers->first(function ($farmer) use ($normalizedTarget) {
                return $normalizedTarget !== ''
                    && (
                        $this->normalizeParcelCode((string) $farmer->rsbsa_no) === $normalizedTarget
                        || $this->normalizeParcelCode((string) $farmer->ffrs) === $normalizedTarget
                    );
            });

            if ($byCode) {
                return ['farmer' => $byCode, 'strategy' => 'parcel_code'];
            }
        }

        $owner = $this->parseOwnerName($ownerName);
        if ($owner['surname'] === '') {
            return ['farmer' => null, 'strategy' => null];
        }

        $barangay = $this->normalizePlace(
            (string) ($extended['BARANGAY'] ?? $extended['barangay'] ?? '')
        );

        $surnameExactMatches = $farmers->filter(function ($farmer) use ($owner) {
            return $this->normalizeSurname((string) $farmer->last_name) === $owner['surname'];
        })->values();

        $surnameLooseMatches = $farmers->filter(function ($farmer) use ($owner) {
            return $this->normalizeSurnameLoose((string) $farmer->last_name) === $owner['surname_loose'];
        })->values();

        $surnameCandidates = $surnameExactMatches->isNotEmpty()
            ? $surnameExactMatches
            : $surnameLooseMatches;

        if ($owner['first'] !== '') {
            $fullNameMatches = $surnameCandidates->filter(function ($farmer) use ($owner) {
                return $this->firstNameMatches((string) $farmer->first_name, $owner['first'])
                    && $this->middleNameMatches((string) $farmer->middle_name, $owner['middle'])
                    && $this->suffixMatches((string) $farmer->ext_name, $owner['suffix']);
            })->values();

            $resolved = $this->resolveCandidates($fullNameMatches, $barangay);
            if ($resolved) {
                return ['farmer' => $resolved, 'strategy' => 'full_name'];
            }
        }

        if ($barangay !== '') {
            $resolvedSurnameBarangay = $this->resolveCandidates($surnameCandidates, $barangay);
            if ($resolvedSurnameBarangay) {
                return ['farmer' => $resolvedSurnameBarangay, 'strategy' => 'surname_barangay'];
            }
        }

        if ($surnameCandidates->count() === 1) {
            return ['farmer' => $surnameCandidates->first(), 'strategy' => 'surname_unique'];
        }

        if ($surnameCandidates->isNotEmpty()) {
            return ['farmer' => $surnameCandidates->first(), 'strategy' => 'surname_loose'];
        }

        return ['farmer' => null, 'strategy' => null];
    }

    private function resolveCandidates(Collection $candidates, string $barangay = ''): ?Farmer
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($barangay !== '') {
            $barangayMatches = $candidates->filter(function ($farmer) use ($barangay) {
                return $this->farmerMatchesBarangay($farmer, $barangay);
            })->values();

            if ($barangayMatches->count() === 1) {
                return $barangayMatches->first();
            }
        }

        return null;
    }

    private function farmerMatchesBarangay(Farmer $farmer, string $barangay): bool
    {
        $haystacks = [
            (string) $farmer->farm_location,
            (string) $farmer->farm_municipality,
            (string) $farmer->farm_province,
        ];

        foreach ($haystacks as $value) {
            $normalized = $this->normalizePlace($value);
            if ($normalized !== '' && str_contains($normalized, $barangay)) {
                return true;
            }
        }

        return false;
    }

    private function parseOwnerName(?string $ownerName): array
    {
        $ownerName = preg_replace('/\s+/', ' ', trim((string) $ownerName));

        if ($ownerName === '') {
            return [
                'surname' => '',
                'surname_loose' => '',
                'first' => '',
                'middle' => '',
                'suffix' => '',
            ];
        }

        $last = '';
        $rest = '';

        if (str_contains($ownerName, ',')) {
            [$last, $rest] = array_pad(array_map('trim', explode(',', $ownerName, 2)), 2, '');
        } else {
            $parts = preg_split('/\s+/', $ownerName) ?: [];

            if (count($parts) === 1) {
                $last = $parts[0];
                $rest = '';
            } else {
                $last = array_pop($parts);
                $rest = implode(' ', $parts);
            }
        }

        $restParts = preg_split('/\s+/', trim($rest)) ?: [];

        $suffix = '';
        if (! empty($restParts)) {
            $lastToken = end($restParts);
            if ($this->isSuffixToken($lastToken)) {
                $suffix = array_pop($restParts);
            }
        }

        $first = trim($restParts[0] ?? '');
        $middle = trim(implode(' ', array_slice($restParts, 1)));

        return [
            'surname' => $this->normalizeSurname($last),
            'surname_loose' => $this->normalizeSurnameLoose($last),
            'first' => $this->normalizeNameToken($first),
            'middle' => $this->normalizeNameToken($middle),
            'suffix' => $this->normalizeSuffix($suffix),
        ];
    }

    private function firstNameMatches(string $farmerFirstName, string $ownerFirstName): bool
    {
        $farmerFirstName = $this->normalizeNameToken($farmerFirstName);
        $ownerFirstName = $this->normalizeNameToken($ownerFirstName);

        if ($farmerFirstName === '' || $ownerFirstName === '') {
            return false;
        }

        return $farmerFirstName === $ownerFirstName
            || str_starts_with($farmerFirstName, $ownerFirstName)
            || str_starts_with($ownerFirstName, $farmerFirstName);
    }

    private function middleNameMatches(string $farmerMiddleName, string $ownerMiddleName): bool
    {
        $farmerMiddleName = $this->normalizeNameToken($farmerMiddleName);
        $ownerMiddleName = $this->normalizeNameToken($ownerMiddleName);

        if ($ownerMiddleName === '') {
            return true;
        }

        if ($farmerMiddleName === '') {
            return true;
        }

        $farmerInitial = substr($farmerMiddleName, 0, 1);
        $ownerInitial = substr($ownerMiddleName, 0, 1);

        return $farmerMiddleName === $ownerMiddleName
            || $farmerInitial === $ownerInitial
            || str_starts_with($farmerMiddleName, $ownerMiddleName)
            || str_starts_with($ownerMiddleName, $farmerMiddleName);
    }

    private function suffixMatches(string $farmerSuffix, string $ownerSuffix): bool
    {
        $farmerSuffix = $this->normalizeSuffix($farmerSuffix);
        $ownerSuffix = $this->normalizeSuffix($ownerSuffix);

        if ($ownerSuffix === '') {
            return true;
        }

        if ($farmerSuffix === '') {
            return true;
        }

        return $farmerSuffix === $ownerSuffix;
    }

    private function isSuffixToken(?string $token): bool
    {
        $token = $this->normalizeSuffix((string) $token);

        return in_array($token, ['JR', 'SR', 'II', 'III', 'IV', 'V'], true);
    }

    private function normalizeParcelCode(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $value = preg_replace('/\s*-\s*/', '-', $value);
        $value = preg_replace('/[^A-Z0-9\-]/', '', $value);
        $value = preg_replace('/\-+/', '-', $value);

        return trim((string) $value, '-');
    }

    private function normalizeNameToken(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $value = preg_replace('/[.,\'"`]/u', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function normalizeSuffix(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $value = preg_replace('/[.\s]/u', '', $value);

        return $value;
    }

    private function normalizeSurname(?string $value): string
    {
        return $this->normalizeNameToken($value);
    }

    private function normalizeSurnameLoose(?string $value): string
    {
        $value = $this->normalizeNameToken($value);
        $value = str_replace([' ', '-', '_'], '', $value);

        return $value;
    }

    private function normalizePlace(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $value = preg_replace('/\b(BRGY|BARANGAY|BRGY\.)\b/u', '', $value);
        $value = preg_replace('/[.,\'"`]/u', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function buildImportedPlotName(
        string $baseName,
        array $extended,
        ?string $parcelCode,
        int $partNo,
        int $totalParts
    ): string {
        $base = trim((string) (
            $extended['GPX_ID']
            ?? $extended['CONCATENAT']
            ?? $extended['CONCATENATE']
            ?? $parcelCode
            ?? $baseName
            ?? 'Imported Plot'
        ));

        if ($totalParts > 1) {
            return $base.' Part '.$partNo;
        }

        return $base;
    }

    private function normalizePolygon(array $polygon): array
    {
        $out = [];

        foreach ($polygon as $p) {
            if (! isset($p['lat'], $p['lng'])) {
                continue;
            }

            $lat = (float) $p['lat'];
            $lng = (float) $p['lng'];

            if (! is_finite($lat) || ! is_finite($lng)) {
                continue;
            }

            $out[] = [
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return $out;
    }

    private function attachVersion(FarmPlot $plot): FarmPlot
    {
        $plot->setAttribute(
            '_record_version',
            ConcurrentWrite::version($plot)
        );

        return $plot;
    }

    /**
     * Keep the Static Maps URL safely below provider URL limits while
     * retaining the first and last sections of complex parcel boundaries.
     */
    private function sampleStaticMapPolygon(
        array $polygon,
        int $maximumPoints = 180
    ): array {
        $count = count($polygon);
        if ($count <= $maximumPoints) {
            return $polygon;
        }

        $sampled = [];
        $lastIndex = $count - 1;

        for ($index = 0; $index < $maximumPoints; $index++) {
            $sourceIndex = (int) round(
                ($index / ($maximumPoints - 1)) * $lastIndex
            );
            $sampled[] = $polygon[$sourceIndex];
        }

        return $sampled;
    }

    private function normalizeHexColor(?string $hex): string
    {
        $hex = strtoupper(trim((string) $hex));

        if ($hex === '') {
            return '#22C55E';
        }

        if ($hex[0] !== '#') {
            $hex = '#'.$hex;
        }

        if (preg_match('/^#([0-9A-F]{3})$/', $hex)) {
            return '#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
        }

        if (preg_match('/^#([0-9A-F]{6})$/', $hex)) {
            return $hex;
        }

        if (preg_match('/^#([0-9A-F]{8})$/', $hex)) {
            return substr($hex, 0, 7);
        }

        return '#22C55E';
    }

    private function computeCentroid(array $polygon): array
    {
        $sumLat = 0.0;
        $sumLng = 0.0;

        foreach ($polygon as $p) {
            $sumLat += $p['lat'];
            $sumLng += $p['lng'];
        }

        return [
            $sumLat / max(count($polygon), 1),
            $sumLng / max(count($polygon), 1),
        ];
    }

    private function areaHectaresSpherical(array $poly): float
    {
        $R = 6378137.0;
        $n = count($poly);

        if ($n < 3) {
            return 0.0;
        }

        $pts = $poly;
        $pts[] = $poly[0];

        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $lat1 = deg2rad($pts[$i]['lat']);
            $lon1 = deg2rad($pts[$i]['lng']);
            $lat2 = deg2rad($pts[$i + 1]['lat']);
            $lon2 = deg2rad($pts[$i + 1]['lng']);

            $sum += ($lon2 - $lon1) * (2 + sin($lat1) + sin($lat2));
        }

        $areaM2 = abs($sum) * ($R * $R / 2.0);

        return $areaM2 / 10000.0;
    }
}
