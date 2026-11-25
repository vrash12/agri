# Parcel summary and AI crop advice

Release status: deployed in `43e9b9b` on November 25. See [verification and remaining limits](NOVEMBER24_RELEASE_2026_11_25.md). Local-only descriptions below are historical implementation checkpoints.

Status: local, not deployed (owner-approved November 24, 2026). No migration,
dependency, environment setting or account change is required.

Both features sit at the top of **Crop checks & suitability**
(`farm-plots.agronomy.show`) for one authorized parcel and the selected crop
year/season.

## 1. Parcel summary (local, no AI)

`App\Modules\Climate\Application\ParcelBriefing` composes a plain-language
briefing from saved evidence only:

| Item | Source | Unavailable state |
| --- | --- | --- |
| Crop this season | `parcel_crop_seasons` row for the parcel/year/season whose numeric `municipality_id` matches the parcel owner, plus planted sections validated by `Land\ParcelCropSeasons::modalRows` | “No crop is recorded…”; sections needing review are reported as such |
| Last field visit | latest current-context field check (`ParcelFieldAssessments`) and the stress monitor's validated stage/condition/water and follow-up | none, older than 14 days, or earlier parcel boundary/owner |
| Satellite trend | `ParcelStressMonitor` trends over saved `ParcelSatelliteHistory` (NDVI, then EVI, then SAVI; NDMI noted when comparable). No satellite request is made | no comparable saved pictures, single/stale pictures |
| Municipality weather and rainfall | existing 7-day Open-Meteo and 30-day CHIRPS readings rechecked by `ClimateAutomaticMonitor`; always labeled **Municipality-level, not measured at this parcel** | “Missing data is not zero rainfall” |
| Harvest history | up to three latest `harvest_records` with this parcel, its current farmer and municipality, scoped through `MunicipalityAccess`; units kept separate | “This does not mean nothing was harvested” |
| Suggested next step | the existing deterministic `ParcelStressMonitor` signals (field condition, water, flowering, inspection due, satellite decline, then municipality signals), then missing evidence, then `ParcelSuitability` review factors | — |

The page and the summary make no external request. Missing, expired or
incompatible evidence is stated as unavailable, never as a reassuring zero.
Authorization: the parcel `view` policy runs before any relationship is read;
farmer, veterinary, evaluator, visitor and other-municipality accounts are denied
by the existing middleware/policies.

`ParcelStressMonitor::summarize()` additionally returns its validated `stage`,
`condition` and `water` keys (null when missing/stale/unsourced). Satellite
categories use an application display band of ±0.05 index units for “about the
same”; this is not a validated stress threshold.

## 2. AI crop advice (explicit button only)

**Ask AI for crop advice** posts to `POST /farm-plots/{plot}/agronomy/advice`
(`farm-plots.agronomy.advice`). Nothing is sent on page load. The controller
re-authorizes the parcel, recomposes the briefing server-side and passes only its
`categories` to `App\Modules\Assistant\Application\ParcelCropAdvice`, which
re-validates them against its own allowlist. The client cannot supply categories.

### Provider allowlist (`ParcelCropAdvice::CATEGORIES`)

| Field | Values |
| --- | --- |
| crop | `ParcelCropSeason::CROPS` keys except `not_recorded` (field-check mungbean maps to `legumes`); `not_recorded` returns 422 locally |
| season | dry, wet |
| growth_stage | not_planted, establishment, vegetative, flowering, grain_filling, mature, harvested, unknown |
| condition | normal, wilting, yellowing, waterlogged, unknown |
| water_supply | reliable, limited, unavailable, unknown |
| satellite_trend | falling, stable, rising, unavailable |
| rainfall (municipality-level) | below_reference, little_rain_forecast, no_dry_signal, unavailable |

The provider prompt is exactly one application-owned question plus seven
`Label: value` lines built from fixed labels, e.g.:

```text
What general crop-care checks should agriculture-office staff consider for a field with these conditions?
Crop: Rice / Palay
Season: Dry season
Growth stage: Leaf growth
Observed field condition: Wilting
Water supply: Limited
Satellite greenness trend: Unavailable (no comparable saved pictures)
Municipality-level rainfall (area, not this field): Unavailable
```

No farmer name, IDs, parcel name, coordinates, barangay/municipality names,
notes, sources or free text are sent. There is no message history.

### Grounding

