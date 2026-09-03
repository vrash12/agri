<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\GeoGeometry;
use App\Support\MunicipalityAccess;
use App\Support\MunicipalityBoundaryImporter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MunicipalityBoundaryController extends Controller
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $concurrentWrite,
        private GeoGeometry $geometry,
        private MunicipalityBoundaryImporter $importer
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', MunicipalityBoundary::class);

        $municipalities = $this->municipalityAccess->choices($request->user());
        $boundaryQuery = MunicipalityBoundary::query()
            ->with('municipality:id,name,province')
            ->current();
        $this->municipalityAccess->scope($boundaryQuery, $request->user());
        $boundaries = $boundaryQuery
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MunicipalityBoundary $boundary) => $this->boundaryData($boundary));

        $municipalityIds = $municipalities->pluck('id');
        $farmerCount = Farmer::query()
            ->whereIn('municipality_id', $municipalityIds)
            ->count();
        $plotQuery = FarmPlot::query()->whereHas(
            'farmer',
            fn (Builder $query) => $query->whereIn('municipality_id', $municipalityIds)
        );

        return view('municipality_boundaries.index', [
            'municipalities' => $municipalities,
            'boundaries' => $boundaries,
            'canManageBoundaries' => $request->user()->can('create', MunicipalityBoundary::class),
            'googleMapsApiKey' => (string) config('services.google_maps.key', ''),
            'googleMapsMapId' => (string) config('services.google_maps.map_id', ''),
            'summary' => [
                'municipalities' => $municipalities->count(),
                'configured' => $boundaries->where('status', MunicipalityBoundary::STATUS_ACTIVE)->count(),
                'boundary_area_ha' => round((float) $boundaries
                    ->where('status', MunicipalityBoundary::STATUS_ACTIVE)
                    ->sum('area_ha'), 2),
                'farmers' => $farmerCount,
                'parcels' => (clone $plotQuery)->count(),
                'mapped_area_ha' => round((float) (clone $plotQuery)->sum('area_ha'), 2),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MunicipalityBoundary::class);

        $validated = $request->validate([
            'municipality_id' => ['required', 'integer'],
        ]);
        $municipalityId = (int) $validated['municipality_id'];

        $boundaryQuery = MunicipalityBoundary::query()
            ->with('municipality:id,name,province')
            ->where('municipality_id', $municipalityId);
        $this->municipalityAccess->scope($boundaryQuery, $request->user());
        $boundaries = $boundaryQuery
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->get();

        $municipality = Municipality::query()
            ->active()
            ->whereKey($municipalityId)
            ->whereIn('id', $this->municipalityAccess->choices($request->user())->pluck('id'))
            ->firstOrFail();
        $activeBoundary = $boundaries->firstWhere('status', MunicipalityBoundary::STATUS_ACTIVE);

        $farmers = Farmer::query()
            ->where('municipality_id', $municipalityId)
            ->count();
        $plots = FarmPlot::query()
            ->with('farmer:id,municipality_id,first_name,middle_name,last_name,ffrs,farm_location')
            ->whereHas('farmer', fn (Builder $query) => $query->where('municipality_id', $municipalityId))
            ->orderBy('id')
            ->get([
                'id', 'farmer_id', 'name', 'color', 'polygon_json',
                'area_ha', 'centroid_lat', 'centroid_lng', 'updated_at',
            ]);

        $review = [];
        $plotStatuses = [];
        $statusCounts = [
            'inside' => 0,
            'near_boundary' => 0,
            'partial' => 0,
            'outside' => 0,
            'invalid' => 0,
            'unconfigured' => $activeBoundary ? 0 : $plots->count(),
        ];

        if ($activeBoundary) {
            foreach ($plots as $plot) {
                try {
                    $status = $this->geometry->classifyParcel(
                        is_array($plot->polygon_json) ? $plot->polygon_json : [],
                        $activeBoundary->geojson
                    );
                } catch (InvalidArgumentException $exception) {
                    $status = 'invalid';
                }

                $statusCounts[$status]++;
                $plotStatuses[$plot->id] = $status;
                if (in_array($status, ['near_boundary', 'partial', 'outside', 'invalid'], true)) {
                    $review[] = $this->reviewData($plot, $status);
                }
            }
        }

        if (! $activeBoundary) {
            foreach ($plots as $plot) {
                $plotStatuses[$plot->id] = 'unconfigured';
            }
        }

        $mappedFarmers = $plots->pluck('farmer_id')->unique()->count();
        $snapshot = null;

        if ($activeBoundary) {
            $frame = $this->snapshotFrame($activeBoundary, $plots);
            $frameVersion = $this->snapshotFrameVersion($frame);
            Cache::put(
                $this->snapshotFrameCacheKey($activeBoundary->id, $frameVersion),
                $frame,
                now()->addHours(6)
            );
            $snapshot = array_merge($frame, [
                'viewport_size' => 640,
                'base_map_url' => route('municipality-boundaries.snapshot-base', [
                    'boundary' => $activeBoundary,
                    'v' => $frameVersion,
                ]),
                'audit_url' => route('municipality-boundaries.snapshot-exported', $activeBoundary),
            ]);
        }

        return response()->json([
            'municipality' => [
                'id' => $municipality->id,
                'name' => $municipality->name,
                'province' => $municipality->province,
            ],
            'boundaries' => $boundaries
                ->map(fn (MunicipalityBoundary $boundary) => $this->boundaryData($boundary))
                ->values(),
            'parcels' => $plots->map(fn (FarmPlot $plot) => [
                'id' => $plot->id,
                'name' => $plot->name ?: 'Parcel '.$plot->id,
                'color' => $plot->color ?: '#22C55E',
                'polygon' => $plot->polygon_json,
                'area_ha' => round((float) $plot->area_ha, 4),
                'geofence_status' => $plotStatuses[$plot->id] ?? 'unconfigured',
                'farmer' => [
                    'id' => $plot->farmer?->id,
                    'name' => trim(collect([
                        $plot->farmer?->first_name,
                        $plot->farmer?->middle_name,
                        $plot->farmer?->last_name,
                    ])->filter()->implode(' ')),
                    'ffrs' => $plot->farmer?->ffrs,
                    'location' => $plot->farmer?->farm_location,
                ],
            ])->values(),
            'stats' => [
                'farmers' => $farmers,
                'mapped_farmers' => $mappedFarmers,
                'parcels' => $plots->count(),
                'mapped_area_ha' => round((float) $plots->sum('area_ha'), 2),
                'outside' => $statusCounts['outside'],
                'partial' => $statusCounts['partial'],
                'near_boundary' => $statusCounts['near_boundary'],
                'invalid' => $statusCounts['invalid'],
                'unconfigured' => $statusCounts['unconfigured'],
            ],
            'review' => $review,
            'snapshot' => $snapshot,
        ]);
    }

    public function snapshotBase(Request $request, MunicipalityBoundary $boundary)
    {
        $this->authorize('view', $boundary);

        if (! $boundary->isActive()) {
            return response('Only the active official municipality boundary can be exported.', 422)
                ->header('Cache-Control', 'no-store');
        }

        $apiKey = (string) config('services.google_maps.static_key', '');
        if ($apiKey === '') {
            return response('Google Maps Static API is not configured.', 503)
                ->header('Cache-Control', 'no-store');
        }

        $requestedVersion = preg_match('/^[a-f0-9]{16}$/', (string) $request->query('v'))
            ? (string) $request->query('v')
            : null;
        $frame = $requestedVersion
            ? Cache::get($this->snapshotFrameCacheKey($boundary->id, $requestedVersion))
            : null;

        if (! is_array($frame)) {
            $frame = $this->snapshotFrame(
                $boundary,
                $this->municipalityPlots($boundary->municipality_id)
            );
            $requestedVersion = $this->snapshotFrameVersion($frame);
            Cache::put(
                $this->snapshotFrameCacheKey($boundary->id, $requestedVersion),
                $frame,
                now()->addHours(6)
            );
        }

        $cacheKey = 'municipality-snapshot-base:v3:'
            .$boundary->id.':'
            .$requestedVersion;
        $map = Cache::get($cacheKey);

        if (! is_array($map)) {
            try {
                $map = Cache::lock($cacheKey.':refresh-lock', 30)->block(
                    5,
                    function () use ($cacheKey, $apiKey, $frame): ?array {
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
                                'center' => number_format($frame['center_lat'], 7, '.', '').','.number_format($frame['center_lng'], 7, '.', ''),
                                'zoom' => $frame['zoom'],
                                'size' => '640x640',
                                'scale' => 2,
                                'format' => 'png',
                                'maptype' => 'satellite',
                                'key' => $apiKey,
                            ]
                        );

                        $contentType = strtolower((string) $response->header('Content-Type'));
                        if (! $response->successful() || ! str_starts_with($contentType, 'image/') || $response->body() === '') {
                            report(new \RuntimeException('Google municipality snapshot request failed with HTTP '.$response->status().'.'));

                            return null;
                        }

                        $map = [
                            'body' => $response->body(),
                            'content_type' => $contentType,
                        ];
                        Cache::put($cacheKey, $map, now()->addHours(6));

                        return $map;
                    }
                );
            } catch (LockTimeoutException $exception) {
                $map = Cache::get($cacheKey);
            } catch (\Throwable $exception) {
                report($exception);
                $map = Cache::get($cacheKey);
            }
        }

        if (! is_array($map) || ! isset($map['body'])) {
            return response('Google could not generate the municipality satellite image.', 502)
                ->header('Cache-Control', 'no-store');
        }

        return response($map['body'], 200, [
            'Content-Type' => $map['content_type'] ?: 'image/png',
            'Cache-Control' => 'private, max-age=21600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function snapshotExported(MunicipalityBoundary $boundary): JsonResponse
    {
        $this->authorize('view', $boundary);

        if (! $boundary->isActive()) {
            return response()->json([
                'message' => 'Only the active official municipality boundary can be exported.',
            ], 422);
        }

        $plots = $this->municipalityPlots($boundary->municipality_id);
        $frame = $this->snapshotFrame($boundary, $plots);
        $auditLog = AuditTrail::record(
            'exported',
            'Municipality geofences',
            sprintf('%s generated the municipality land snapshot for %s.', auth()->user()?->name ?? 'System', $boundary->municipality?->name ?? 'a municipality'),
            [
                'auditable' => $boundary,
                'municipality_id' => $boundary->municipality_id,
                'metadata' => [
                    'boundary_updated_at' => optional($boundary->updated_at)->toIso8601String(),
                    'parcel_count' => $plots->count(),
                    'mapped_area_ha' => round((float) $plots->sum('area_ha'), 4),
                    'parcel_statuses' => $this->snapshotStatusCounts($boundary, $plots),
                    'zoom' => $frame['zoom'],
                    'image_size' => '1280x1280',
                ],
            ]
        );

        return response()->json(['recorded' => $auditLog !== null]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MunicipalityBoundary::class);
        $validated = $this->validateBoundaryRequest($request, true);
        $geometry = $this->decodeGeometry($validated['geojson']);

        return $this->persistNew($request, $validated, $geometry, 'drawn');
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('import', MunicipalityBoundary::class);
        $validated = $request->validate([
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:150'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::in([MunicipalityBoundary::STATUS_DRAFT, MunicipalityBoundary::STATUS_ACTIVE])],
            'replace_confirmed' => ['nullable', 'boolean'],
            'file' => [
                'bail',
                'required',
                'file',
                'max:10240',
                function (string $attribute, $value, $fail): void {
                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    if (! in_array($extension, ['kml', 'kmz', 'json', 'geojson', 'xml'], true)) {
                        $fail('Upload a KML, KMZ, GeoJSON, JSON, or XML boundary file.');
                    }
                },
            ],
        ]);

        try {
            $geometry = $this->importer->import($request->file('file'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return $this->persistNew($request, $validated, $geometry, 'imported', [
            'source_file' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    public function update(Request $request, MunicipalityBoundary $boundary): JsonResponse
    {
        $this->authorize('update', $boundary);
        $validated = $this->validateBoundaryRequest($request, false);
        $newGeometry = array_key_exists('geojson', $validated)
            ? $this->decodeGeometry($validated['geojson'])
            : $boundary->geojson;
        $geometryChanged = json_encode($newGeometry) !== json_encode($boundary->geojson);

        if ($boundary->isActive() && $geometryChanged && ! $request->boolean('replace_confirmed')) {
            throw ValidationException::withMessages([
                'replace_confirmed' => 'Confirm that you want to replace the active official boundary geometry.',
            ]);
        }

        $this->assertNoOverlap($newGeometry, $boundary->municipality_id, $boundary->id);
        $before = $this->auditSnapshot($boundary);
        $payload = $this->geometryPayload($newGeometry);

        /** @var MunicipalityBoundary $updated */
        $updated = $this->concurrentWrite->execute(
            $boundary,
            $request->input('_record_version'),
            function (MunicipalityBoundary $current) use ($validated, $payload, $request): MunicipalityBoundary {
                $current->update(array_merge($payload, [
                    'name' => $validated['name'] ?? $current->name,
                    'color' => strtoupper($validated['color'] ?? $current->color),
                    'updated_by' => $request->user()->id,
                ]));

                return $current->fresh(['municipality:id,name,province']);
            }
        );

        $this->forgetCache($updated->municipality_id);
        $this->recordAudit('updated', $updated, $before, $this->auditSnapshot($updated));

        return response()->json([
            'message' => 'Municipality boundary updated.',
            'boundary' => $this->boundaryData($updated),
        ]);
    }

    public function activate(Request $request, MunicipalityBoundary $boundary): JsonResponse
    {
        $this->authorize('activate', $boundary);
        $request->validate([
            '_record_version' => ['required', 'string'],
            'replace_confirmed' => ['accepted'],
        ]);
        $this->assertNoOverlap($boundary->geojson, $boundary->municipality_id, $boundary->id);
        $before = $this->auditSnapshot($boundary);
        $replacedBoundaries = collect();

        /** @var MunicipalityBoundary $activated */
        $activated = $this->concurrentWrite->execute(
            $boundary,
            $request->input('_record_version'),
            function (MunicipalityBoundary $current) use ($request, &$replacedBoundaries): MunicipalityBoundary {
                $replacedBoundaries = MunicipalityBoundary::query()
                    ->where('municipality_id', $current->municipality_id)
                    ->where('status', MunicipalityBoundary::STATUS_ACTIVE)
                    ->where('id', '!=', $current->id)
                    ->lockForUpdate()
                    ->get();
                MunicipalityBoundary::query()
                    ->whereIn('id', $replacedBoundaries->pluck('id')->all())
                    ->update([
                        'status' => MunicipalityBoundary::STATUS_ARCHIVED,
                        'archived_at' => now(),
                        'updated_by' => $request->user()->id,
                        'updated_at' => now(),
                    ]);
                $current->update([
                    'status' => MunicipalityBoundary::STATUS_ACTIVE,
                    'archived_at' => null,
                    'updated_by' => $request->user()->id,
                ]);

                return $current->fresh(['municipality:id,name,province']);
            }
        );

        $this->forgetCache($activated->municipality_id);
        $this->recordAudit('activated', $activated, $before, $this->auditSnapshot($activated));
        foreach ($replacedBoundaries as $replaced) {
            $after = $replaced->fresh(['municipality:id,name,province']);
            $this->recordAudit('archived', $after, $this->auditSnapshot($replaced), $this->auditSnapshot($after), [
                'replaced_by_boundary_id' => $activated->id,
            ]);
        }

        return response()->json([
            'message' => 'Official municipality boundary activated.',
            'boundary' => $this->boundaryData($activated),
        ]);
    }

    public function archive(Request $request, MunicipalityBoundary $boundary): JsonResponse
    {
        $this->authorize('archive', $boundary);
        $request->validate([
            '_record_version' => ['required', 'string'],
            'archive_confirmed' => ['accepted'],
        ]);
        $before = $this->auditSnapshot($boundary);

        /** @var MunicipalityBoundary $archived */
        $archived = $this->concurrentWrite->execute(
            $boundary,
            $request->input('_record_version'),
            function (MunicipalityBoundary $current) use ($request): MunicipalityBoundary {
                $current->update([
                    'status' => MunicipalityBoundary::STATUS_ARCHIVED,
                    'archived_at' => now(),
                    'updated_by' => $request->user()->id,
                ]);

                return $current->fresh(['municipality:id,name,province']);
            }
        );

        $this->forgetCache($archived->municipality_id);
        $this->recordAudit('archived', $archived, $before, $this->auditSnapshot($archived));

        return response()->json([
            'message' => 'Municipality boundary archived.',
            'boundary' => $this->boundaryData($archived),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateBoundaryRequest(Request $request, bool $creating): array
    {
        $rules = [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'geojson' => [$creating ? 'required' : 'sometimes'],
            'replace_confirmed' => ['nullable', 'boolean'],
        ];

        if ($creating) {
            $rules['municipality_id'] = ['required', 'integer', Rule::exists('municipalities', 'id')->where('is_active', true)];
            $rules['status'] = ['required', Rule::in([MunicipalityBoundary::STATUS_DRAFT, MunicipalityBoundary::STATUS_ACTIVE])];
        } else {
            $rules['_record_version'] = ['required', 'string'];
        }

        return $request->validate($rules);
    }

    /** @param  mixed  $raw @return array<string, mixed> */
    private function decodeGeometry($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw)) {
            throw ValidationException::withMessages(['geojson' => 'Provide valid GeoJSON polygon geometry.']);
        }

        try {
            return $this->geometry->prepare($raw);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['geojson' => $exception->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $geometry
     * @param  array<string, mixed>  $metadata
     */
    private function persistNew(
        Request $request,
        array $validated,
        array $geometry,
        string $event,
        array $metadata = []
    ): JsonResponse {
        $municipalityId = (int) $validated['municipality_id'];
        $status = $validated['status'];
        $this->assertNoOverlap($geometry, $municipalityId);

        $existingActive = MunicipalityBoundary::query()
            ->active()
            ->where('municipality_id', $municipalityId)
            ->exists();
        if ($status === MunicipalityBoundary::STATUS_ACTIVE && $existingActive && ! $request->boolean('replace_confirmed')) {
            throw ValidationException::withMessages([
                'replace_confirmed' => 'This municipality already has an active official boundary. Confirm replacement to continue.',
            ]);
        }

        /** @var MunicipalityBoundary $boundary */
        $replacedBoundaries = collect();
        $boundary = $this->concurrentWrite->transaction(function () use (
            $request, $validated, $geometry, $municipalityId, $status, &$replacedBoundaries
        ): MunicipalityBoundary {
            MunicipalityBoundary::query()
                ->where('municipality_id', $municipalityId)
                ->lockForUpdate()
                ->get();

            if ($status === MunicipalityBoundary::STATUS_ACTIVE) {
                $replacedBoundaries = MunicipalityBoundary::query()
                    ->active()
                    ->where('municipality_id', $municipalityId)
                    ->lockForUpdate()
                    ->get();
                MunicipalityBoundary::query()
                    ->whereIn('id', $replacedBoundaries->pluck('id')->all())
                    ->update([
                        'status' => MunicipalityBoundary::STATUS_ARCHIVED,
                        'archived_at' => now(),
                        'updated_by' => $request->user()->id,
                        'updated_at' => now(),
                    ]);
            }

            return MunicipalityBoundary::query()->create(array_merge(
                $this->geometryPayload($geometry),
                [
                    'municipality_id' => $municipalityId,
                    'name' => $validated['name'],
                    'color' => strtoupper($validated['color'] ?? '#15803D'),
                    'status' => $status,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]
            ))->load('municipality:id,name,province');
        });

        $this->forgetCache($municipalityId);
        $this->recordAudit($event, $boundary, null, $this->auditSnapshot($boundary), $metadata);
        foreach ($replacedBoundaries as $replaced) {
            $after = $replaced->fresh(['municipality:id,name,province']);
            $this->recordAudit('archived', $after, $this->auditSnapshot($replaced), $this->auditSnapshot($after), [
                'replaced_by_boundary_id' => $boundary->id,
            ]);
        }

        return response()->json([
            'message' => $status === MunicipalityBoundary::STATUS_ACTIVE
                ? 'Official municipality boundary saved and activated.'
                : 'Municipality boundary saved as a draft.',
            'boundary' => $this->boundaryData($boundary),
        ], 201);
    }

    /** @param  array<string, mixed>  $geometry */
    private function assertNoOverlap(array $geometry, int $municipalityId, ?int $exceptId = null): void
    {
        $bounds = $this->geometry->bounds($geometry);
        $query = MunicipalityBoundary::query()
            ->with('municipality:id,name')
            ->active()
            ->where('municipality_id', '!=', $municipalityId)
            ->where('max_lat', '>=', $bounds['min_lat'])
            ->where('min_lat', '<=', $bounds['max_lat'])
            ->where('max_lng', '>=', $bounds['min_lng'])
            ->where('min_lng', '<=', $bounds['max_lng']);
        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        foreach ($query->get() as $other) {
            if ($this->geometry->overlaps($geometry, $other->geojson)) {
                throw ValidationException::withMessages([
                    'geojson' => 'This boundary overlaps the active official boundary for '.$other->municipality->name.'. Resolve the overlap before saving.',
                ]);
            }
        }
    }

    /** @param  array<string, mixed>  $geometry @return array<string, mixed> */
    private function geometryPayload(array $geometry): array
    {
        $metadata = $this->geometry->metadata($geometry);

        return [
            'geojson' => $geometry,
            'area_ha' => $metadata['area_ha'],
            'centroid_lat' => $metadata['centroid_lat'],
            'centroid_lng' => $metadata['centroid_lng'],
            'min_lat' => $metadata['min_lat'],
            'max_lat' => $metadata['max_lat'],
            'min_lng' => $metadata['min_lng'],
            'max_lng' => $metadata['max_lng'],
            'vertex_count' => $metadata['vertices'],
        ];
    }

    /** @return array<string, mixed> */
    private function boundaryData(MunicipalityBoundary $boundary): array
    {
        return [
            'id' => $boundary->id,
            'municipality_id' => $boundary->municipality_id,
            'municipality_name' => $boundary->municipality?->name,
            'name' => $boundary->name,
            'geojson' => $boundary->geojson,
            'color' => $boundary->color,
            'status' => $boundary->status,
            'area_ha' => round((float) $boundary->area_ha, 2),
            'centroid_lat' => (float) $boundary->centroid_lat,
            'centroid_lng' => (float) $boundary->centroid_lng,
            'vertex_count' => $boundary->vertex_count,
            'updated_at' => optional($boundary->updated_at)->toIso8601String(),
            '_record_version' => ConcurrentWrite::version($boundary),
        ];
    }

    /** @return array<string, mixed> */
    private function reviewData(FarmPlot $plot, string $status): array
    {
        return [
            'plot_id' => $plot->id,
            'plot_name' => $plot->name ?: 'Parcel '.$plot->id,
            'status' => $status,
            'area_ha' => round((float) $plot->area_ha, 4),
            'farmer_id' => $plot->farmer?->id,
            'farmer_name' => trim(collect([
                $plot->farmer?->first_name,
                $plot->farmer?->middle_name,
                $plot->farmer?->last_name,
            ])->filter()->implode(' ')),
            'ffrs' => $plot->farmer?->ffrs,
            'location' => $plot->farmer?->farm_location,
        ];
    }

    private function municipalityPlots(int $municipalityId)
    {
        return FarmPlot::query()
            ->whereHas('farmer', fn (Builder $query) => $query->where('municipality_id', $municipalityId))
            ->get(['id', 'farmer_id', 'polygon_json', 'area_ha', 'updated_at']);
    }

    /** @return array<string, int> */
    private function snapshotStatusCounts(MunicipalityBoundary $boundary, $plots): array
    {
        $counts = [
            'inside' => 0,
            'near_boundary' => 0,
            'partial' => 0,
            'outside' => 0,
            'invalid' => 0,
        ];

        foreach ($plots as $plot) {
            try {
                $status = $this->geometry->classifyParcel(
                    is_array($plot->polygon_json) ? $plot->polygon_json : [],
                    $boundary->geojson
                );
            } catch (InvalidArgumentException $exception) {
                $status = 'invalid';
            }

            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Calculate the exact Web Mercator frame for the active boundary and all
     * municipality-owned parcels, including parcels outside the geofence.
     */
    private function snapshotFrame(MunicipalityBoundary $boundary, $plots): array
    {
        $minLat = (float) $boundary->min_lat;
        $maxLat = (float) $boundary->max_lat;
        $minLng = (float) $boundary->min_lng;
        $maxLng = (float) $boundary->max_lng;

        foreach ($plots as $plot) {
            foreach ((array) $plot->polygon_json as $point) {
                if (! isset($point['lat'], $point['lng'])) {
                    continue;
                }

                $lat = (float) $point['lat'];
                $lng = (float) $point['lng'];
                if (! is_finite($lat) || ! is_finite($lng)) {
                    continue;
                }

                $minLat = min($minLat, $lat);
                $maxLat = max($maxLat, $lat);
                $minLng = min($minLng, $lng);
                $maxLng = max($maxLng, $lng);
            }
        }

        [$westX, $northY] = $this->mercatorWorldPoint($maxLat, $minLng);
        [$eastX, $southY] = $this->mercatorWorldPoint($minLat, $maxLng);
        $width = max(0.0000001, abs($eastX - $westX));
        $height = max(0.0000001, abs($southY - $northY));
        $available = 520.0;
        $zoom = (int) floor(min(log($available / $width, 2), log($available / $height, 2)));
        $zoom = max(5, min(20, $zoom));
        $centerX = ($westX + $eastX) / 2;
        $centerY = ($northY + $southY) / 2;
        [$centerLat, $centerLng] = $this->inverseMercatorWorldPoint($centerX, $centerY);

        return [
            'center_lat' => round($centerLat, 7),
            'center_lng' => round($centerLng, 7),
            'zoom' => $zoom,
            'source_size' => 1280,
            'scale' => 2,
        ];
    }

    /** @param  array<string, int|float>  $frame */
    private function snapshotFrameVersion(array $frame): string
    {
        return substr(sha1(json_encode(['v3', $frame])), 0, 16);
    }

    private function snapshotFrameCacheKey(int $boundaryId, string $version): string
    {
        return 'municipality-snapshot-frame:'.$boundaryId.':'.$version;
    }

    /** @return array{0:float,1:float} */
    private function mercatorWorldPoint(float $lat, float $lng): array
    {
        $lat = max(-85.05112878, min(85.05112878, $lat));
        $sine = sin(deg2rad($lat));

        return [
            (($lng + 180.0) / 360.0) * 256.0,
            (0.5 - log((1 + $sine) / (1 - $sine)) / (4 * M_PI)) * 256.0,
        ];
    }

    /** @return array{0:float,1:float} */
    private function inverseMercatorWorldPoint(float $x, float $y): array
    {
        $lng = ($x / 256.0) * 360.0 - 180.0;
        $n = M_PI - (2.0 * M_PI * $y / 256.0);
        $lat = rad2deg(atan(sinh($n)));

        return [$lat, $lng];
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(MunicipalityBoundary $boundary): array
    {
        return [
            'municipality_id' => $boundary->municipality_id,
            'name' => $boundary->name,
            'color' => $boundary->color,
            'status' => $boundary->status,
            'archived_at' => optional($boundary->archived_at)->toIso8601String(),
            'geometry' => [
                'type' => $boundary->geojson['type'] ?? null,
                'vertices' => $boundary->vertex_count,
                'area_ha' => round((float) $boundary->area_ha, 4),
                'centroid_lat' => (float) $boundary->centroid_lat,
                'centroid_lng' => (float) $boundary->centroid_lng,
                'bounds' => [
                    'min_lat' => (float) $boundary->min_lat,
                    'max_lat' => (float) $boundary->max_lat,
                    'min_lng' => (float) $boundary->min_lng,
                    'max_lng' => (float) $boundary->max_lng,
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>|null  $before @param  array<string, mixed>  $after @param  array<string, mixed>  $metadata */
    private function recordAudit(
        string $event,
        MunicipalityBoundary $boundary,
        ?array $before,
        array $after,
        array $metadata = []
    ): void {
        AuditTrail::record(
            $event,
            'Municipality geofences',
            sprintf('%s %s the “%s” boundary for %s.', auth()->user()?->name ?? 'System', $event, $boundary->name, $boundary->municipality?->name ?? 'a municipality'),
            [
                'auditable' => $boundary,
                'municipality_id' => $boundary->municipality_id,
                'old_values' => $before,
                'new_values' => $after,
                'metadata' => $metadata,
            ]
        );
    }

    private function forgetCache(int $municipalityId): void
    {
        Cache::forget('municipality-boundary:active:v1:'.$municipalityId);
    }
}
