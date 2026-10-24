# Verified operational release — October 25, 2026

Runtime `cdbc85c1851c94491bfa0d495eabf31efa15fb19` was pushed to GitHub
main and installed on Hostinger by fast-forward pull from
`69cb9f583e085597e810be6cf487e1b276291ab9`, following the owner's explicit
deployment request. Production is online at https://agritarlac.online.

## Released behavior

- Scoped local Assistant reports cover workbook sections, cooperatives, machinery,
  seasonal crops and office setup, alongside existing registers. Replies include
  source links, scope, period and query time; private records stay local. Unsupported
  filters clarify, and current-only reports refuse historical-year interpretations.
  Curated help requires its primary retrieved source. Agriculture-only scope,
  gradual typing, cancellation, draft preservation and accessible sources are live.
- LGU dashboards show mapping, assistance reach and monthly input-weight charts
  with readable figures and explicit units/periods. Parcel actions and crop context
  are clearer, with separate loading, empty and failed states.
- Satellite checks have beginner guidance, descriptive choices, complete-UTC-day
  presets, dated results and explicit retries. Older responses cannot restore stale
  results; browser Back preserves retry deadlines without restarting requests.
- Full-name map/beneficiary searches match across name fields, support surname-first
  input and invalidate older responses immediately after edits or clearing.
- Outside LGU and Delisted workbook sections are available within existing scope.
  Source rows remain distinct from people; delisted rows have no active-card actions.
- The pending compact LGU footer, LGU-branded office sign-in/central dashboard
  flow and higher-resolution front ID mark were included.
- Fresh-installation schema history, isolated test setup and guarded owner-bootstrap
  source are included for new empty environments. **No production migration or
  owner bootstrap ran.** Existing migration receipts were preserved exactly.

## Recovery and preservation

A private verified compressed database backup preceded installation, covering
**27 tables / 10,028 rows**, **3,414,652 bytes**, SHA-256
`128cb07c0c3b9201e678f004664c1ca3df0953161cd735b651a9b56f0a6c6770`.
Recovery files are outside the document root at
`/home/u330835917/.private/operational-ux-20261025`, with restricted permissions.
The environment, previous tracked runtime and replaced public assets were also
backed up privately. Credentials and operational rows are not included here.

All existing row/schema fingerprints and the complete table set matched after
verification. Environment bytes, application/session/cache identities, provider
configuration and provider counters matched. No accounts, operational data,
dependencies or provider settings were changed. No general-cache clear ran.
The pinned isolated demo was preserved and was not updated by this release.

Optimized autoloading and configuration/route/view caches were rebuilt. Nine
changed public assets were mirrored to the hosting web root with normal read
permissions. The existing high-resolution card mark already matched in both
public directories. Scheduled jobs and hosting/DNS settings were preserved.

## Verification

- Broad local PHP checkpoint: **2,093 passed / 36,374 assertions**, with seven
  MySQL/MariaDB-specific logical-backup checks skipped on SQLite.
- After the final refinements: **1,078 affected PHP tests / 9,442 assertions**
  passed. **357 JavaScript tests** passed. These overlapping runs are not additive.
- Six isolated MariaDB finder checks passed; Pint, changed syntax, Blade compilation,
  affected routes and diff checks passed.
- Live read-only server checks passed for dashboard charts, shared chat, both
  workbook sections, eight local reports with scoped evidence, historical-data
  refusal, Filipino routing, full-name search, foreign-LGU search denial, satellite
  dates and the ID mark. These checks issued no external provider requests.
- The server-only verifier initially used a PHPUnit assertion unavailable in a
  production dependency installation. It was replaced with a normal condition,
  and the complete verification and preservation checks passed on the repeat run.
- Public HTTPS returned 200 for the homepage, central sign-in, LGU directory,
  Mayantoc website and its scoped central sign-in, and the existing isolated demo
  sign-in. A guest requesting Delisted was redirected to central sign-in.
- All nine served CSS/JavaScript responses match the committed release bytes.
  The CDN re-encodes the existing PNG; its served dimensions remain 1,254 × 1,254.
  Origin copies match the committed original. Browser/working-copy line endings
  were not used as the expected asset bytes.

Fresh browser inspection was unavailable because browser access was disabled in
Work settings. Earlier synthetic desktop/mobile and keyboard observations remain
documented in [the UX receipt](OPERATIONAL_UX_2026_10_25.md). Physical devices,
screen-reader audio and staff sign-off remain outstanding. Live Google Maps
interaction and new model/Copernicus calls were not repeated for this release.
No model training or unrestricted ingestion of private LGU fields was performed.

See [LGU retrieval](ASSISTANT_LGU_RECORDS.md), [workbook sections](FARMER_WORKBOOK_SECTIONS.md),
[fresh installation](FRESH_INSTALLATION.md) and [deployment guide](GITHUB_DEPLOYMENT.md).
