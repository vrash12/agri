# Verified Assistant and Registry insights release — October 26, 2026

Runtime `6cdf65a8aee3c5e0be451b3a218aae4fec6d7e09` was pushed to GitHub main
and installed on Hostinger by fast-forward pull from `1010d53`, following the
owner's explicit deployment request. Verification completed at **05:11 PHT on
October 26** (`2026-10-25T21:11:22Z`). Production is online at
[agritarlac.online](https://agritarlac.online/), running PHP 8.3.36.

## Released changes

- Agriculture questions about harvest improvement, yellow crops and watering
  reach relevant reviewed guidance or useful topic choices. Multiple farm topics
  ask which to explain first; specific recommendations retain their evidence limits.
- A collapsed agriculture-topic picker prepares editable questions. Reply labels
  distinguish agriculture/system help, office records and guidance delivery.
  Veterinary choices remain restricted; private records stay local.
- Account-relative “our LGU” record questions work without widening access.
  Unsupported irrigation-assistance filters and named location suffixes clarify
  instead of returning broader totals.
- Registry insights use colorful server-rendered gender and ranked location bars,
  exact counts, shares of the filtered total and optional additional locations.
  The long mixed table and duplicate deferred charts are removed. Map scope,
  registry queries and permissions are unchanged.

## Recovery and preservation

The verified private database backup contains **27 tables / 10,030 rows**, with
**3,414,696 compressed bytes**, SHA-256
`1b55d3fef390a47c1df3377fee9bc68a7fac3346b8132111fb37341faa543c7f`.
Recovery files are outside the document root at
`/home/u330835917/.private/assistant-registry-20261026`, with restricted permissions.
The prior tracked runtime, replaced public assets and environment were backed up.

Every existing table, row fingerprint and schema matched after verification.
Migration receipts, accounts, environment bytes, session/cache identities and
provider settings/counters were preserved. No migration, operational data write,
dependency installation, provider request or general-cache clear ran. The
separately pinned demo was not updated; its public sign-in remains available.
Existing unrelated server files were retained.

Optimized autoloading and configuration/route/view caches were rebuilt. These
three assets were mirrored to `public_html` with 0644 permissions:

- `css/farmer-registry-insights.css`
- `css/office-assistant.css`
- `js/office-assistant.js`

## Verification

- Pre-release complete Assistant run: **1,043 PHP tests / 8,063 assertions passed**.
- Deployment checkpoint: **33 registry/architecture PHP tests / 1,465 assertions**
  and **77 Assistant/finder/workspace JavaScript tests passed**. These runs overlap
  previously documented checks and should not be added as a single test total.
- Pint, PHP/JavaScript syntax, isolated Blade compilation, route verification and
  whitespace checks passed before deployment.
- Live server-side reads verified filtered registry denominators, unchanged map
  totals, colored figures, the shared topic picker, canonical agriculture topics,
  targeted suggestions, local LGU report counts, unsupported-filter refusal,
  reviewed evidence and veterinary restrictions. No provider calls were made.
- Public HTTPS returned 200 for the homepage, central sign-in, LGU directory,
  Mayantoc website and isolated demo sign-in. Guest registry access redirects to
  central login. All three served CSS/JavaScript responses match committed bytes.

Earlier synthetic browser checks covered keyboard use, draft preservation,
numbered replies and measured narrow layouts. Fresh production browser visuals,
physical devices, screen-reader audio, staff approval and live-model answer quality
were not tested by this deployment. Screenshot capture was unavailable during the
local refinement. No model training or arbitrary private-data ingestion occurred.

See [Assistant refinement](ASSISTANT_AGRICULTURE_TYPING.md),
[Registry insights](OPERATIONAL_UX_2026_10_25.md) and
[deployment instructions](GITHUB_DEPLOYMENT.md).
