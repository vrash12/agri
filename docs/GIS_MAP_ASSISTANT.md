# Ask the map and field-visit priority layer

Status: local, not deployed (November 25, 2026). No migration, dependency,
configuration value, account operation, provider call or data write is involved.

## What staff see

The Farmers parcel map has a new **Ask the map** panel below **Crops on the
map** (shortcut "4. Ask the map"). Staff type a short question; the answer is a
plain sentence with counts, and the map either applies the existing crop filter
or outlines matching parcels.

| Example question | Result |
| --- | --- |
| corn parcels with no harvest this wet season | Count of corn parcels with a saved wet-season record and no linked harvest; the crop view switches to corn/wet season and those parcels are outlined in yellow |
| rice parcels 2026 dry season | Count; the existing **Filter parcels by saved crop** control is set to rice / 2026 / dry |
| rice parcels in San Isidro 2026 dry season | As above, plus a yellow outline on the parcels whose farmer's farm location is San Isidro |
| parcels without a boundary | Count of saved parcel records without boundary geometry and of farmers with no parcel; link to the registry's unmapped list (nothing to highlight) |
| farmers without a parcel in San Isidro | Count of farmers in that farm location without a saved parcel; registry link |
| parcels needing a field visit | Turns on the field-visit priority layer for the period |

Also recognized: Filipino/regional words (palay, mais, gulay, prutas, tag-ulan,
tag-araw, walang), "this year", "last year", "parcels with no crop recorded",
"unmapped farmers", "overdue inspections". When a question gives no year or
season, the map's applied crop period is used and the answer names it.

**Field-visit priority** (toggle) outlines loaded parcels with at least one reason:

- *Planned visit overdue* — the latest field check for that parcel/year/season
  has an open follow-up whose next-visit date is today or earlier, recorded
  against the current owner, office and boundary;
- *Greenness/moisture fell* — saved NDVI or NDMI history (completed checks only)
  shows a fall of at least 0.05 between two comparable, current pictures;
- *No harvest recorded* — a saved crop season for the period with no harvest
  linked to the parcel or its crop season.

Orange (thicker) = visit first: an overdue visit or two or more reasons. Amber =
one reason. Reason chips with dates appear in the hover card and the selected
farmer's parcel list; the legend shows counts and states that this is a
visit-planning aid, not a diagnosis. **Show only highlighted** hides other loaded
parcels; **Clear highlight** removes it.

## Rules

- **Local and allowlisted.** `MapQuestionInterpreter` uses fixed vocabularies
  built from `ParcelCropSeason::CROPS` / `SEASONS` and
  `HarvestRecord::COMMODITY_LABELS`, plus bounded regular expressions over at
  most 200 characters. No model, provider or user-supplied pattern is used.
  Category words only: "mango" does not widen to all fruit.
- **Clarify, never widen.** Write/export verbs, area or quantity limits,
  rankings, comparisons, two years/seasons/crops, fish/livestock/poultry,
  diagnosis words (pests, drought, damage…), people/assistance filters,
  positive "harvested" questions, out-of-range years, period words on
  boundary/coverage questions and unrecognized questions return a
  clarification with the five examples and no counts. A place name is matched
  only against farm locations (and municipality names) inside the current map
  view; an unknown or foreign place clarifies.
- **Scope.** Every query applies `MunicipalityAccess::scope()` and then the
  municipality IDs resolved by `FarmerWorkspace` from the same
  region/province/municipality parameters as the parcel request. The request
  URLs carry those parameters; omitting them can only fall back to the
  account's own scope. Policies (`FarmPlot` `viewAny`) and the existing
  route-name allowlists deny Provincial Veterinary, GIS Evaluator and visitor
  accounts.
- **Bounded.** Highlights and priority flags are requested only for parcel IDs
  already loaded on the map, at most 200 per request, sequentially, with stale
  responses ignored. Responses contain counts, parcel IDs, reason keys and
  dates — no names, identifiers, addresses or geometry. Satellite history is a
  cache read through `ParcelSatelliteHistory::forPlot()`; nothing calls
  Copernicus or any other provider.
- **Read-only.** No records, audit rows or caches are written. The question POST
  skips the operational-write `synchronized` lock (like map usage) and keeps
  CSRF, authentication, idle, account-scope and role middleware.
