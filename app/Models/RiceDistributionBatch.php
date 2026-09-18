<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One printable Rice Seed Distribution Sheet.
 *
 * A batch groups assistance releases that were handed out under the same
 * programme reference and planting season. It never stores a released quantity of
 * its own: every total on the sheet is derived from the grouped release rows, so
 * the two can never disagree.
 */
class RiceDistributionBatch extends Model
{
    use HasFactory;

    public const SEASON_DRY = 'dry';

    public const SEASON_WET = 'wet';

    /**
     * Cropping seasons offered for planting and harvest headings.
     *
     * A short stable code is stored and the label is for display only, so the
     * wording on screen can change without rewriting stored rows, and so the
     * printed abbreviations below key off the same value. The forms render the
     * label; nothing persists it.
     */
    public const SEASONS = [
        self::SEASON_DRY => 'Dry season',
        self::SEASON_WET => 'Wet season',
    ];

    /**
     * Abbreviations used for the printed worksheet band, e.g. "2025 DS".
     */
    public const SEASON_ABBREVIATIONS = [
        self::SEASON_DRY => 'DS',
        self::SEASON_WET => 'WS',
    ];

    protected $table = 'rice_distribution_batches';

    protected $fillable = [
        'municipality_id',
        'reference',
        'planting_season',
        'planting_year',
        'harvest_season',
        'harvest_year',
        'default_seed_bag_kg',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'planting_year' => 'integer',
        'harvest_year' => 'integer',
        'default_seed_bag_kg' => 'decimal:2',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(RiceSeedDistribution::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Accept the abbreviation, the key, or the full label a form may submit and
     * return the stored key. Anything unrecognised returns null so an unknown
     * season is never silently stored as "dry".
     */
    public static function normalizeSeason(mixed $season): ?string
    {
        if (! is_string($season) && ! is_numeric($season)) {
            return null;
        }

        $candidate = mb_strtolower(trim((string) $season));

        if ($candidate === '') {
            return null;
        }

        foreach (self::SEASONS as $key => $label) {
            if (
                $candidate === $key
                || $candidate === mb_strtolower($label)
                || $candidate === mb_strtolower(self::SEASON_ABBREVIATIONS[$key])
            ) {
                return $key;
            }
        }

        return null;
    }

    public static function seasonLabel(?string $season): ?string
    {
        return $season === null ? null : (self::SEASONS[$season] ?? $season);
    }

    /**
     * Build a sheet heading such as "2025 DS" from stored values only. Nothing is
     * inferred from today's date, so a sheet printed next year still reads right.
     */
    public static function seasonHeading(?string $season, ?int $year): string
    {
        $abbreviation = $season === null
            ? null
            : (self::SEASON_ABBREVIATIONS[$season] ?? mb_strtoupper($season));

        return trim(implode(' ', array_filter([
            $year !== null && $year > 0 ? (string) $year : null,
            $abbreviation,
        ])));
    }

    public function plantingSeasonHeading(): string
    {
        return self::seasonHeading($this->planting_season, $this->planting_year);
    }

    public function harvestSeasonHeading(): string
    {
        return self::seasonHeading($this->harvest_season, $this->harvest_year);
    }

    public function displayLabel(): string
    {
        return trim(implode(' · ', array_filter([
            $this->reference,
            $this->plantingSeasonHeading(),
        ]))) ?: 'Distribution sheet #'.$this->getKey();
    }
}
