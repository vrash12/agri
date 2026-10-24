# LGU record retrieval and Assistant UX

Status: local source and isolated verification, October 24–25, 2026. Not deployed.

## Record coverage

The Assistant answers supported record questions through fixed local database
queries after office authentication, policy authorization and `MunicipalityAccess`
scoping. It does not send private rows to a model or let generated SQL choose the
records. Counts are calculated again for each request; they are not cached help.

| Ask | Data and meaning |
| --- | --- |
| Summarize our LGU records for 2026 | Eight modules: registry/mapping, assistance, harvests, Animal Health, retained workbook rows, seasonal crops, cooperatives and machinery |
| Summarize registry sections | Retained active, Outside LGU, Delisted and unrecognized-status rows; these are rows, not distinct people |
| Show outside LGU records | Outside LGU rows, valid same-LGU links and distinct linked farmers; outside addresses do not transfer ownership |
| Show delisted rows | Retained Delisted rows only; stale farmer links do not establish active registrations |
| Show seasonal crop records for 2026 | Valid parcel-season classifications, distinct parcels and excluded invalid links; no inferred planted area, section count or production |
| Summarize cooperatives | Cooperative counts and distinct valid same-LGU member farmers; no eligibility inference |
| Summarize machinery | Saved availability, unrecognized status and the inventory's maintenance-attention rule |
| Show LGU profile setup | Active offices, configured profiles, logo/signatory coverage and published page counts; publication does not verify DNS or HTTPS |

Existing annual assistance, harvest and Animal Health reports, farmer search,
selected-farmer details and data-quality reports remain available. Veterinary
accounts retain only their authorized Animal Health records and help. A broader
account receives its authorized scope, not a caller-selected municipality.

Current registry/setup/inventory values are labeled separately from records for a
stated year. Quantity units are never added together. Optional missing tables
produce an unavailable message rather than a fabricated zero. Cross-LGU linked
records are excluded where the report requires an owned farmer or parcel.

The October 25 refinement explicitly rejects reporting years on current-only
workbook, cooperative, machinery and LGU setup reports, including a year-only
follow-up. These sources have no historical snapshots; clarification clears the
old context and does not query those operational tables or call a provider.
An earlier annual report's context does not prevent a new request for current
records. Crop reports and other supported annual reports retain their year handling.
Additional exact phrases include “How many farmers are delisted?”, “Ilan ang
delisted na magsasaka natin?” and “Show LGU profile setup”; additional geography,
private fields or other unsupported qualifiers are still rejected.

This is broader supported reporting, not unrestricted access to every field.
Private contacts, birth dates, source payloads, uploaded files, credentials,
accounts, audit contents and field notes are not added to model context. Unsupported
subsets, exclusions, status/age filters, rankings, arbitrary geography and multi-year
requests ask for clarification or direct users to the appropriate register. They
must not silently return a broader total. No autonomous write, web search, provider
refresh or private-document ingestion is introduced.

## Evidence and interface

Record replies carry application-owned evidence: account scope, reporting basis,
query time and fixed links to the source registers. The query time is not a claim
about the source's latest update. Figures describe saved records; missing events
and concurrent edits can affect their completeness. Several aggregates run as
separate reads, not a single point-in-time database snapshot.

The chat shows a keyboard-accessible Sources and coverage disclosure, at most
eight source links and bounded limitations. The renderer accepts only same-origin
links, rejects credentials and unsafe protocols, and renders text without HTML.
Starter cards preserve drafts and stage an editable prompt. Stop checking cancels
the browser request and offers Retry; late responses cannot replace a newer request.
Canceling the browser does not promise cancellation of already-started server work.
Numbered replies, gradual display, reduced-motion support and role-specific prompts
remain intact. Mobile controls account for the available viewport and safe areas.

## Reviewed help retrieval

`registry-sections` and `lgu-settings` add reviewed workflow guidance. The corpus
contains 44 passages across 41 canonical topics, within the existing 48-entry,
80 KiB, three-passage, 7,000-character retrieval bounds. Each passage remains
limited to 2,400 characters. Provider inputs remain canonical questions and
reviewed public workflow material, never raw questions or prior record replies.

Generated and cached help must cite the first, primary retrieved passage.
Secondary-only citations fail validation and use reviewed fallback text. Primary
and secondary citations may appear in either order. This checks provenance and
source selection; it does not establish semantic entailment or guarantee perfect
answers. Existing allowance accounting, single-flight locks, cache identities,
failure behavior and agriculture-only topic restrictions remain in force.

## Verification

- October 25 refinement: the broad PHP checkpoint passed 2,093 tests / 36,374
  assertions, with seven engine-specific backup checks skipped on SQLite. The
  new current-period and wording cases initially reproduced six failures, then
  all 57 LGU intent/conversation tests passed with 508 assertions after correction.
  The final affected run passed 1,078 tests / 9,442 assertions, including Assistant,
  finder, satellite and architecture suites; see the operational UX receipt.
- Integrated Assistant PHP checks: 980 passed / 7,334 assertions, covering local
  routing, record aggregates, role/municipality isolation, context follow-ups,
  unsupported filters, retrieval, provider failures and privacy.
- Assistant and existing JavaScript suites: 344 passed at the Assistant checkpoint.
  Source rendering, cancellation, stale responses, safe links and draft preservation
  are included. Later parcel/satellite work has its own verification receipt.
- After the final help text edit, knowledge retrieval and RAG checks passed again.
- After the parcel guide label update and formatting, 162 focused checks passed
  with 2,515 assertions across retrieval, LGU routing/conversations/aggregates,
  parcel presentation and architecture.
- A separate architecture check identified the fresh-installation date helper as
  missing from the declared shared support inventory; it is now registered there.
- Normal signed-in browser requests against isolated synthetic MariaDB records
  returned the expected scoped overview and Outside LGU row totals, source links,
  period and query timestamp. Desktop and emulated phone layouts and keyboard
  source-disclosure activation were inspected. This is not physical-device,
  screen-reader audio, staff acceptance or production verification.
- With added synthetic crop fixtures, normal browser questions returned two
  valid parcel-season records for 2026; “What about 2025?” retained the crop task
  and correctly returned zero records for that year, with matching evidence.

The browser harness has isolated keys, sessions, storage and synthetic data.
Provider traffic is blocked or explicitly faked; no live-provider accuracy or
production data acceptance is claimed by these checks.

## Release dependencies

No migration, dependency, provider setting, model change or operational data write
is required for this follow-up. Release the Assistant and Reporting PHP, knowledge
corpus, shared Blade partial, CSS and JavaScript together. The source links rely
on the pending scoped farmer workbook section route/view, so include that existing
change in the reviewed release or do not release these links independently.

Build optimized autoloading and the normal configuration/route/view caches after
the reviewed deployment. Mirror the public CSS/JavaScript where hosting uses a
separate web root. Preserve general cache, provider counters, session namespaces,
environment values and existing data. The separate baseline/fresh-installation
work does not authorize running the full migration chain against production.

See [Office Assistant](OFFICE_ASSISTANT.md), [help RAG](ASSISTANT_RAG.md),
[workbook sections](FARMER_WORKBOOK_SECTIONS.md) and
[deployment instructions](GITHUB_DEPLOYMENT.md).