- **Throttled.** Named limiters: `map-questions` 20/minute and
  `map-layer-batches` 60/minute per account (`RouteServiceProvider`).
- **Rendering.** Highlights change only the stroke colour/width of existing
  parcel polygons; no extra Maps 3D objects are created. The red selected-parcel
  boundary and crop fills/badges take precedence and are unchanged. Highlights
  follow the loaded parcel set (debounced reload) and are cleared/replaced by
  the next question or the Clear button. Saved geometry and colours are never
  modified.

## Query and payload bounds

- Question: one place-name lookup (distinct farm locations in scope, at most
  2,000) when a place is typed, plus 1–3 aggregate queries. The no-harvest answer
  adds one query per 500 farmers for the unlinked-harvest note.
- Match batch: one count plus one joined query for ≤200 IDs.
- Priority batch: parcels (with geometry kept server-side for the evidence
  fingerprint), latest field check per parcel (one `MAX(id)` subquery), crop
  seasons and harvests — four queries — plus one cache read per parcel.
- A 2,000-parcel map therefore makes at most 10 batch requests per layer.

## UX changes

- Ask panel with loading ("Checking…"), answer, clarification-with-examples,
  session/throttle/failure messages, retryable highlight errors and an empty
  state that explains when highlights become available.
- Phone (≤720px): in-map legend wraps inside the map, farmer search and its
  button stack full width, 44px touch targets in the toolbar, the map hint is
  hidden, the scope strip wraps; the ask form stacks at ≤520px. A synthetic
  375px render had no horizontal page scroll. The farmer panel keeps its
  existing stacked layout below the map.

## Files

- `app/Modules/Land/Application/MapQuestionInterpreter.php` (pure parser)
- `app/Modules/Land/Application/MapQuestionAnswers.php` (scoped counts, matches, place resolution)
- `app/Modules/Climate/Application/ParcelVisitPriority.php` (priority flags; Climate owns the satellite history, so no new cross-module import)
- `app/Http/Controllers/ParcelMapQuestionController.php`
- `routes/web.php` (`farm-plots.map-questions`, `farm-plots.map-matches`, `farm-plots.visit-priority`), `app/Providers/RouteServiceProvider.php`
- `public/js/parcel-map-assistant.js` (new, testable), `public/js/farmers-maps.js` (stroke/visibility/hover/list hooks)
- `resources/views/farmers/partials/map-ask.blade.php` (new), `resources/views/farmers/maps.blade.php`, `resources/views/farmers/partials/maps-scripts.blade.php`
- Tests: `tests/Feature/ParcelMapQuestionTest.php`, `tests/Unit/MapQuestionInterpreterTest.php`, `tests/JavaScript/parcel-map-assistant.test.cjs`, one added case and a `parcelHighlights: null` context global in `tests/JavaScript/farmers-map-rendering.test.cjs`

## Verification (local, synthetic data, in-memory SQLite)

- PHPUnit: new feature/unit suites plus ModularArchitecture, ParcelCropSeason,
  ParcelAgronomy, ParcelSatelliteHistory, ParcelSatellite and both
  FarmerWorkspace suites — 183 tests / 2,929 assertions passed.
- `node --test tests/JavaScript/*.test.cjs` — 381 passed.
- Pint (explicit paths, then `--test`), `php -l`, `git diff --check` on touched
  files, `php artisan view:cache` / `view:clear`, `php artisan route:list --path=farm-plots`.
- Not verified: signed-in Google Maps 3D rendering of the coloured strokes,
  MySQL `JSON_EXTRACT` path against real data, production query timings.

## Limitations

- Recognizers are finite; unusual phrasings clarify rather than guess.
- Location means the farmer's recorded farm location text (or a municipality
  name in view), not parcel geometry inside a barangay boundary.
- "No harvest" counts harvests only when linked to the parcel or its crop
  season; unlinked farmer-level harvests are reported as a note, not matched.
  It does not know whether the season has ended.
- Highlights cover only parcels loaded on the map (the parcel cap still applies);
  answer counts cover the whole current map view.
- The 0.05 NDVI/NDMI threshold is a planning heuristic, not an agronomic standard.

## Release

Deploy the PHP classes, controller, routes, provider, both views/partial and
both scripts together; mirror `public/js/parcel-map-assistant.js` and
`public/js/farmers-maps.js` to both public directories and rebuild Blade/route
caches. No migration or data operation.
