# Farmer workbook reference sections

Status: October 21, 2026 local implementation, not pushed or deployed.

Farmers navigation now includes **Farmer registry**, **Parcel map**, **Outside
LGU** and **Delisted**. The last two are read-only views of retained
`farmer_registry_source_rows`, not new active-farmer categories or eligibility
decisions. A selected municipality, province or region remains selected when
switching sections. Changing location retains the current section and search.

## Source reconciliation

The owner-supplied Ramos workbook SHA-256 is
`70d97152686977feaf4d6d5d43ed1e11d71ce403fc19a41f4ce5ac7c66531eae`, matching
the sole source hash already retained in production. Its header-excluded counts
match the existing database: 2,996 PARCEL LISTING rows, 133 OUTSIDE LGU rows and
245 DELISTED rows. No workbook upload, re-import or production write ran.
The 133 outside-LGU rows link to 68 distinct existing farmer profiles. All 245
delisted rows are unlinked. Source rows can repeat a farmer for commodities or
parcels, so navigation and page counts explicitly say **rows**.

The workbook and personal source data stay outside Git/public assets. Public
LGU websites and farmer portal responses do not expose these sections.

## Workflow and boundaries

- `GET /farmers/registry-sources/{status}` (`farmers.registry-sources.index`)
  accepts only `outside_lgu` and `delisted`.
- Existing authenticated office middleware and the Farmer `viewAny` policy run
  before any source access. System Owners, Regional Heads, scoped provincial
  Super Admins/staff and municipal heads/staff keep their existing read scope.
  Veterinary, evaluator and visitor identities are denied; farmer-portal
  authentication does not authenticate an office session.
- `FarmerWorkspace` validates geography; `MunicipalityAccess` scopes source
  queries before counts, word-based search and pagination. Active municipality
  and supervising-province choices are required. A forged municipal filter
  cannot expand a municipal account's assigned area.
- Search accepts at most 100 characters and matches original names across both
  uppercase parcel-sheet headers and lowercase delisted headers, plus retained
  FFRS/RSBSA/parcel references. SQL values are parameterized, fields fixed and
  `%`, `_` and `!` treated literally. Explicit SQL `ESCAPE` and lowercasing keep
  SQLite/MySQL behavior consistent, including JSON string collations.
- Default page size is 25, bounded to 10–100. Counts use database aggregation;
  only the current page's payloads and LGU labels are loaded. Page links retain
  search/location/size. There are no provider requests or map initialization.
- Each entry shows original name, identifiers, maintaining LGU, residence,
  source sheet/row and a native disclosure of every retained workbook field.
  Escaped output preserves blanks, zero values and original labels.
- Delisting reasons are taken only from explicit duplicate/deceased/inactive
  source markers. Multiple reasons remain visible; missing reasons are labeled.
  A delisted row never gains a profile/ID action, even with a stale linked ID.
- Outside-LGU addresses do not change municipality ownership. A linked profile
  action exists only when farmer and source row share the same numeric
  municipality. The destination retains its independent policy checks.
- Responses are private and non-storable with shared security headers. There
  are no writes, exports, reactivation, new account/ID generation, or automatic
  changes to current totals, parcel addresses, assistance or printed cards.
- Missing source table and empty results have distinct messages. Existing
  profiles remain usable without the source table.

Other LGUs see their own retained source sheets, or an explicit empty state.
This change does not modify the normal farmer workbook importer, which still
processes PARCEL LISTING / OUTSIDE LGU into canonical farmer profiles. It does
not automatically retain future uploads or provision reference rows for LGUs
without an approved source backfill. Do not re-import Ramos to populate these
pages; its reference rows already exist.

## Verification and release

57 isolated PHP tests / 1,641 assertions passed across the new section checks,
workspace hierarchy, source migration, regional/provincial isolation and module
architecture. Two workspace JavaScript tests passed. Tests cover office roles,
guest/other-identity denial, forged geography, search, pagination bounds,
repeated-row counts, original fields/XSS, invalid farmer links, missing table,
empty LGU and read-only preservation. Changed PHP was formatted with Pint;
Blade compilation, route registration, syntax and diff checks were performed.

Actual Blade pages were rendered with synthetic SQLite fixtures. Browser
preview was disabled in Work settings, so desktop/mobile visual inspection and
live HTTP checks of the new route remain outstanding.

No migration, dependency or environment change is required on the current
installation. Release the controller, application service, Blade page, shared
workspace navigation, routes and `public/js/farmer-workspace.js` together. Mirror
the script into both Hostinger public directories, refresh route/view caches
and verify own-LGU source counts and foreign-LGU denial after an explicitly
authorized GitHub/Hostinger release. Preserve the existing database and private
source rows. Earlier pending local changes require separate release review.
