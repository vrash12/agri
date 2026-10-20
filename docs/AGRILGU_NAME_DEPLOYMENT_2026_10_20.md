# AgriLGU name and LGU interface deployment - October 20, 2026

Runtime `e804e575fa9cf07e227b494fb95893298736d569` was pushed to GitHub main
and installed through Hostinger fast-forward pull from `248cb43`. GitHub's
existing `8d5b497` LGU interface commit was preserved; overlapping public views
retain its design with the new AgriLGU name. The application release and this
receipt are separate commits.

## Released behavior

Visible site, sign-in, portal, registry and Assistant copy now reads AgriLGU.
The supplied agriculture mark replaces A, followed by green outlined Poppins
Bold griLGU. The shared versioned PNG is 1,224 x 416. The front card retains its
standalone mark and the back retains issuing-office information without system
branding. Stored identifiers, QR destinations and role/scope rules are unchanged.

The LGU settings directory, office profile and website editors include the
existing interface refresh: grouped sections, issuer preview, image previews,
character counters, optional entry disclosures and sticky save actions. The
public directory/page includes contact summaries and updated responsive styles.
No records were created, published or modified by this release.

## Preservation and recovery

A private verified database backup covers 27 tables / 10,008 rows, 3,413,401
compressed bytes. SHA-256:
`7f6ab165111dbc60f6f3dc31df8c9e09c3c2879b4d8301e22e6ff1fc65360857`.
Baseline SHA-256:
`4f59330eebd153bfefcb54c71dd1dd3b10cf7a1014d3d27f2708953e2bf69316`.

Private recovery files are outside the document root at
`/home/u330835917/.private/agrilgu-name-20261020`: database, environment, source,
prior served assets and verification receipts. Every original table schema and
row fingerprint matched before and after read-only rendering. Environment bytes,
session/cache namespaces and provider counters matched. No migrations, private
APP_NAME changes, account changes, dependency installation, live provider calls
or general-cache clearing ran. Optimized autoloading and compiled views were
rebuilt. Existing untracked server files were retained.

## Verification

- Combined release: 47 focused PHP tests / 401 assertions passed, covering LGU
  settings/public-site behavior and scope, branding presentation, welcome and
  card signatory behavior. Blade compilation and diff checks passed.
- Earlier rename verification: 71 JavaScript tests passed; PHP syntax and Pint
  passed. The broader 914-test PHP run had ten missing-province fixture errors
  and one missing-audit-table fixture failure, reproduced against pre-rename
  tests. This release does not claim that broader suite fully passed.
- Production PHP 8.3.35: three scoped settings directory renders, six profile /
  website editor renders and two authorized existing card renders passed in a
  read-only transaction using memory-only sessions. The public directory and
  guest policy denial passed. All rows were rechecked after rendering.
- Seven public assets were mirrored and matched on disk. Over HTTPS, SVG, CSS,
  three JavaScript files and photo credits match Git bytes. Hostinger's image
  optimization recompresses the PNG, whose decoded 1,224 x 416 RGBA pixels match
  the source exactly (pixel SHA-256
  `d8221ca35b00e65d470b48eeab020e52e56c5fd77ed714a6b5705130155e2d37`).
- HTTPS welcome, office sign-in, farmer sign-in and LGU directory succeeded,
  reference the new wordmark and contain no old eAgriLGU display name.
- Maintenance is off; production tracked files were clean after installation.

Physical printing, interactive authenticated browser editing/downloads and live
AI provider evaluation were not repeated. Optional tenant DNS/HTTPS remains a
separate hosting configuration.
