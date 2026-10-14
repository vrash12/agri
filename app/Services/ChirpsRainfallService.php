<?php

namespace App\Services;

use App\Models\Municipality;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Small ClimateSERV client for CHIRPS area-average rainfall.
 *
 * ClimateSERV is asynchronous. The service intentionally returns a bounded
 * summary rather than storing raw granules or making a request in a page load.
 */
final class ChirpsRainfallService
{
    /** @return array<string, mixed> */
    public function forMunicipality(Municipality $municipality, array $coordinates, bool $forceRefresh = false): array
    {
        if (! config('climate.sources.chirps.enabled', true) || ! $this->validCoordinates($coordinates)) {
            return $this->unavailable($municipality, 'CHIRPS rainfall evidence is not configured for this area.');
        }

        $key = 'climate.chirps.v1.'.(int) $municipality->getKey().'.'.hash('sha256', json_encode($coordinates));
        if (! $forceRefresh && is_array($cached = Cache::get($key))) {
            return $cached;
        }

        $lock = Cache::lock($key.':refresh-lock', 60);
        $locked = false;
        try {
            $locked = (bool) $lock->block(2);
            if (! $forceRefresh && is_array($cached = Cache::get($key))) {
                return $cached;
            }

            $result = $this->build($municipality, $coordinates);
            Cache::put($key, $result, now()->addHours(max(6, (int) config('climate.sources.chirps.refresh_hours', 24))));

            return $result;
        } catch (LockTimeoutException) {
            return is_array($cached = Cache::get($key))
                ? $cached + ['is_stale' => true, 'status_message' => 'A CHIRPS refresh is already running. Showing the latest cached evidence.']
                : $this->unavailable($municipality, 'A CHIRPS refresh is already running. Try again shortly.');
        } catch (Throwable $exception) {
            report($exception);

            return is_array($cached = Cache::get($key))
                ? $cached + ['is_stale' => true, 'status_message' => 'CHIRPS is temporarily unavailable. Showing the latest cached evidence.']
                : $this->unavailable($municipality, 'CHIRPS rainfall evidence is temporarily unavailable.');
        } finally {
            if ($locked) {
                $lock->release();
            }
        }
    }

    /** @return array<string, mixed> */
    private function build(Municipality $municipality, array $coordinates): array
    {
        // CHIRPS preliminary data arrives after a short delay. Leave the most
        // recent two UTC days out so an incomplete day cannot look like zero.
        $end = CarbonImmutable::now('UTC')->subDays(2)->startOfDay();
        $start = $end->subDays(29);
        $current = $this->requestAverage($coordinates, $start, $end);
        if ($current === []) {
            return $this->unavailable($municipality, 'CHIRPS has not returned a usable recent rainfall period yet.');
        }

        $baselineYears = (int) config('climate.sources.chirps.baseline_years', 3);
        $baseline = [];
        for ($year = 1; $year <= $baselineYears; $year++) {
            $from = $start->subYears($year);
            $to = $end->subYears($year);
            $values = $this->requestAverage($coordinates, $from, $to);
            if ($values !== []) {
                $baseline[] = array_sum($values);
            }
        }

        $rainfall = round(array_sum($current), 2);
        $baselineValue = $baseline === [] ? null : round(array_sum($baseline) / count($baseline), 2);
        $anomaly = $baselineValue !== null && $baselineValue > 0
            ? round((($rainfall - $baselineValue) / $baselineValue) * 100, 2) : null;
        $observed = $end->toDateString();

        return [
            'available' => true,
            'is_stale' => false,
            'status_message' => null,
            'municipality' => ['id' => (int) $municipality->getKey(), 'name' => (string) $municipality->name],
            'provider' => 'CHIRPS v3 via ClimateSERV',
            'provider_url' => 'https://chc.ucsb.edu/data/chirps3',
            'source_url' => 'https://climateserv.servirglobal.net/chirps/',
            'observed_on' => $observed,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'rainfall_mm' => $rainfall,
            'baseline_mm' => $baselineValue,
            'anomaly_percent' => $anomaly,
            'baseline_years' => count($baseline),
            'days_available' => count($current),
            'fetched_at' => now()->utc()->toIso8601String(),
        ];
    }

