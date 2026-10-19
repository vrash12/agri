<?php

namespace App\Modules\Climate\Application;

use App\Support\LocalTime;
use Carbon\CarbonImmutable;

/** A transparent comparison with broad crop references, not a planting prescription. */
final class ParcelSuitability
{
    public function evaluate(array $assessment): array
    {
        $crop = is_string($assessment['crop'] ?? null) ? $assessment['crop'] : '';
        $profile = config('agronomy.profiles.'.$crop);
        $supported = in_array($crop, ['rice', 'corn', 'mungbean'], true) && is_array($profile);
        $current = ($assessment['context_current'] ?? true) === true
            && self::freshDate($assessment['observed_on'] ?? null, 'assessment') !== null;
        $soil = $current && $this->group($assessment, 'soil');
        $terrain = $current && $this->group($assessment, 'terrain');
        $water = $current && $this->group($assessment, 'water');
        $climate = $current && $this->climate($assessment);
        $factors = [];
        $source = $supported ? $profile['source'] : null;

        $ph = self::number($assessment['soil_ph'] ?? null, 0, 14);
        $factors[] = $this->range('soil_ph', 'Soil acidity (pH)', $soil && $supported ? $ph : null,
            $supported ? $profile['ph'] : [0, 0], '', $assessment, 'soil', $source);

        $texture = self::choice($assessment['soil_texture'] ?? null, ['sandy', 'loamy', 'clayey']);
        $textureMatch = $supported && in_array($texture, $profile['textures'], true);
        $factors[] = $this->factor('soil_texture', 'Soil texture', $soil && $supported ? $texture : null,
            $textureMatch, '', $textureMatch ? 'The recorded soil texture matches the broad reference.'
                : ($crop === 'rice' ? 'Check water retention. Sandy or loamy rice fields may need more water control.'
                    : 'Check soil structure, root depth and water movement with an agriculture officer.'),
            $assessment, 'soil', $source);

        $drainage = self::choice($assessment['drainage'] ?? null, ['good', 'moderate', 'poor']);
        $drainageMatch = $supported && in_array($drainage, $profile['drainage'], true);
        $factors[] = $this->factor('drainage', 'Drainage', $soil && $supported ? $drainage : null,
            $drainageMatch, '', $crop === 'rice'
                ? ($drainageMatch ? 'Slow drainage supports water retention for lowland rice. Flood depth and field drainage still need control.'
                    : 'Confirm bunds, water retention and controlled drainage for lowland rice.')
                : ($drainageMatch ? 'Good drainage matches the reference for this crop.' : 'Review drainage before planting; standing water can harm this crop.'),
            $assessment, 'soil', $source);

        $slope = self::number($assessment['slope_percent'] ?? null, 0, 100);
        $factors[] = $this->factor('slope_percent', 'Slope and irrigation layout', $terrain && $supported ? $slope : null,
            $slope !== null && $slope < 2, '%', $slope !== null && $slope < 2
                ? 'Gentle terrain matches the broad surface-irrigation reference. Confirm field leveling and drainage.'
                : 'Review terraces, runoff and the irrigation layout. This slope does not by itself rule out the crop.',
            $assessment, 'terrain', 'https://www.fao.org/4/w4347e/w4347e08.htm');

        $temperature = self::number($assessment['growing_temperature_c'] ?? null, -50, 60);
        $factors[] = $this->range('growing_temperature_c', 'Growing-period temperature', $climate && $supported ? $temperature : null,
            $supported ? $profile['temperature'] : [0, 0], '°C', $assessment, 'climate', $source);

        $availability = self::choice($assessment['water_availability'] ?? null, ['reliable', 'limited', 'unavailable']);
        $factors[] = $this->factor('water_availability', 'Verified water availability', $water && $supported ? $availability : null,
            $availability === 'reliable', '', $availability === 'reliable'
                ? 'A recent field record reports a reliable supply. Confirm that its timing, quantity and quality meet this crop’s needs.'
                : 'Confirm usable rain, stored soil moisture or irrigation before planting. A seasonal rain total alone cannot confirm supply.',
            $assessment, 'water', 'https://www.fao.org/4/s2022e/s2022e07.htm');

        $missing = array_column(array_filter($factors, fn (array $factor) => $factor['status'] === 'missing'), 'label');
        $review = array_column(array_filter($factors, fn (array $factor) => $factor['status'] === 'review'), 'label');
        if (! $supported) {
            array_unshift($missing, 'Choose Rice, Corn or Mungbean for a supported crop reference');
        }
        if (! $current) {
            array_unshift($missing, ($assessment['context_current'] ?? true) === false
                ? 'Update the assessment for the current parcel boundary' : 'A dated, current parcel assessment');
        }
        $status = $missing !== [] ? 'insufficient_evidence' : ($review !== [] ? 'review_needed' : 'potentially_suitable');
        $rain = $climate ? self::number($assessment['growing_rainfall_mm'] ?? null, 0, 100000) : null;

        return [
            'status' => $status,
            'label' => match ($status) {
                'potentially_suitable' => 'Reference conditions matched',
                'review_needed' => 'Review these conditions',
                default => 'More evidence needed',
            },
            'summary' => match ($status) {
                'potentially_suitable' => 'The recorded factors match this basic crop screen. Ask the agriculture office to confirm the variety and field management before planting.',
                'review_needed' => 'Some recorded conditions need an agriculture officer’s review. The crop may still work with suitable management.',
                default => 'Add or refresh the missing evidence before judging this parcel. Any known concerns remain listed below.',
            },
            'crop' => $supported ? $crop : null, 'crop_label' => $supported ? $profile['label'] : 'Crop not selected',
            'profile_version' => config('agronomy.profile_version'), 'factors' => $factors,
            'missing' => array_values($missing), 'review' => array_values($review),
            'rainfall_context' => ['value_mm' => $rain,
                'period_start' => $climate ? $assessment['climate_period_start'] : null,
                'period_end' => $climate ? $assessment['climate_period_end'] : null,
                'detail' => $rain === null ? 'No usable growing-period rainfall record. Add its source and dates.'
                    : 'Recorded rain for this period. Rainfall timing, runoff and irrigation must be checked separately; this total does not prove enough water.'],
            'sources' => $supported ? [
                ['label' => 'FAO Ecocrop: '.$profile['label'], 'url' => $source],
                ['label' => 'FAO surface-irrigation terrain reference', 'url' => 'https://www.fao.org/4/w4347e/w4347e08.htm'],
                ['label' => 'FAO crop water needs', 'url' => 'https://www.fao.org/4/s2022e/s2022e07.htm'],
                ['label' => 'FAO lowland rice soil and basin irrigation', 'url' => 'https://www.fao.org/4/s8684e/s8684e03.htm'],
            ] : [],
            'limits' => 'Basic screening only. Soil depth, salinity, fertility, flooding, cultivar and irrigation design still need local review. Broad references are not a yield forecast or an official land-suitability classification.',
        ];
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
            || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(LocalTime::now()->toDateString(), 'UTC');
    }

