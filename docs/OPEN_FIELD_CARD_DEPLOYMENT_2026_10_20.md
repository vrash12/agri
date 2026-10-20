# Full-field farmer-card deployment - October 20, 2026

Runtime `ddcc8d829e709a54d56569bf66024ce3a9683c4d` was pushed to GitHub main and
deployed through Hostinger fast-forward pull from `eec15e4`.

## Released design

Both sides share continuous field artwork and dark forest-green text with a
full-surface contrast wash. White information boxes are removed. The front uses
the standalone agriculture mark, existing issuer seal, declared area and mapped
parcel count. The back replaces system branding with issuing-office/contact
information and return/lost-card/correction guidance. Logo white margins blend
through matching CSS/canvas multiply compositing; uploaded images are unchanged.
The QR uses a compact cream quiet area instead of a large white panel.

## Preservation

A private verified backup covers 27 tables / 10,004 rows (3,413,304 compressed
bytes), SHA-256 `4b8af7634dd9cb601405193f29dbd23fe36d412d31d5c59dcd11b6f676face32`.
Baseline SHA-256: `cf8cc047bf42e73a6c7d41e2896e977db74263a887357e3c0ffff222a5fc6fd5`.
Private recovery includes database, environment, source and prior served assets.
Every original schema and row fingerprint matched after release and read-only
renders. Environment bytes, session/cache namespaces and provider counters
matched. No migration, account change, provider call, dependency installation or
general-cache clear ran. Only compiled views were rebuilt. Existing untracked
server files were retained.

## Verification

- Local: 34 PHP tests / 268 assertions; 13 JavaScript card tests; Pint, syntax,
  Blade compilation and diff checks passed.
- Synthetic front/back desktop and mobile previews and actual PNG exports were
  inspected. Exports remain 1011 x 638. Missing artwork blocked incomplete export.
- Synthetic exported QR decoded to its unchanged map URL at card widths 1011,
  856, 650 and 505; screen QR at 850, 650 and 505. No project dependency was added.
- Text-region artwork samples measured minimum 6.60:1 contrast for primary values
  and 4.95:1 for labels. This is not a full accessibility certification.
- Production: three scoped settings/layout renders and two existing authorized
  card renders passed in a read-only transaction with memory-only sessions.
  Standalone front mark, back office/help text, shared artwork, blended export,
  existing Ramos seal and national-ID disclaimer passed. Public directory and
  guest policy denial passed.
- All four served CSS/SVG assets match Git byte-for-byte over HTTPS. Welcome,
  office sign-in, farmer sign-in and LGU directory returned 200.
- Maintenance is off. Production tracked files are clean.

Physical printing and interactive live authenticated downloads were not repeated.
