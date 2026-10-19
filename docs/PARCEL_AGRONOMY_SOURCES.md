# Parcel agronomy: evidence and reference rules

The October 19, 2026 implementation provides **basic crop screening and field-inspection support**. It does not issue an official land-suitability class, drought declaration, yield forecast, irrigation prescription or diagnosis. No model or provider generates these decisions: the reasons follow the bounded rules below.

## Crop references

Primary sources checked on October 19, 2026:

| Profile | Reference pH | Reference temperature | Provenance |
| --- | --- | --- | --- |
| Rice / Palay, irrigated lowland | 5.5–7.0 | 20–30 °C | [FAO Ecocrop: Oryza sativa, 1574](https://ecocrop.apps.fao.org/ecocrop/srv/en/dataSheet?id=1574) |
| Corn | 5.0–7.0 | 18–33 °C | [FAO Ecocrop: Zea mays ssp. mays, 238663](https://ecocrop.apps.fao.org/ecocrop/srv/en/dataSheet?id=238663) |
| Mungbean / Munggo | 5.5–6.2 | 21–36 °C | [FAO Ecocrop: Vigna radiata, 2150](https://ecocrop.apps.fao.org/ecocrop/srv/en/dataSheet?id=2150) |

These are Ecocrop's broad **optimal reference** ranges, not absolute survival limits or Philippine cultivar prescriptions. Values outside them prompt local review rather than a claim that the crop cannot grow. Temperature represents a sourced growing-period mean; it cannot rule out damaging daily extremes. General categories such as “vegetables” and “other” cannot substitute for a species-specific profile.

Ecocrop lists medium texture and good drainage as favorable for corn and mungbean. The interface's broad `loamy` category approximates medium texture; it does not reproduce a laboratory soil classification. For lowland rice, the clayey reference follows [FAO basin-irrigation guidance](https://www.fao.org/4/s8684e/s8684e03.htm): sandy soils may support rice but can require additional water control. Poor drainage matches the lowland rice screen while requiring controlled flooding and drainage. The same condition needs review for corn and mungbean.

The [FAO soil and terrain surface-irrigation study](https://www.fao.org/4/w4347e/w4347e08.htm) supplies the less-than-2% gentle-slope reference. Its irrigation assumptions and regional screening scale limit transfer to an individual field. The application therefore labels this factor **Slope and irrigation layout**. A steeper parcel requests review of terraces, runoff and irrigation layout; it is not automatically unsuitable for the crop. Parcel-average slope cannot prove that an individual paddy is level. [IRRI's land-leveling guidance](https://www.knowledgebank.irri.org/training/fact-sheets/land-preparation/land-leveling) explains the role of uniform water coverage.

## Water and rainfall

[FAO crop water needs](https://www.fao.org/4/s2022e/s2022e07.htm) vary with climate, crop and growth stage. A dated local water-supply observation is therefore evaluated separately from rainfall. “Reliable” is a staff-reported supply condition; its quantity, timing and quality still require confirmation.

Ecocrop rainfall values are **annual**. The application does not compare a seven-day forecast, a 30-day CHIRPS total or a single-season rain total against those annual bands. Entered growing-period rainfall is displayed with its source and dates as context only. Neither a high total nor a positive satellite index proves sufficient crop water.

## Dated evidence and statuses

`ParcelSuitability::evaluate()` returns six factors: pH, texture, drainage, slope/layout, growing-period temperature and water. Every factor is `matches`, `review` or `missing`. All six supported, sourced and current factors are required for `potentially_suitable`, whose user label is “Reference conditions matched.” Any missing factor makes the overall result `insufficient_evidence`; known concerns remain visible. Complete evidence with a concern is `review_needed`. No weights or percentage score are used.

Actual calendar dates, finite measurements, valid enums, sources and current parcel context are required. Future evidence, dates after the assessment, malformed values and obsolete boundary context remain unavailable. Zero is retained when it is a real measured value.

The following are **application recheck policies**, not scientific freshness standards: assessment 365 days, soil 730 days, terrain 1,825 days, growing-period climate ending within 730 days, water and field checks 14 days. Climate periods must cover 30–366 days; the minimum prevents a short forecast from masquerading as growing-period evidence. Offices should review these defaults for their local practices. They do not schedule or increase provider calls.

## Crop-stress monitoring

`ParcelStressMonitor::summarize()` combines fresh field observations with the already authorized municipality weather/CHIRPS evidence and saved parcel satellite history. It rechecks the municipal source dates and coverage through `ClimateAutomaticMonitor`; the area evidence remains labeled **Municipality**. It does not invent parcel-scale rainfall or root-zone moisture.

Reported wilting, yellowing, waterlogging or an active crop with limited/unavailable water prompts a visit. These signs have multiple possible causes. The [IRRI water-management guide](https://books.irri.org/9789712202193_content.pdf) specifically supports extra attention to rice water supply around flowering. The interface recommends field confirmation rather than automated watering, pesticide or fertilizer instructions.

Saved observations are grouped by NDVI, NDMI, SAVI and EVI. Comparisons require the same series identity (index, settings, grid, script, credentials and current boundary), a latest usable observation within 14 days, and a prior observation 3–45 days earlier. These are transparent operational comparison windows, not validated stress thresholds. Dates before the recorded planting are excluded. Multiple observations on one date produce one chart point. Missing, cloudy, future, malformed or incompatible evidence never becomes zero.

Raw finite SAVI/EVI values are retained, including values outside a display color scale. NDVI/NDMI must remain in their normalized range. Changes are reported in index units, not percentage crop damage. A decrease alone does not diagnose stress. An NDVI/NDMI decrease adds a field-check prompt only alongside a water/field concern or current municipal monitoring trigger and a confirmed active crop with a planting date. Mature, harvested and unplanted stages suppress this inference. Missing planting dates explicitly prevent a same-crop-cycle claim.

An annual-crop record more than 366 days after planting needs stage confirmation before crop-cycle inference. Crops that cross New Year remain valid; the selected season year is not treated as a biological cutoff.

Open inspection dates can create an “Inspection due” prompt; completing a follow-up does not erase current evidence. No result automatically changes official climate assessments, affected-parcel links, saved crop geometry, hectares or satellite budgets.

## Verification

Focused unit coverage checks missing and malformed evidence, source freshness, future dates, true zero values, unsupported crops, differing profiles, surface-irrigation scope, rainfall limitations, current/old field reports, comparison identity, cloud/missing pixels, real SAVI/EVI values, crop-stage interpretation, planting dates, duplicate dates, stale municipal summaries and inspection follow-ups. Provider requests are prohibited in these tests.