`ParcelCropAdvice::topics()` deterministically selects two reviewed agriculture
topics (for example wilting → irrigation + pest management; yellowing → pest
management + crop nutrition; harvested → rotation + soil health). Passages come
from the unchanged `AssistantKnowledge::retrieve()` with each topic's canonical
question (at most three passages / 7,000 characters). The new
`Infrastructure\CropAdviceAgent` uses strict structured output (answer,
supported, citations restricted to retrieved IDs), temperature 0 and 600 output
tokens. `AssistantGroundedAnswer` validates the output and requires the primary
passage citation; URLs, HTML, secrets and contact patterns are rejected. The
instructions forbid diagnoses, products, doses, schedules and drought
declarations. Source titles shown to staff are application-owned.

### Budget, cache, throttle and states

- Disabled/misconfigured Assistant (`OfficeAssistant::configured()`) → 503 and the
  page shows an unavailable note instead of the button. Veterinary accounts are
  excluded even though the Assistant policy otherwise admits them.
- Each provider call reserves the existing shared atomic allowance through
  `AssistantRequestBudget` (daily, monthly, per-user). Exhaustion → 429 before
  inference. Cache hits reserve nothing.
- Cache key `assistant:crop-advice:v1:<sha256>` over the schema, generation
  settings, agent file hash, the seven categories, corpus digest, passage IDs,
  model, question and instructions. Only validated output is cached, for 24 hours;
  hits are revalidated against current passages. Values hold only the generic
  answer and passage IDs. One per-key nonblocking lock prevents duplicate
  generation; contention serves reviewed guidance.
- Provider failure or rejected output returns the primary reviewed passage
  (`delivery: guide`) without retry.
- `throttle:4,1,parcel-crop-advice` (prefixed, so it does not share the numeric
  throttle counter), `SynchronizeAssistantRequests` (one Assistant request per
  user) and no operational `synchronized` write lock. The session/CSRF late-save
  skip used by chat also covers this route.
- Metrics reuse `openai` provider attempts/tokens and the existing
  `assistant-help` (“AI general help”) cache counter.
- Audit: `Office Assistant` / `viewed` with task, delivery, token counts,
  passage counts and latency; no prompt, answer or categories text.
- UI shows Generated now / Saved answer reused / Reviewed guidance, cited source
  titles, loading (`aria-busy`, reduced-motion spinner), unavailable and error
  states, and the disclaimer: advisory only, not a prescription; verify in the field.

## Files

- `app/Modules/Climate/Application/ParcelBriefing.php` (new)
- `app/Modules/Assistant/Application/ParcelCropAdvice.php` (new)
- `app/Modules/Assistant/Infrastructure/CropAdviceAgent.php` (new)
- `app/Modules/Climate/Application/ParcelStressMonitor.php` (three additive keys)
- `app/Modules/ModuleMap.php` (Climate → `Land\Application\ParcelCropSeasons`)
- `app/Http/Controllers/ParcelAgronomyController.php` (evidence via `ParcelBriefing`, new `advice`)
- `app/Http/Middleware/StartApplicationSession.php`, `VerifyCsrfToken.php` (route name added)
- `routes/web.php` (one route next to the agronomy routes)
- `resources/views/farm_plots/partials/agronomy-briefing.blade.php` (new), `resources/views/farm_plots/agronomy.blade.php` (include)
- `public/js/parcel-crop-advice.js` (new), `public/css/parcel-agronomy.css` (appended)
- `tests/Feature/ParcelAiBriefingTest.php`, `tests/JavaScript/parcel-crop-advice.test.cjs` (new)

Deploy the PHP, view and both public assets together; mirror public assets to both
Hostinger public directories. Refresh configuration/route/view caches; do not clear
the general cache (it holds budgets).

## Verification (local, November 25, 2026)

- `ParcelAiBriefingTest` 12 tests; with `ParcelAgronomyTest`, `ParcelStressMonitorTest`,
  `ParcelSuitabilityTest` and `ModularArchitectureTest`. Coverage: missing/expired/old-context
  evidence, crop season and planted sections, harvest isolation, satellite/rainfall categories,
  exact provider prompt and absence of names/places/coordinates/notes in prompt, cache and audit,
  cross-parcel cache reuse, budget exhaustion, disabled state, unrecorded crop, fallback,
  other-municipality/vet/evaluator/visitor/inactive/guest/farmer denial, CSRF, throttle and middleware.
  `Http::fake()` + `assertNothingSent()` throughout; provider calls are fakes only.
- `AssistantKnowledgeTest` and `AssistantAgricultureTest` pass. The November 25 integrated run also passed the chat suites; the earlier
  interface mismatch is resolved. See the November 25 release receipt.
- JavaScript renderer test, Pint, `php -l`, `view:cache`/`view:clear`, `route:list` and
  `git diff --check` pass.

Not verified: real OpenAI output quality, browser visual/mobile acceptance and
production latency. A valid citation does not prove every sentence is entailed.
