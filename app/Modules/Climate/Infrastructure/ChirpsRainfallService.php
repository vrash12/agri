<?php

namespace App\Modules\Climate\Infrastructure;

use App\Models\Municipality;
use App\Platform\Observability\UsageMetrics;
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

        $key = 'climate.chirps.v2.'.(int) $municipality->getKey().'.'.hash('sha256', json_encode([
            $coordinates, config('climate.sources.chirps.endpoint'), $this->baselineYears(),
        ]));
        if (! $forceRefresh && is_array($cached = Cache::get($key))) {
            app(UsageMetrics::class)->cache('chirps', true);

            return $cached;
        }
        app(UsageMetrics::class)->cache('chirps', false);

        $lock = Cache::lock($key.':refresh-lock', 180);
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
                ? array_replace($cached, ['is_stale' => true, 'status_message' => 'A CHIRPS refresh is already running. Showing the latest cached evidence.'])
                : $this->unavailable($municipality, 'A CHIRPS refresh is already running. Try again shortly.');
        } catch (Throwable $exception) {
            report($exception);

            return is_array($cached = Cache::get($key))
                ? array_replace($cached, ['is_stale' => true, 'status_message' => 'CHIRPS is temporarily unavailable. Showing the latest cached evidence.'])
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
        // Use a completed pentad with publication time allowed, then locate
        // an actually complete daily window. Availability is never assumed.
        $publishedBy = CarbonImmutable::now('UTC')->subDays(2)->startOfDay();
        $day = $publishedBy->day;
        $end = $day >= $publishedBy->daysInMonth ? $publishedBy->endOfMonth()->startOfDay()
            : ($day >= 5 ? $publishedBy->day((int) floor(min($day, 25) / 5) * 5) : $publishedBy->subMonthNoOverflow()->endOfMonth()->startOfDay());
        $deadline = microtime(true) + 90;
        $series = $this->requestAverage($coordinates, $end->subDays(59), $end, $deadline);
        $current = $this->latestCompleteWindow($series, $end);
        if ($current === []) {
            return $this->unavailable($municipality, 'Waiting for 30 complete daily CHIRPS readings. Missing days are not counted as zero rainfall.');
        }
        $start = CarbonImmutable::parse(array_key_first($current), 'UTC');
        $end = CarbonImmutable::parse(array_key_last($current), 'UTC');

        $baselineYears = $this->baselineYears();
        $baseline = [];
        for ($year = 1; $year <= $baselineYears; $year++) {
            $to = $end->subYearsNoOverflow($year);
            $from = $to->subDays(29);
            $values = $this->requestAverage($coordinates, $from, $to, $deadline);
            if ($this->completeWindow($values, $from, $to)) {
                $baseline[] = array_sum($values);
            }
        }

        $rainfall = round(array_sum($current), 2);
        $baselineValue = count($baseline) !== $baselineYears ? null : round(array_sum($baseline) / count($baseline), 2);
        $anomaly = $baselineValue !== null && $baselineValue > 0
            ? round((($rainfall - $baselineValue) / $baselineValue) * 100, 2) : null;
        $observed = $end->toDateString();

        return [
            'available' => true,
            'is_stale' => false,
            'status_message' => $baselineValue === null ? 'Rainfall is available. The comparison is waiting for complete historical readings.' : null,
            'municipality' => ['id' => (int) $municipality->getKey(), 'name' => (string) $municipality->name],
            'provider' => 'CHIRPS via ClimateSERV',
            'provider_url' => 'https://chc.ucsb.edu/data/chirps',
            'source_url' => 'https://climateserv2.servirglobal.net/develop-api',
            'observed_on' => $observed,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'rainfall_mm' => $rainfall,
            'baseline_mm' => $baselineValue,
            'anomaly_percent' => $anomaly,
            'baseline_years' => count($baseline),
            'baseline_years_requested' => $baselineYears,
            'days_available' => count($current),
            'coverage_complete' => true,
            'reference_point' => true,
            'dataset' => 'climateserv-chirps-0',
            'fetched_at' => now()->utc()->toIso8601String(),
        ];
    }

    /** @return array<string, float> */
    private function requestAverage(array $coordinates, CarbonImmutable $start, CarbonImmutable $end, float $deadline): array
    {
        $endpoint = rtrim((string) config('climate.sources.chirps.endpoint'), '/');
        $geometry = json_encode($this->box($coordinates), JSON_THROW_ON_ERROR);
        $key = 'climate.chirps.daily.v2.'.hash('sha256', implode('|', [$endpoint, $geometry, $start->toDateString(), $end->toDateString(), '0:0:5']));
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }
        if (microtime(true) >= $deadline) {
            return [];
        }
        $timeout = min(30, max(5, (int) config('climate.sources.chirps.timeout_seconds', 20)));
        $id = Cache::get($key.':job');
        if (! is_string($id) || ! preg_match('/^[a-f0-9-]{20,80}$/i', $id)) {
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
            Cache::put($key.':job', $id, now()->addDays(7));
        }

        $pollDeadline = min($deadline, microtime(true) + min(20, max(1, (int) config('climate.sources.chirps.poll_seconds', 20))));
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
                Cache::forget($key.':job');

                return [];
            }
            usleep(400000);
        } while (microtime(true) < $pollDeadline);

        if (! isset($percent) || $percent < 100) {
            return [];
        }
        $data = Http::acceptJson()->connectTimeout(5)->timeout($timeout)->get($endpoint.'/getDataFromRequest/', ['id' => $id]);
        if (! $data->successful()) {
            return [];
        }

        $payload = $this->decode($data->body());
        Cache::forget($key.':job');
        $rows = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $values = [];
        $duplicates = [];
        $seen = [];
        foreach ($rows as $row) {
            $number = is_array($row) ? ($row['value']['avg'] ?? null) : null;
            $date = is_array($row) ? $this->rowDate($row) : null;
            if ($date === null || $date < $start->toDateString() || $date > $end->toDateString()) {
                continue;
            }
            if (isset($seen[$date]) || isset($duplicates[$date])) {
                $duplicates[$date] = true;
                unset($values[$date]);

                continue;
            }
            $seen[$date] = true;
            if (is_numeric($number) && is_finite((float) $number) && (float) $number >= 0 && (float) $number <= 2000) {
                $values[$date] = (float) $number;
            }
        }
        ksort($values);
        if ($values !== []) {
            $completeHistorical = $end->lt(CarbonImmutable::now('UTC')->subDays(60)) && $this->completeWindow($values, $start, $end);
            Cache::put($key, $values, now()->addHours($completeHistorical ? 24 * 30 : 12));
        }

        return $values;
    }

    private function rowDate(array $row): ?string
    {
        // ClimateSERV documents both a display date and epochTime. The epoch
        // avoids the conflicting day/month order in display-date examples.
        if (isset($row['epochTime']) && preg_match('/^\d{9,11}$/', (string) $row['epochTime'])) {
            return CarbonImmutable::createFromTimestampUTC((int) $row['epochTime'])->toDateString();
        }
        $date = $row['date'] ?? null;
        if (is_string($date) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return $date;
        }

        return null;
    }

    private function latestCompleteWindow(array $series, CarbonImmutable $requestedEnd): array
    {
        foreach (array_reverse(array_keys($series)) as $date) {
            $end = CarbonImmutable::parse($date, 'UTC');
            if ($end->lt($requestedEnd->subDays(30))) {
                break;
            }
            $start = $end->subDays(29);
            $window = array_filter($series, fn ($key) => $key >= $start->toDateString() && $key <= $date, ARRAY_FILTER_USE_KEY);
            if ($this->completeWindow($window, $start, $end)) {
                return $window;
            }
        }

        return [];
    }

    private function completeWindow(array $values, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        if (count($values) !== 30) {
            return false;
        }
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if (! array_key_exists($date->toDateString(), $values)) {
                return false;
            }
        }

        return true;
    }

    private function baselineYears(): int
    {
        return min(5, max(1, (int) config('climate.sources.chirps.baseline_years', 3)));
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
            'provider' => 'CHIRPS via ClimateSERV', 'provider_url' => 'https://chc.ucsb.edu/data/chirps',
            'source_url' => 'https://climateserv2.servirglobal.net/develop-api', 'observed_on' => null,
            'period_start' => null, 'period_end' => null, 'rainfall_mm' => null,
            'baseline_mm' => null, 'anomaly_percent' => null, 'baseline_years' => 0,
            'baseline_years_requested' => $this->baselineYears(), 'coverage_complete' => false,
            'reference_point' => true, 'dataset' => 'climateserv-chirps-0',
            'days_available' => 0, 'fetched_at' => null,
        ];
    }
}