    /** @return array<int, float> */
    private function requestAverage(array $coordinates, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $endpoint = rtrim((string) config('climate.sources.chirps.endpoint'), '/');
        $geometry = json_encode($this->box($coordinates), JSON_THROW_ON_ERROR);
        $timeout = max(5, (int) config('climate.sources.chirps.timeout_seconds', 20));
        $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout($timeout)->post($endpoint.'/submitDataRequest/', [
            'datatype' => 0,
            'begintime' => $start->format('m/d/Y'),
            'endtime' => $end->format('m/d/Y'),
            'intervaltype' => 0,
            'operationtype' => 5,
            'geometry' => $geometry,
            'dateType_Category' => 'default',
            'isZip_CurrentDataType' => 'false',
        ]);
        if (! $response->successful()) {
            return [];
        }

        $job = $this->decode($response->body());
        $id = is_array($job) && isset($job[0]) ? (string) $job[0] : '';
        if (! preg_match('/^[a-f0-9-]{20,80}$/i', $id)) {
            return [];
        }

        $deadline = microtime(true) + max(1, (int) config('climate.sources.chirps.poll_seconds', 20));
        do {
            $progress = Http::acceptJson()->connectTimeout(5)->timeout($timeout)->get($endpoint.'/getDataRequestProgress/', ['id' => $id]);
            if (! $progress->successful()) {
                return [];
            }
            $value = $this->decode($progress->body());
            $percent = is_array($value) ? (float) ($value[0] ?? -1) : (float) $value;
            if ($percent >= 100) {
                break;
            }
            if ($percent < 0) {
                return [];
            }
            usleep(400000);
        } while (microtime(true) < $deadline);

        if (! isset($percent) || $percent < 100) {
            return [];
        }
        $data = Http::acceptJson()->connectTimeout(5)->timeout($timeout)->get($endpoint.'/getDataFromRequest/', ['id' => $id]);
        if (! $data->successful()) {
            return [];
        }

        $payload = $this->decode($data->body());
        $rows = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $values = [];
        foreach ($rows as $row) {
            $number = is_array($row) ? ($row['value']['avg'] ?? null) : null;
            if (is_numeric($number) && is_finite((float) $number) && (float) $number >= 0 && (float) $number <= 2000) {
                $values[] = (float) $number;
            }
        }

        return $values;
    }

    /** @return array<string, mixed> */
    private function box(array $coordinates): array
    {
        $lat = (float) $coordinates['latitude'];
        $lng = (float) $coordinates['longitude'];
        $delta = 0.025;

        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng - $delta, $lat - $delta], [$lng + $delta, $lat - $delta],
            [$lng + $delta, $lat + $delta], [$lng - $delta, $lat + $delta],
            [$lng - $delta, $lat - $delta],
        ]]];
    }

    private function validCoordinates(array $coordinates): bool
    {
        return is_numeric($coordinates['latitude'] ?? null) && is_numeric($coordinates['longitude'] ?? null)
            && abs((float) $coordinates['latitude']) <= 90 && abs((float) $coordinates['longitude']) <= 180;
    }

    private function decode(string $body): mixed
    {
        $decoded = json_decode($body, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return $decoded ?? (is_numeric(trim($body)) ? (float) trim($body) : null);
    }

    /** @return array<string, mixed> */
    private function unavailable(Municipality $municipality, string $message): array
    {
        return [
            'available' => false, 'is_stale' => false, 'status_message' => $message,
            'municipality' => ['id' => (int) $municipality->getKey(), 'name' => (string) $municipality->name],
            'provider' => 'CHIRPS v3 via ClimateSERV', 'provider_url' => 'https://chc.ucsb.edu/data/chirps3',
            'source_url' => 'https://climateserv.servirglobal.net/chirps/', 'observed_on' => null,
            'period_start' => null, 'period_end' => null, 'rainfall_mm' => null,
            'baseline_mm' => null, 'anomaly_percent' => null, 'baseline_years' => 0,
            'days_available' => 0, 'fetched_at' => null,
        ];
    }
}
