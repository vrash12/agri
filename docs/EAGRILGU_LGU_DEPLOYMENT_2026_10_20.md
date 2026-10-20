# eAgriLGU branding, farmer IDs and LGU settings — October 20, 2026

Runtime `fed7289e595b4539086f7efe3617905790d8167d` was pushed to GitHub main and
installed on Hostinger by `git pull --ff-only origin main` from
`db6935b8d043691a79aa676a273c036ebecfec2f`. The owner explicitly authorized this
release. Production remains on Laravel 12.69.3 / PHP 8.3.35; current Assistant,
parcel agronomy, monitoring, charts and modular application features were retained.

## Released behavior

- Shared site branding and ID screen, print, digital and PNG output use outlined
  **Poppins Bold (700)**. The owner's corn-and-field mark replaces **A**, the
  lowercase **e** is blue/red, and `griLGU` is green. Office sign-in uses the full
  wordmark; compact navigation and browser icons use the supplied mark.
- Farmer IDs use the green-field artwork, readable information panels, white QR
  area and ink-signature lines. The Republic emblem and heading are removed.
  Configured LGU logos/details/signatories take precedence; unconfigured active
  Ramos, Tarlac retains its municipal seal, and other offices use the DA fallback.
  Stored farmer identifiers, QR destinations, parcel-address protections and
  municipality permissions are preserved.
- **Settings → LGU profiles, websites & IDs** provides scoped office profiles,
  private raster-logo uploads, issuer details, optional signatory pairs and LGU
  website content/publication. Roles, stale-edit protection, file validation and
  safe audits are enforced on the server.
- The public directory is `/lgus`; published offices use `/lgus/{slug}`. No
  profiles or websites were populated or published during deployment. Tenant
  hostnames remain disabled until provider wildcard DNS/HTTPS is configured;
  central sessions remain host-only.

## Backups and preservation

Verified private database backup: **25 tables / 9,978 rows**, **3,411,829 compressed
bytes**, SHA-256
`1f2ac48f1de6bade2455d2d835751c1975f60fea80d06b1fb5ee98388a0861c7`.
Private recovery files are outside the document root at
`/home/u330835917/.private/eagrilgu-20261020`: database backup, original environment,
prior tracked source, affected served assets, baseline and acceptance receipts.
No credentials or personal records are included in this documentation.

All existing table schemas and complete row fingerprints matched before and
after release and authorized rendering. The environment bytes, effective
session/cache namespaces and provider counters were preserved. Only these two
reviewed migrations ran, each by explicit path with `--force`:

1. `2026_10_19_000100_create_municipality_profiles_table.php`
2. `2026_10_19_000200_create_municipality_websites_table.php`

They created two empty tables with unique municipality relationships and
restrictive foreign keys; the website slug is unique. Exactly two migration
receipts were added. No legacy migration, geography import, seeder, account
provisioning, password change, dependency update or operational data write ran.
The general application cache was not cleared.

## Verification

- **101 focused PHP tests / 2,025 assertions** passed on the current application,
  including LGU permissions/isolation, validation, concurrency, public/tenant
  routing, file delivery, card/signatory rendering, portal, navigation and module
  boundaries. After switching office sign-in to the full wordmark, its existing
  seven-test suite passed again with 77 assertions.
- All **328 JavaScript tests** passed. Pint, syntax checks for 33 changed PHP
  files, Blade compilation, route checks and whitespace/secret reviews passed.
- Live checks rendered three authorized scoped Settings pages and two existing
  farmer cards, including Ramos, in a read-only transaction with memory sessions.
  The empty public directory rendered, and guest policy access was denied.
- Homepage, office login, farmer-portal login and `/lgus` return HTTP 200 with the
  Poppins wordmark. Guest `/settings/lgu` redirects to login; an unpublished slug
  returns HTTP 404.
- All **11 changed public assets** were mirrored to `public_html` with matching
  source/served file bytes. Nine text/SVG HTTPS responses match Git bytes. The two
  PNG responses are losslessly recompressed by Hostinger's CDN: dimensions and
  every decoded RGBA pixel match the committed originals.
- Optimized production autoloading and configuration/route/view caches were
  rebuilt. The site reopened successfully; the tracked production tree is clean.

Browser interaction, physical CR80 printing, real provider calls, populated LGU
content and wildcard DNS/HTTPS were not exercised by this release. Scoped
controller rendering and HTTP checks do not replace those acceptance activities.

## Recovery

Use the private source, served-asset and database backups for a separately reviewed
recovery. Revert runtime routes and code before considering removal of the new
tables. Once profiles/websites are entered, preserve their rows and private
`lgu-logos` / `lgu-covers` bytes before any rollback. Do not run a blanket migration
rollback against the legacy database baseline.
