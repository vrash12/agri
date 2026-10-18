# Parcel satellite indices

Status: implemented locally on October 19, 2026; not pushed or deployed.
Release with the pending modular-monolith and cost/efficiency work.

## Using the feature

Open Farmers → Parcel map, select a farmer, expand a saved parcel and choose
**Satellite field check**. The same controls work in the modal and full-page
fallback. Choose an index, a UTC period of up to 31 days and a scene-cloud limit,
then select **Find pictures**. The latest usable day opens automatically; other
dates, natural-colour images, opacity controls and the history table remain
available. No request is sent merely by changing the index.

The plain-language follow-up adds a **Help me choose** guide and short,
index-specific explanations beside the result. NDVI is a general crop check;
NDMI looks for possible lack of water; SAVI suits young/widely spaced crops;
EVI suits thick, leafy crops. Simple text explains higher/lower numbers and a
practical next step. No-data and few-pixel messages avoid implying crop damage
or a percentage of clear land. Formulas, resolution limits and calculation
details remain under the closed **Technical details** disclosure. This copy
follow-up remains local and does not change calculations or provider budgets.

| Index | Purpose | Sentinel-2 formula (harmonized reflectance) | Approximate output grid |
| --- | --- | --- | --- |
| NDVI | General vegetation greenness | `(B08 - B04) / (B08 + B04)` | 10 m |
| NDMI | Investigate possible vegetation water stress | `(B08 - B11) / (B08 + B11)` | 20 m |
| SAVI | Reduce soil-background influence in sparse/newly planted crops | `1.5 * (B08 - B04) / (B08 + B04 + 0.5)` | 10 m |
| EVI | Dense canopy comparisons where NDVI can saturate | `2.5 * (B08 - B04) / (B08 + 6 * B04 - 7.5 * B02 + 1)` | 10 m |

The grid is geographic and approximate. NDMI does not upsample B11 to imply
10 m detail. Natural-colour reference images use approximately 10 m cells even
when reviewing NDMI. The SCL cloud/scene classification is 20 m for every index.
Narrow or small parcels may contain too few usable cells for interpretation.

These are remote-sensing indicators, not validated crop-specific risk thresholds.
NDMI is not a soil-moisture percentage, an irrigation diagnosis or proof of
drought. Flooded paddies, bare soil, crop stage and recent harvest affect the
signal. Compare similar crop stages and observation dates with rainfall,
verified irrigation records and field inspection. SAVI does not measure soil
nutrients or agricultural suitability. EVI is not a yield prediction. Nothing
automatically assigns an official drought level or an affected-parcel link.

## Calculation and presentation

`App\Modules\Climate\Application\SatelliteIndex` owns the finite allowlist,
formulas, band/reflectance units, resolution, numeric validation and display
metadata. Caller-supplied formulas, scripts, bands or resolutions are never
accepted. `SentinelImagery` uses one calculation function for each index's
statistics and image, so their validity masks match. SCL classes 4/5/6
(vegetation, bare soil, water) remain eligible; clouds, shadows, snow, defective
and missing pixels are excluded. Eligible pixels are not necessarily crops.

Required reflectances must be finite and nonnegative. A denominator with
absolute value ≤ 0.000001 is masked, never substituted with a zero index.
Statistics preserve available zero separately from unavailable data. NDVI/NDMI
use their ±1 bounds. With nonnegative reflectance, SAVI is bounded by ±1.5;
reflectance can exceed one. EVI is not assumed to be bounded by ±1: ±1 billion
is only a defensive numerical envelope, shared by the script and response
validators, not an agronomic range. Small divisors and extreme values need
review, even when finite.

All index colour ramps show −1 to +1. Colours outside that display range use
the end colours without clipping the numeric measurements. The meter and
history chart expand to include accepted values; unusual values trigger review
copy. Colours never mean Normal/Watch/Alert/Critical. The response reader
checks index/grid identity before accepting values. Changing the index cancels
pending requests and clears old rows, images, labels and charts. Late replies
cannot restore the previous index or unlock a newer pending search.

