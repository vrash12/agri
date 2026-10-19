<?php

namespace Tests\Unit;

use App\Modules\Climate\Application\ParcelSuitability;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParcelSuitabilityTest extends TestCase
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

    public function test_complete_dated_reference_matches_are_a_limited_screen_without_a_score(): void
    {
        $result = app(ParcelSuitability::class)->evaluate($this->assessment());
        $this->assertSame('potentially_suitable', $result['status']);
        $this->assertSame([], $result['missing']);
        $this->assertSame([], $result['review']);
        $this->assertCount(6, $result['factors']);
        $this->assertSame(['matches'], array_values(array_unique(array_column($result['factors'], 'status'))));
        $this->assertArrayNotHasKey('score', $result);
        $this->assertStringContainsString('not a yield forecast', $result['limits']);
        Http::assertNothingSent();
    }

    public function test_other_crop_profiles_reuse_real_evidence_and_keep_their_own_ranges(): void
    {
        foreach (['corn', 'mungbean'] as $crop) {
            $result = app(ParcelSuitability::class)->evaluate($this->assessment([
                'crop' => $crop, 'soil_ph' => 6, 'soil_texture' => 'loamy', 'drainage' => 'good',
            ]));
            $this->assertSame('potentially_suitable', $result['status'], $crop);
        }
        $result = app(ParcelSuitability::class)->evaluate($this->assessment(['crop' => 'mungbean', 'soil_ph' => 7]));
        $this->assertContains('Soil acidity (pH)', $result['review']);
    }

    public function test_missing_or_changed_boundary_data_never_becomes_a_favorable_screen(): void
    {
        $missing = app(ParcelSuitability::class)->evaluate([]);
        $this->assertSame('insufficient_evidence', $missing['status']);
        $this->assertSame(['missing'], array_values(array_unique(array_column($missing['factors'], 'status'))));
        $changed = app(ParcelSuitability::class)->evaluate($this->assessment(['context_current' => false]));
        $this->assertSame('insufficient_evidence', $changed['status']);
        $this->assertSame(['missing'], array_values(array_unique(array_column($changed['factors'], 'status'))));
    }

    #[DataProvider('invalidEvidence')]
    public function test_malformed_unsourced_or_stale_evidence_remains_missing(array $changes, string $factor): void
    {
        $result = app(ParcelSuitability::class)->evaluate($this->assessment($changes));
        $factors = array_column($result['factors'], null, 'key');
        $this->assertSame('insufficient_evidence', $result['status']);
        $this->assertSame('missing', $factors[$factor]['status']);
        $this->assertNull($factors[$factor]['value']);
    }

    public static function invalidEvidence(): iterable
    {
        yield 'NaN' => [['soil_ph' => NAN], 'soil_ph'];
        yield 'infinity' => [['soil_ph' => INF], 'soil_ph'];
        yield 'boolean is not measurement' => [['soil_ph' => true], 'soil_ph'];
        yield 'empty is not zero' => [['soil_ph' => ''], 'soil_ph'];
        yield 'outside physical pH' => [['soil_ph' => 15], 'soil_ph'];
        yield 'future soil' => [['soil_observed_on' => '2026-10-20'], 'soil_ph'];
        yield 'rolled invalid calendar date' => [['soil_observed_on' => '2026-02-30'], 'soil_ph'];
        yield 'relative date' => [['soil_observed_on' => 'yesterday'], 'soil_ph'];
        yield 'soil source missing' => [['soil_source' => '  '], 'soil_ph'];
        yield 'old soil' => [['soil_observed_on' => '2023-10-01'], 'soil_ph'];
        yield 'old assessment' => [['observed_on' => '2024-10-19'], 'soil_ph'];
        yield 'future assessment' => [['observed_on' => '2026-10-20'], 'soil_ph'];
        yield 'negative slope' => [['slope_percent' => -1], 'slope_percent'];
        yield 'old water' => [['water_observed_on' => '2026-10-01'], 'water_availability'];
        yield 'unknown water' => [['water_availability' => 'unknown'], 'water_availability'];
        yield 'week forecast not growing climate' => [['climate_period_start' => '2026-10-10', 'climate_period_end' => '2026-10-16'], 'growing_temperature_c'];
        yield 'reversed climate' => [['climate_period_start' => '2026-10-19', 'climate_period_end' => '2026-09-30'], 'growing_temperature_c'];
        yield 'climate source missing' => [['climate_source' => null], 'growing_temperature_c'];
        yield 'future climate' => [['climate_period_end' => '2026-11-01'], 'growing_temperature_c'];
        yield 'very long climate' => [['climate_period_start' => '2024-01-01'], 'growing_temperature_c'];
        yield 'unsupported crop' => [['crop' => 'vegetables'], 'soil_ph'];
    }

    public function test_true_zero_values_stay_evidence_and_concerns_remain_visible_when_other_evidence_is_missing(): void
    {
        $result = app(ParcelSuitability::class)->evaluate($this->assessment([
            'soil_ph' => 0, 'slope_percent' => 0, 'water_availability' => 'unavailable', 'soil_texture' => null,
        ]));
        $factors = array_column($result['factors'], null, 'key');
        $this->assertSame(0.0, $factors['soil_ph']['value']);
        $this->assertSame('review', $factors['soil_ph']['status']);
        $this->assertSame('matches', $factors['slope_percent']['status']);
        $this->assertSame('insufficient_evidence', $result['status']);
        $this->assertContains('Verified water availability', $result['review']);
    }

    public function test_slope_is_layout_review_and_rainfall_never_supplants_verified_water(): void
    {
        $result = app(ParcelSuitability::class)->evaluate($this->assessment([
            'slope_percent' => 2, 'growing_rainfall_mm' => 2000, 'water_availability' => 'limited',
        ]));
        $factors = array_column($result['factors'], null, 'key');
        $this->assertSame('review_needed', $result['status']);
        $this->assertSame('review', $factors['slope_percent']['status']);
        $this->assertStringContainsString('does not by itself rule out', $factors['slope_percent']['detail']);
        $this->assertSame('review', $factors['water_availability']['status']);
        $this->assertSame(2000.0, $result['rainfall_context']['value_mm']);
        $this->assertStringContainsString('does not prove enough water', $result['rainfall_context']['detail']);
    }

    private function assessment(array $changes = []): array
    {
        return array_replace(['crop' => 'rice', 'context_current' => true, 'observed_on' => '2026-10-19',
            'soil_ph' => 6.2, 'soil_texture' => 'clayey', 'drainage' => 'poor', 'soil_source' => 'Soil test and field inspection',
            'soil_observed_on' => '2026-09-01', 'slope_percent' => 1.5, 'terrain_source' => 'Parcel survey',
            'terrain_observed_on' => '2026-08-01', 'growing_temperature_c' => 27, 'growing_rainfall_mm' => 600,
            'climate_source' => 'Observed growing-period station summary', 'climate_period_start' => '2026-06-01',
            'climate_period_end' => '2026-09-30', 'water_availability' => 'reliable', 'water_source' => 'Site supply inspection',
            'water_observed_on' => '2026-10-18'], $changes);
    }
}
