<?php

namespace App\Modules\Farmers\Application;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\User;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only, current registry checks for the Assistant.
 *
 * The result contains counts and existing review links only. It deliberately
 * does not select farmer names, identifiers, addresses, geometry or contact
 * fields, and it never calls an AI provider.
 */
final class AssistantDataQuality
{
    public function __construct(private MunicipalityAccess $access) {}

    public static function matches(string $text): bool
    {
        return (bool) preg_match('/\b(?:data\s+quality|quality\s+checks?|registry\s+health|record\s+completeness)\b/iu', $text)
            || ((bool) preg_match('/\bwhat\s+(?:needs|needs\s+to)\s+attention\b/iu', $text)
                && (bool) preg_match('/\b(?:registry|records?|farmers?|parcels?|plots?|mapping|data|ffrs|locations?)\b/iu', $text));
    }

    /**
     * Current scoped counts only; the Reporting module words the reply.
     *
     * @return array{total:int,missing_ffrs:int,missing_location:int,without_parcels:int,plots_without_geometry:int}
     */
    public function figures(User $user): array
    {
        Gate::forUser($user)->authorize('viewAny', Farmer::class);
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);

        $farmers = $this->access->scope(Farmer::query(), $user);
        $plots = FarmPlot::query()->whereIn('farmer_id', (clone $farmers)->select('farmers.id'));

        return [
            'total' => (clone $farmers)->count(),
            'missing_ffrs' => FarmerDataQuality::missingFfrs(clone $farmers)->count(),
            'missing_location' => FarmerDataQuality::missingLocation(clone $farmers)->count(),
            'without_parcels' => (clone $farmers)->whereDoesntHave('farmPlots')->count(),
            'plots_without_geometry' => (clone $plots)->where(function (Builder $query): void {
                $query->whereNull('polygon_json')->orWhere('polygon_json', '')->orWhere('polygon_json', '[]');
            })->count(),
        ];
    }

    /** @return array{missing_ffrs:array{label:string,url:string},missing_location:array{label:string,url:string},without_parcels:array{label:string,url:string}} */
    public function links(): array
    {
        return [
            'missing_ffrs' => ['label' => 'Review missing FFRS', 'url' => route('farmers.index', ['quality' => 'missing_ffrs'])],
            'missing_location' => ['label' => 'Review missing locations', 'url' => route('farmers.index', ['quality' => 'missing_location'])],
            'without_parcels' => ['label' => 'Review unmapped farmers', 'url' => route('farmers.index', ['mapping' => 'unmapped'])],
        ];
    }
}
