<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Repeat-claim window
    |--------------------------------------------------------------------------
    |
    | How many days back to look for an earlier release of the same input to the
    | same farmer. A match raises a warning on the entry form; it never blocks the
    | release, because a second claim is sometimes correct — a replanting after a
    | typhoon, a split delivery, or two genuinely different cropping seasons.
    |
    | The default is one cropping season. Philippine rice is broadly dry season
    | (November to April) and wet season (May to October), so roughly 180 days
    | separates one legitimate claim from the next. Shorten it for an office that
    | issues in split deliveries; lengthen it for one that issues once a year.
    |
    | This is a window in days rather than a season comparison because
    | `harvest_season` is not recorded on any existing release. When both releases
    | do state a season, that comparison is used instead and this window is ignored
    | — see App\Support\DuplicateClaimCheck.
    |
    */

    'duplicate_claim_window_days' => (int) env('ASSISTANCE_DUPLICATE_CLAIM_WINDOW_DAYS', 180),

];