    public static function freshDate(mixed $value, string $group): ?CarbonImmutable
    {
        $date = self::date($value);
        $today = self::today();
        $maxAge = (int) config('agronomy.freshness_days.'.$group, 14);

        return $date && $date->lte($today) && $date->gte($today->subDays($maxAge)) ? $date : null;
    }

    public static function number(mixed $value, float $min, float $max): ?float
    {
        return (is_int($value) || is_float($value) || (is_string($value) && trim($value) !== ''))
            && is_numeric($value) && is_finite((float) $value) && $value >= $min && $value <= $max
                ? (float) $value : null;
    }

    public static function choice(mixed $value, array $choices): ?string
    {
        return is_string($value) && in_array($value, $choices, true) ? $value : null;
    }

    private function group(array $assessment, string $group): bool
    {
        return is_string($assessment[$group.'_source'] ?? null) && trim($assessment[$group.'_source']) !== ''
            && self::freshDate($assessment[$group.'_observed_on'] ?? null, $group) !== null
            && $assessment[$group.'_observed_on'] <= $assessment['observed_on'];
    }

    private function climate(array $assessment): bool
    {
        $start = self::date($assessment['climate_period_start'] ?? null);
        $end = self::freshDate($assessment['climate_period_end'] ?? null, 'climate');

        return is_string($assessment['climate_source'] ?? null) && trim($assessment['climate_source']) !== ''
            && $start && $end && $start->lte($end) && $end->toDateString() <= $assessment['observed_on']
            && $start->diffInDays($end) + 1 >= (int) config('agronomy.minimum_climate_period_days', 30)
            && $start->diffInDays($end) + 1 <= 366;
    }

    private function range(string $key, string $label, ?float $value, array $range, string $unit, array $assessment, string $group, ?string $source): array
    {
        $matches = $value !== null && $value >= $range[0] && $value <= $range[1];
        $reference = $range[0].'–'.$range[1].($unit !== '' ? ' '.$unit : '');

        return $this->factor($key, $label, $value, $matches, $unit, $matches
            ? 'Within the broad reference range of '.$reference.'.'
            : 'Outside the broad reference range of '.$reference.'. Review local variety and management; this is not proof the crop cannot grow.',
            $assessment, $group, $source);
    }

    private function factor(string $key, string $label, mixed $value, bool $matches, string $unit, string $detail, array $assessment, string $group, ?string $source): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $value === null ? 'missing' : ($matches ? 'matches' : 'review'),
            'value' => $value, 'unit' => $unit, 'detail' => $value === null ? 'Add a valid measurement, source and recent dates for this factor.' : $detail,
            'evidence_source' => $value === null ? null : ($assessment[$group.'_source'] ?? null),
            'source' => $source, 'observed_on' => $value === null ? null : ($assessment[$group.'_observed_on'] ?? $assessment['climate_period_end'] ?? null)];
    }
}