The existing `ParcelVegetationSnapshot` summary and climate dashboard remain
**NDVI-only**. NDMI, SAVI and EVI results cannot overwrite that summary.
Automatic climate-source refresh continues to request default NDVI only, under
its existing opt-in settings and caps.

## HTTP, caching and costs

The existing observations endpoint accepts optional `index` with one of
`ndvi`, `ndmi`, `savi`, `evi`; omission retains NDVI compatibility. Its JSON
includes `index`, `resolution_m`, `method`, source/date metadata and the existing
daily observations. The image endpoint accepts these values in `layer`, plus
`true-color`. Both endpoints authorize the saved parcel before processing.
Ownership, role, municipality, CSRF/session rules and private image responses
remain intact. No schema, account, credential or dependency change is required.

Each explicit search makes at most one statistics request and then one image
request for the latest usable day, before cache reuse. Selecting another image
date or natural-colour view can make a separate image request. Opening the
workspace, changing the index or finishing a cooldown does not start provider
processing. Failed requests are never automatically retried.

All indices share the existing `satellite-observations` 6/minute and
`satellite-images` 12/minute account buckets and the existing daily/monthly
processing allowances. An uncached outbound processing attempt reserves the
existing allowance; cache hits do not. Changing the index does not bypass a
cooldown or allocate a new budget. No quota increase or automatic refresh was
enabled. Google reference map billing remains separate.

Cache identity includes index/layer, exact canonical boundary and grid,
dates/cloud filter, evalscript, processing settings, ownership and credential
identity. Different indices never reuse one another's results. Existing
retention remains: usable settled historical results 30 days; recent, empty or
fully masked results 12 hours. Old script keys expire naturally. Do not clear
the general cache or reset provider counters.

## Release and verification

Release the new `SatelliteIndex`, changed Climate classes/controller, both
satellite partials, JS/CSS, reviewed Assistant satellite passages and resolver
aliases together with the pending modular-monolith/efficiency release. Keep the
stable `ndvi` Assistant topic identifier; it now explains all supported indices.
Knowledge hashes invalidate old generated help without clearing the cache.
Mirror public assets to the served Hostinger public directory. Regenerate
optimized Composer autoloading and config/route/Blade caches, without a general
cache clear. No migration or data rewrite is needed.

Regression checks cover actual exported evalscripts, formulas and explicit
reflectance units; zero/negative/above-one/no-data values; cloud and denominator
masks; index/grid cache separation; matching PNG dimensions; shared allowances;
authorization; NDVI summary isolation; stale response/cooldown handling; and
reviewed Assistant retrieval. Local verification passed all 282 JavaScript
tests and 368 focused PHP checks / 2,970 assertions (165 existing warnings for
the absent `.env.testing`; no failures). Pint, Blade compilation and whitespace
checks passed. Signed-in live Copernicus imagery acceptance is
still required after deployment; synthetic provider tests are not live imagery
verification. Use one unchanged saved parcel for each index and verify dates,
legends, resolution notes, natural-colour reference and retained modal access.

## Primary references

- [USGS NDMI](https://www.usgs.gov/landsat-missions/normalized-difference-moisture-index): vegetation water-content use; Sentinel-2 band names above are sensor-specific.
- [USGS SAVI](https://www.usgs.gov/landsat-missions/landsat-soil-adjusted-vegetation-index): soil-background adjustment and L = 0.5.
- [Sentinel Hub EVI formula](https://custom-scripts.sentinel-hub.com/custom-scripts/sentinel-2/evi/): B08/B04/B02 formulation. This implementation preserves finite values beyond the nominal display range.
- [Sentinel-2 L2A bands and units](https://docs.sentinel-hub.com/api/latest/data/sentinel-2-l2a/): B08 10 m, B11/SCL 20 m; harmonized reflectance may exceed one.
- [Evalscript V3](https://docs.sentinel-hub.com/api/latest/evalscript/v3/): per-band input units, floating-point statistics and image output.
