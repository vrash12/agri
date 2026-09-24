<?php

namespace App\Support;

use App\Exceptions\SatelliteUnavailable;
use App\Models\FarmPlot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class SentinelImagery
{
    private const TOKEN_URL = 'https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token';

    private const STATISTICS_URL = 'https://sh.dataspace.copernicus.eu/statistics/v1';

    private const PROCESS_URL = 'https://sh.dataspace.copernicus.eu/api/v1/process';

    private const CRS = 'http://www.opengis.net/def/crs/OGC/1.3/CRS84';

    public function __construct(private SentinelParcel $parcels)
    {
    }

    public function configured(): bool
    {
        return config('sentinel.enabled') && trim((string) config('sentinel.client_id')) !== ''
            && trim((string) config('sentinel.client_secret')) !== '';
    }

    public function statistics(FarmPlot $plot, string $from, string $to, int $maxCloud): array
    {
        $this->requireConfiguration();
        $parcel = $this->parcels->describe($plot);
        $key = $this->key($plot, ['statistics', $parcel['geometry'], $from, $to, $maxCloud]);

        return $this->cached($key, function () use ($parcel, $from, $to, $maxCloud): array {
            $response = $this->post(self::STATISTICS_URL, [
                'input' => $this->input($parcel, $maxCloud),
                'aggregation' => [
                    'timeRange' => $this->period($from, $to), 'aggregationInterval' => ['of' => 'P1D'],
                    'resx' => $parcel['resx'], 'resy' => $parcel['resy'],
                    'evalscript' => $this->statisticsScript(),
                ],
            ]);
            $payload = $response->json();
            if (! is_array($payload) || ($payload['status'] ?? '') !== 'OK' || ! is_array($payload['data'] ?? null)
                || count($payload['data']) > 31) {
                throw new SatelliteUnavailable('The satellite provider returned incomplete statistics. Please try again.', 502);
            }
            $rows = [];
            foreach ($payload['data'] as $entry) {
                if (! is_array($entry) || isset($entry['error']) || ! is_array($entry['outputs'] ?? null)) {
                    throw new SatelliteUnavailable('Some observation days could not be processed. Please retry a shorter period.', 502);
                }
                $date = substr((string) ($entry['interval']['from'] ?? ''), 0, 10);
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
                    || $date < $from || $date > $to || isset($rows[$date])) {
                    throw new SatelliteUnavailable('The satellite provider returned an invalid observation period.', 502);
                }
                $stats = $entry['outputs']['ndvi']['bands']['B0']['stats'] ?? null;
                if (! is_array($stats) || ! is_numeric($stats['sampleCount'] ?? null) || ! is_numeric($stats['noDataCount'] ?? null)) {
                    throw new SatelliteUnavailable('The satellite provider returned incomplete pixel counts.', 502);
                }
                $samples = (float) $stats['sampleCount'];
                $masked = (float) $stats['noDataCount'];
                if (! is_finite($samples) || ! is_finite($masked) || $samples !== floor($samples) || $masked !== floor($masked)
                    || $samples < 0 || $samples > 300000 || $masked < 0 || $masked > $samples) {
                    throw new SatelliteUnavailable('The satellite provider returned invalid pixel counts.', 502);
                }
                $valid = (int) ($samples - $masked);
                $values = [];
                foreach (['mean', 'min', 'max'] as $metric) {
                    $value = $stats[$metric] ?? null;
                    if ($valid && (! is_numeric($value) || ! is_finite((float) $value) || $value < -1.001 || $value > 1.001)) {
                        throw new SatelliteUnavailable('The satellite provider returned invalid vegetation values.', 502);
                    }
                    $values[$metric] = $valid ? round((float) $value, 4) : null;
                }
                if ($valid && ($values['min'] > $values['mean'] || $values['mean'] > $values['max'])) {
                    throw new SatelliteUnavailable('The satellite provider returned inconsistent vegetation values.', 502);
                }
                $rows[$date] = ['date' => $date, 'valid_pixels' => $valid, 'status' => $valid ? 'available' : 'no_data'] + $values;
            }
            ksort($rows);

            return ['observations' => array_values($rows), 'source' => 'Copernicus Sentinel-2 Level-2A',
                'method' => 'NDVI (B08 − B04) / (B08 + B04); daily UTC least-cloud mosaics; SCL mask; approximately 10 m geographic output grid.',
                'generated_at' => now()->utc()->toIso8601String(), 'scene_cloud_limit' => $maxCloud];
        });
    }

    public function image(FarmPlot $plot, string $date, int $maxCloud, string $layer): string
    {
        $this->requireConfiguration();
        $parcel = $this->parcels->describe($plot);
        $key = $this->key($plot, ['image', $parcel['geometry'], $date, $maxCloud, $layer]);

        return $this->cached($key, function () use ($parcel, $date, $maxCloud, $layer): string {
            $input = $this->input($parcel, $maxCloud);
            $input['data'][0]['dataFilter']['timeRange'] = $this->period($date, $date);
            $response = $this->post(self::PROCESS_URL, [
                'input' => $input,
                'output' => ['width' => $parcel['width'], 'height' => $parcel['height'],
                    'responses' => [['identifier' => 'default', 'format' => ['type' => 'image/png']]]],
                'evalscript' => $this->imageScript($layer),
            ], 'image/png');
            $body = $response->body();
            $info = @getimagesizefromstring($body);
            if (! str_starts_with($body, "\x89PNG\r\n\x1a\n") || ! is_array($info) || $info[2] !== IMAGETYPE_PNG
                || $info[0] !== $parcel['width'] || $info[1] !== $parcel['height']) {
                throw new SatelliteUnavailable('The satellite provider did not return a valid parcel image.', 502);
            }

            return $body;
        });
    }

    private function requireConfiguration(): void
    {
        if (! $this->configured()) {
            throw new SatelliteUnavailable('Satellite access is not configured. Ask the system administrator to enable Copernicus access.');
        }
    }

    private function input(array $parcel, int $maxCloud): array
    {
        return ['bounds' => ['geometry' => $parcel['geometry'], 'bbox' => $parcel['bbox'], 'properties' => ['crs' => self::CRS]],
            'data' => [['type' => 'sentinel-2-l2a', 'dataFilter' => ['maxCloudCoverage' => $maxCloud, 'mosaickingOrder' => 'leastCC'],
                'processing' => ['upsampling' => 'NEAREST', 'downsampling' => 'NEAREST', 'harmonizeValues' => true]]]];
    }

    private function period(string $from, string $to): array
    {
        return ['from' => $from.'T00:00:00Z', 'to' => CarbonImmutable::parse($to, 'UTC')->addDay()->format('Y-m-d\T00:00:00\Z')];
    }

    private function key(FarmPlot $plot, array $parameters): string
    {
        return 'sentinel:v1:'.$plot->id.':'.hash('sha256', json_encode([$plot->farmer_id, $plot->farmer?->municipality_id, $parameters, $this->credentialKey()], JSON_THROW_ON_ERROR));
    }

    private function credentialKey(): string
    {
        return hash('sha256', config('sentinel.client_id').'|'.config('sentinel.client_secret'));
    }

    private function cached(string $key, callable $make): mixed
    {
        $value = Cache::get($key);
        if ($value !== null) {
            return $value;
        }
        try {
            return Cache::lock($key.':lock', 70)->block(2, function () use ($key, $make) {
                $value = Cache::get($key);
                if ($value === null) {
                    $value = $make();
                    Cache::put($key, $value, now()->addMinutes((int) config('sentinel.cache_minutes', 720)));
                }

                return $value;
            });
        } catch (LockTimeoutException $exception) {
            throw new SatelliteUnavailable('This satellite result is being prepared. Retry in a moment.', 503);
        }
    }

    private function reserveRequest(): void
    {
        try {
            Cache::lock('sentinel:budget:lock', 10)->block(2, function (): void {
                $keys = ['day' => 'sentinel:requests:'.now()->utc()->format('Y-m-d'), 'month' => 'sentinel:requests:'.now()->utc()->format('Y-m')];
                $limits = ['day' => max(0, (int) config('sentinel.daily_requests', 100)), 'month' => max(0, (int) config('sentinel.monthly_requests', 2000))];
                foreach ($keys as $period => $key) {
                    if ((int) Cache::get($key, 0) >= $limits[$period]) {
                        throw new SatelliteUnavailable('The local satellite request allowance has been reached. Ask the administrator to review usage.', 429);
                    }
                }
                foreach ($keys as $period => $key) {
                    Cache::put($key, (int) Cache::get($key, 0) + 1, $period === 'day' ? now()->utc()->endOfDay() : now()->utc()->endOfMonth());
                }
            });
        } catch (LockTimeoutException $exception) {
            throw new SatelliteUnavailable('Satellite processing is busy. Retry shortly.');
        }
    }

    private function token(): string
    {
        $key = 'sentinel:oauth:'.$this->credentialKey();
        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }
        try {
            return Cache::lock($key.':lock', 20)->block(2, function () use ($key): string {
                $cached = Cache::get($key);
                if (is_string($cached)) {
                    return $cached;
                }
                $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(12)
                    ->withOptions(['allow_redirects' => false])->post(self::TOKEN_URL, [
                        'grant_type' => 'client_credentials', 'client_id' => config('sentinel.client_id'), 'client_secret' => config('sentinel.client_secret'),
                    ]);
                $token = $response->json('access_token');
                $expires = $response->json('expires_in');
                if (! $response->successful() || ! is_string($token) || strlen($token) < 10 || strlen($token) > 16000 || ! is_numeric($expires) || $expires <= 60) {
                    throw new SatelliteUnavailable('Copernicus authentication failed. Ask the administrator to check the private OAuth configuration.');
                }
                Cache::put($key, $token, now()->addSeconds(min(86400, (int) $expires - 60)));

                return $token;
            });
        } catch (SatelliteUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // Never log an OAuth exception/request body, which can contain credentials.
            throw new SatelliteUnavailable('Copernicus authentication is temporarily unavailable.');
        }
    }

    private function post(string $url, array $payload, string $accept = 'application/json'): Response
    {
        $token = $this->token();
        $this->reserveRequest();
        try {
            $response = Http::withToken($token)->accept($accept)->connectTimeout(5)->timeout(25)
                ->withOptions(['allow_redirects' => false, 'on_headers' => static function ($response): void {
                    if ((int) $response->getHeaderLine('Content-Length') > 3000000) {
                        throw new \RuntimeException('Response exceeds limit.');
                    }
                }, 'progress' => static function ($total, $downloaded): void {
                    if ($downloaded > 3000000) {
                        throw new \RuntimeException('Response exceeds limit.');
                    }
                }])->post($url, $payload);
        } catch (Throwable $exception) {
            throw new SatelliteUnavailable('The satellite provider could not be reached. Retry later.', 502);
        }
        if ($response->status() === 401 || $response->status() === 403) {
            Cache::forget('sentinel:oauth:'.$this->credentialKey());
            throw new SatelliteUnavailable('Copernicus access was refused. Ask the administrator to check account access.');
        }
        if ($response->status() === 429) {
            throw new SatelliteUnavailable('Copernicus usage limits were reached. Retry later or ask the administrator to review the account quota.', 429);
        }
        if (! $response->successful() || strlen($response->body()) > 3000000) {
            throw new SatelliteUnavailable('The satellite provider could not complete this request. Retry a shorter period.', 502);
        }

        return $response;
    }

    private function maskScript(): string
    {
        return 'function clear(s) { return s.dataMask === 1 && [4,5,6].indexOf(s.SCL) !== -1 && s.B08 + s.B04 > 0; }';
    }

    public function statisticsScript(): string
    {
        return '//VERSION=3'."\n".$this->maskScript()."\n".<<<'JS'
function setup() { return {input: [{bands: ["B04","B08","SCL","dataMask"]}], output: [{id:"ndvi",bands:1,sampleType:"FLOAT32"},{id:"dataMask",bands:1}]}; }
function evaluatePixel(s) { var ok = clear(s); return {ndvi:[ok ? (s.B08-s.B04)/(s.B08+s.B04) : 0],dataMask:[ok ? 1 : 0]}; }
JS;
    }

    public function imageScript(string $layer): string
    {
        $pixel = $layer === 'true-color'
            ? 'return [Math.min(1,2.5*s.B04),Math.min(1,2.5*s.B03),Math.min(1,2.5*s.B02),ok ? 1 : 0];'
            : 'var v = ok ? (s.B08-s.B04)/(s.B08+s.B04) : 0; var rgb = colorBlend(v, [-1,0,0.2,0.5,1], [[0.2,0.3,0.65],[0.65,0.48,0.3],[0.95,0.83,0.3],[0.5,0.72,0.25],[0.06,0.32,0.15]]); return [rgb[0],rgb[1],rgb[2],ok ? 1 : 0];';

        return '//VERSION=3'."\n".$this->maskScript()."\n".
            'function setup() { return {input:[{bands:["B02","B03","B04","B08","SCL","dataMask"]}],output:{bands:4,sampleType:"AUTO"}}; }'."\n".
            'function evaluatePixel(s) { var ok = clear(s); '.$pixel.' }';
    }
}
