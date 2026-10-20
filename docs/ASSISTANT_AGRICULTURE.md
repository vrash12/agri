# Agriculture help and numbered replies

Status: implemented locally on October 20, 2026; not pushed or deployed.

The Assistant distinguishes reviewed farming education from eAgriLGU workflows.
Questions about fertilizer, planting or fish farming no longer automatically
become assistance-entry or map-drawing instructions. Ten general topics cover
farming basics, soil, rotation, nutrients, seeds, establishment, irrigation,
pest management, livestock care and aquaculture. English/Filipino explanations,
steps, shorter answers and examples reuse the existing approved-topic context.

This remains a bounded reviewed-topic service, not unrestricted agricultural
question answering. It may clarify unsupported questions and cannot diagnose,
prescribe doses, fetch live prices/weather, or infer a local planting calendar.
Unrelated questions are declined locally; explicit mixed/instruction-override
requests also clarify. Keyword routing is not a semantic classifier guarantee.
Only application-owned canonical questions and eligible reviewed passages reach
OpenAI, so unmatched wording cannot produce an arbitrary-topic provider answer.
Raw questions, prior replies, names, record values and account scope remain local.

Animal Health roles may read general livestock-care guidance; other agriculture
and operational permissions are unchanged. Existing authentication, policy,
municipality checks, no-store responses, rate limits, atomic budgets, response
validation, source labels and cache safeguards remain. General help has no
operational query path. The corpus now has 42 passages / 39 topics within a
48-entry ceiling, the same 80 KiB corpus cap, 2,400 characters per passage and
three passages / 7,000 characters per retrieval. Cache identity changes with
instructions and knowledge; never clear the general cache.

Numbered assistant replies render as semantic ol/li elements. Consecutive inline
1..N numbering in older replies is also recognized. Paragraphs, introductions,
conclusions and indented item continuations remain readable. Years, decimals
and isolated inline numbers remain text. Rendering uses textContent only; HTML,
links, scripts and attributes in answers never execute. User messages remain text.
The provider instructions request a separate line for each numbered item.

## Curation references

The new passages are concise general educational summaries reviewed against
these primary references on October 20, 2026. These are maintained references,
not runtime web retrieval, certifications or endorsements. Public source titles
identify the subject and organization; the URLs below document provenance.

- Soil cover: [FAO soil organic cover](https://www.fao.org/conservation-agriculture/in-practice/soil-organic-cover/en/).
- Rotation and soil management: [FAO conservation agriculture practice](https://www.fao.org/agriculture/crops/thematic-sitemap/theme/spi/scpi-home/managing-ecosystems/conservation-agriculture/ca-how/en/).
- Nutrient management: [IRRI Nutrient Manager for Cereal Systems](https://webapps.irri.org/nm/index.html).
- Seed quality: [IRRI seed quality course](https://www.knowledgebank.irri.org/images/docs/seed-quality-management.pdf). Search-extracted course objectives were available; the full PDF fetch was unavailable. No detailed rate or storage threshold is inferred from it.
- Irrigation: [IRRI water management overview](https://ricetoday.irri.org/water-management-for-food-security-how-does-irri-contribute/).
- Pest management: [FAO integrated pest management](https://www.fao.org/agriculture/crops/thematic-sitemap/theme/spi/scpi-home/managing-ecosystems/integrated-pest-management).
- Livestock: [FAO biosecurity](https://www.fao.org/animal-health/areas-of-work/biosecurity/en) and [FAO farm practices](https://www.fao.org/dairy-production-products/production/farm-practices/en/).
- Fish farming: [FAO freshwater farm management](https://www.fao.org/fishery/docs/CDrom/FAO_Training/FAO_Training/General/x6709e/x6709e16.htm).

The basics and planting-preparation passages synthesize introductory concepts
from these references without local prescriptions. Structured output checks
constrain format/source membership, not semantic truth; see
[OpenAI structured outputs](https://developers.openai.com/api/docs/guides/structured-outputs).

## Verification and release

Tests cover local agriculture routing, unrelated/mixed refusals, unchanged record
workflows, eligible sources, veterinary boundaries, provider privacy, topic/style
follow-ups and numbered-list escaping. Provider tests use fakes, not a live model
accuracy evaluation. See the final verification note below for completed checks.

Deploy the Assistant application/infrastructure changes, all reviewed passages,
shared Blade, JavaScript and CSS together. Mirror both public assets in Hostinger
public_html and rebuild optimized autoload/configuration/route/view caches as
usual. No dependency, migration, account, environment, provider-budget or DNS
change is needed. Deployment still requires the owner's explicit request.

## Completed local checks

- Assistant regression: 833 PHP tests / 5,837 assertions passed.
- Module architecture/composition: 8 tests / 1,130 assertions passed.
- Full JavaScript suite: 332 tests passed, including 47 chat-renderer tests.
- Laravel Pint, changed PHP syntax, Blade compilation, assistant route listing
  and whitespace checks passed.

All provider regression calls were faked and private-record tests used synthetic
SQLite fixtures. No live-model accuracy evaluation, browser visual acceptance,
production request, push or deployment was performed for this update.
