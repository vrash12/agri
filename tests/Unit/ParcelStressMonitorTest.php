<?php

namespace Tests\Unit;

use App\Modules\Climate\Application\ClimateAutomaticMonitor;
use App\Modules\Climate\Application\ParcelStressMonitor;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParcelStressMonitorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19T10:00:00Z');
        CarbonImmutable::setTestNow('2026-10-19T10:00:00Z');
        config(['app.display_timezone' => 'Asia/Manila']);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_missing_evidence_is_not_normal_or_an_official_drought_level(): void
    {
        $result = $this->monitor(null);
        $this->assertSame('waiting', $result['status']);
        $this->assertNotEmpty($result['missing']);
        $this->assertSame([], $result['signals']);
        $this->assertCount(4, $result['satellite']);
        $this->assertSame(['unavailable'], array_values(array_unique(array_column($result['satellite'], 'status'))));
        $this->assertArrayNotHasKey('risk_level', $result);
        $this->assertArrayNotHasKey('affected', $result);
        Http::assertNothingSent();
    }

    public function test_current_field_concerns_combine_with_a_comparable_decline_and_crop_stage(): void
    {
        $result = $this->monitor($this->assessment(['crop_stage' => 'flowering', 'water_availability' => 'limited']),
            ['ndvi' => [$this->picture('2026-10-10', .7), $this->picture('2026-10-18', .5)]]);
        $keys = array_column($result['signals'], 'key');
        $this->assertSame('attention', $result['status']);
        $this->assertContains('water_supply', $keys);
        $this->assertContains('flowering', $keys);
        $this->assertContains('ndvi_decline', $keys);
        $this->assertSame(-.2, $result['satellite'][0]['delta']);
        $this->assertStringContainsString('cannot identify the cause', $result['signals'][2]['detail']);
    }

    public function test_decline_alone_does_not_diagnose_stress_and_mature_or_harvested_crops_explain_change(): void
    {
        $pictures = ['ndvi' => [$this->picture('2026-10-10', .7), $this->picture('2026-10-18', .4)]];
        $result = $this->monitor($this->assessment(), $pictures);
        $this->assertSame('monitoring', $result['status']);
        $this->assertSame([], $result['signals']);
        foreach (['mature', 'harvested', 'not_planted'] as $stage) {
            $result = $this->monitor($this->assessment(['crop_stage' => $stage, 'water_availability' => 'unavailable']), $pictures);
            $this->assertNotContains('ndvi_decline', array_column($result['signals'], 'key'));
            $this->assertNotContains('water_supply', array_column($result['signals'], 'key'));
            $this->assertStringContainsString('crop stage can explain', $result['satellite'][0]['detail']);
        }
    }

    public function test_old_field_report_and_changed_boundaries_do_not_repeat_a_current_stress_claim(): void
    {
        foreach ([['observed_on' => '2026-09-01'], ['observed_on' => '2026-02-30'], ['context_current' => false], ['observed_on' => '2026-10-20']] as $change) {
            $result = $this->monitor($this->assessment(array_replace(['field_condition' => 'wilting', 'water_availability' => 'unavailable'], $change)));
            $this->assertSame('waiting', $result['status']);
            $this->assertSame([], $result['signals']);
            $this->assertNull($result['observed_on']);
        }
    }

    public function test_water_evidence_needs_its_own_source_and_fresh_date(): void
    {
        foreach ([['water_source' => null], ['water_observed_on' => '2026-09-01'], ['water_observed_on' => '2026-10-20']] as $change) {
            $result = $this->monitor($this->assessment(array_replace(['water_availability' => 'unavailable'], $change)));
            $this->assertNotContains('water_supply', array_column($result['signals'], 'key'));
            $this->assertContains('A recent, sourced water-supply check', $result['missing']);
        }
    }

    public function test_satellite_comparison_rejects_different_settings_small_gaps_stale_and_preplant_pictures(): void
    {
        foreach ([
            [$this->picture('2026-10-10', .7, ['series_key' => 'other']), $this->picture('2026-10-18', .5)],
            [$this->picture('2026-10-17', .7), $this->picture('2026-10-18', .5)],
            [$this->picture('2026-09-01', .7), $this->picture('2026-10-18', .5)],
        ] as $rows) {
            $result = $this->monitor($this->assessment(['water_availability' => 'limited']), ['ndvi' => $rows]);
            $this->assertNull($result['satellite'][0]['delta']);
            $this->assertNotContains('ndvi_decline', array_column($result['signals'], 'key'));
        }
        $old = $this->monitor($this->assessment(), ['ndvi' => [$this->picture('2026-09-25', .7), $this->picture('2026-10-01', .5)]]);
        $this->assertSame('stale', $old['satellite'][0]['status']);
        $this->assertNull($old['satellite'][0]['delta']);
        $preplant = $this->monitor($this->assessment(['planted_on' => '2026-10-15']),
            ['ndvi' => [$this->picture('2026-10-10', .7), $this->picture('2026-10-18', .5)]]);
        $this->assertSame('single', $preplant['satellite'][0]['status']);
    }

    public function test_all_cloud_invalid_and_future_pictures_are_unavailable_while_raw_evi_values_survive(): void
    {
        $bad = [$this->picture('2026-10-18', .5, ['valid_pixels' => 0]), $this->picture('2026-10-20', .5),
            $this->picture('2026-02-30', .5), $this->picture('2026-10-18', INF), $this->picture('2026-10-18', 1.1),
            $this->picture('2026-10-18', .5, ['series_key' => '']), $this->picture('2026-10-18', .5, ['context_current' => false])];
        $result = $this->monitor($this->assessment(), ['ndvi' => $bad,
            'evi' => [$this->picture('2026-10-10', 1.2), $this->picture('2026-10-18', 1.4)]]);
        $this->assertSame('unavailable', $result['satellite'][0]['status']);
        $this->assertSame('comparable', $result['satellite'][3]['status']);
        $this->assertSame(1.4, $result['satellite'][3]['observations'][1]['mean']);
        $this->assertSame(.2, $result['satellite'][3]['delta']);
    }

    public function test_no_planting_date_prevents_a_crop_cycle_stress_claim_and_duplicates_do_not_add_comparisons(): void
    {
        $result = $this->monitor($this->assessment(['planted_on' => null, 'water_availability' => 'limited']),
            ['ndvi' => [$this->picture('2026-10-10', .7), $this->picture('2026-10-18', .4)]]);
        $this->assertNotContains('ndvi_decline', array_column($result['signals'], 'key'));
        $this->assertContains('Planting date for matching satellite readings to this crop', $result['missing']);
        $duplicate = $this->monitor($this->assessment(), ['ndvi' => [$this->picture('2026-10-18', .7), $this->picture('2026-10-18', .5)]]);
        $this->assertCount(1, $duplicate['satellite'][0]['observations']);
        $this->assertNull($duplicate['satellite'][0]['delta']);
    }

    public function test_municipal_weather_is_labeled_and_rechecked_when_old_summary_is_reused(): void
    {
        $weather = ['status' => 'available', 'value' => 0, 'context_current' => true,
            'period_start' => '2026-10-19', 'period_end' => '2026-10-25', 'fetched_at' => '2026-10-19T09:00:00Z',
            'metadata' => ['coverage_complete' => true, 'forecast_days' => 7, 'rain_days' => 7, 'temperature_days' => 7, 'temperature_max_c' => 36]];
        $monitoring = app(ClimateAutomaticMonitor::class)->summarize(['open_meteo' => $weather]);
        $result = app(ParcelStressMonitor::class)->summarize(null, $monitoring, []);
        $this->assertSame('attention', $result['status']);
        $this->assertSame('Municipality', $result['signals'][0]['scope']);
        $this->assertStringContainsString('verify this parcel', $result['signals'][0]['detail']);
        $monitoring['sources']['open_meteo']['reading']['fetched_at'] = '2026-10-17T09:00:00Z';
        $result = app(ParcelStressMonitor::class)->summarize(null, $monitoring, []);
        $this->assertSame('waiting', $result['status']);
        $this->assertSame([], $result['signals']);
    }

    public function test_an_annual_crop_over_a_year_old_needs_stage_confirmation_but_cross_year_crops_are_allowed(): void
    {
        $old = $this->monitor($this->assessment(['planted_on' => '2025-06-01', 'water_availability' => 'limited']),
            ['ndvi' => [$this->picture('2026-10-10', .7), $this->picture('2026-10-18', .4)]]);
        $this->assertSame('Stage not confirmed', $old['stage_label']);
        $this->assertNotContains('ndvi_decline', array_column($old['signals'], 'key'));
        $this->assertNotContains('water_supply', array_column($old['signals'], 'key'));
        $crossYear = $this->monitor($this->assessment(['crop_year' => 2025, 'planted_on' => '2025-12-30']));
        $this->assertSame('Leaf growth', $crossYear['stage_label']);
    }

    public function test_comparison_overflow_and_non_integer_pixel_counts_cannot_create_a_trend(): void
    {
        $result = $this->monitor($this->assessment(), ['ndvi' => [$this->picture('2026-10-18', .5, ['valid_pixels' => 1.5])],
            'evi' => [$this->picture('2026-10-10', -PHP_FLOAT_MAX), $this->picture('2026-10-18', PHP_FLOAT_MAX)]]);
        $this->assertSame('unavailable', $result['satellite'][0]['status']);
        $this->assertNull($result['satellite'][3]['delta']);
        $this->assertNotFalse(json_encode($result));
    }

    public function test_inspection_due_and_completion_are_separate_from_crop_diagnosis(): void
    {
        $result = $this->monitor($this->assessment(['next_inspection_on' => '2026-10-19', 'follow_up_status' => 'open']));
        $this->assertTrue($result['follow_up']['overdue']);
        $this->assertContains('inspection_due', array_column($result['signals'], 'key'));
        $done = $this->monitor($this->assessment(['next_inspection_on' => '2026-10-18', 'follow_up_status' => 'completed']));
        $this->assertFalse($done['follow_up']['overdue']);
        $this->assertNotContains('inspection_due', array_column($done['signals'], 'key'));
    }

    private function monitor(?array $assessment, array $pictures = []): array
    {
        return app(ParcelStressMonitor::class)->summarize($assessment, [], $pictures);
    }

    private function picture(string $date, float $mean, array $changes = []): array
    {
        return array_replace(['date' => $date, 'mean' => $mean, 'valid_pixels' => 80, 'series_key' => 'verified-context-settings',
            'checked_at' => '2026-10-19T09:00:00Z', 'is_stale' => false], $changes);
    }

    private function assessment(array $changes = []): array
    {
        return array_replace(['crop' => 'rice', 'observed_on' => '2026-10-19', 'context_current' => true,
            'crop_stage' => 'vegetative', 'planted_on' => '2026-09-01', 'field_condition' => 'normal',
            'water_availability' => 'reliable', 'water_source' => 'Field water check', 'water_observed_on' => '2026-10-18'], $changes);
    }
}
