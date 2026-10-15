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

    /** @return array{answer:string,links:list<array{label:string,url:string}>} */
    public function summary(User $user): array
    {
        Gate::forUser($user)->authorize('viewAny', Farmer::class);
        Gate::forUser($user)->authorize('viewAny', FarmPlot::class);

        $farmers = $this->access->scope(Farmer::query(), $user);
        $plots = FarmPlot::query()->whereIn('farmer_id', (clone $farmers)->select('farmers.id'));
        $total = (clone $farmers)->count();
        $missingFfrs = FarmerDataQuality::missingFfrs(clone $farmers)->count();
        $missingLocation = FarmerDataQuality::missingLocation(clone $farmers)->count();
        $withoutParcels = (clone $farmers)->whereDoesntHave('farmPlots')->count();
        $plotsWithoutGeometry = (clone $plots)->where(function (Builder $query): void {
            $query->whereNull('polygon_json')->orWhere('polygon_json', '')->orWhere('polygon_json', '[]');
        })->count();

        $answer = implode("\n", [
            'Data quality · current registry review',
            'Scope: '.mb_substr($user->scopeLabel(), 0, 180),
            "Registered farmer profiles: {$total}",
            "Missing FFRS reference: {$missingFfrs}",
            "Missing farm location: {$missingLocation}",
            "Farmers without saved parcels: {$withoutParcels}",
            "Saved plots without boundary geometry: {$plotsWithoutGeometry}",
            'These are review queues, not fraud findings. Missing information is not a recorded negative value.',
        ]);

        return [
            'answer' => $answer,
            'links' => [
                ['label' => 'Review missing FFRS', 'url' => route('farmers.index', ['quality' => 'missing_ffrs'])],
                ['label' => 'Review missing locations', 'url' => route('farmers.index', ['quality' => 'missing_location'])],
                ['label' => 'Review unmapped farmers', 'url' => route('farmers.index', ['mapping' => 'unmapped'])],
            ],
        ];
    }
}
