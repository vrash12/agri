<?php

namespace Tests\Unit;

use App\Modules\Climate\Application\ClimateRefreshStatus;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ClimateRefreshStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->at('2026-10-19T10:00:00+00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_no_status_never_implies_a_scheduler_is_installed_or_running(): void
    {
        $status = app(ClimateRefreshStatus::class);
        $this->assertNull($status->read('open-meteo'));
        $this->assertNull($status->read('chirps'));
        $this->assertNull($status->read('sentinel2'));
    }

    public function test_start_and_finish_expose_utc_timestamps_and_safe_counts_only(): void
    {
        $status = app(ClimateRefreshStatus::class);
        $status->started('open-meteo');
        $running = $status->read('open-meteo');
        $this->assertSame('running', $running['state']);
        $this->assertSame('2026-10-19T10:00:00+00:00', $running['started_at']);
        $this->assertNull($running['finished_at']);
        $this->assertArrayNotHasKey('counts', $running);

        $this->at('2026-10-19T10:01:30+00:00');
        $status->finished('open-meteo', ['open_meteo' => 2, 'chirps' => -5, 'skipped' => '3',
            'url' => 'https://private.example/path', 'municipality_id' => 12, 'message' => 'private debug text', 'token' => 'sensitive']);
        $finished = $status->read('open-meteo');
        $this->assertSame('finished', $finished['state']);
        $this->assertSame('2026-10-19T10:00:00+00:00', $finished['started_at']);
        $this->assertSame('2026-10-19T10:01:30+00:00', $finished['finished_at']);
        $this->assertSame(['open_meteo' => 2, 'chirps' => 0, 'sentinel2' => 0, 'skipped' => 3, 'failed' => 0], $finished['counts']);
        $encoded = json_encode($finished);
        foreach (['private.example', 'municipality_id', 'private debug text', 'sensitive'] as $private) {
            $this->assertStringNotContainsString($private, $encoded);
        }
    }

    public function test_failure_counts_are_explicit_and_a_new_run_keeps_the_last_completion_time(): void
    {
        $status = app(ClimateRefreshStatus::class);
        $status->started('chirps');
        $status->finished('chirps', ['chirps' => 2, 'failed' => 1]);
        $this->assertSame('needs_review', $status->read('chirps')['state']);
        $this->assertSame(1, $status->read('chirps')['counts']['failed']);
        $this->at('2026-10-20T10:00:00+00:00');
        $status->started('chirps');
        $running = $status->read('chirps');
        $this->assertSame('running', $running['state']);
        $this->assertSame('2026-10-20T10:00:00+00:00', $running['started_at']);
        $this->assertSame('2026-10-19T10:00:00+00:00', $running['finished_at']);
        $this->assertArrayNotHasKey('counts', $running, 'Old counts cannot appear to belong to a new running batch');
    }

    public function test_unknown_provider_names_cannot_create_arbitrary_cache_entries(): void
    {
        $status = app(ClimateRefreshStatus::class);
        foreach (['unknown', '../secrets', 'open_meteo', 'OPEN-METEO', ''] as $provider) {
            $status->started($provider);
            $status->finished($provider, ['failed' => 1]);
            $this->assertNull($status->read($provider));
            $this->assertNull(Cache::get('climate:refresh-status:v1:'.$provider));
        }
    }

    public function test_each_provider_has_independent_state_and_status_expires_after_seven_days(): void
    {
        $status = app(ClimateRefreshStatus::class);
        $status->finished('open-meteo', ['open_meteo' => 1]);
        $status->started('chirps');
        $this->assertSame('finished', $status->read('open-meteo')['state']);
        $this->assertSame('running', $status->read('chirps')['state']);
        $this->assertNull($status->read('sentinel2'));
        $this->at('2026-10-26T09:59:59+00:00');
        $this->assertNotNull($status->read('open-meteo'));
        $this->at('2026-10-26T10:00:01+00:00');
        $this->assertNull($status->read('open-meteo'));
        $this->assertNull($status->read('chirps'));
    }

    private function at(string $date): void
    {
        $now = CarbonImmutable::parse($date);
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }
}
