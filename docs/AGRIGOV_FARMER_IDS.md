# eAgriLGU farmer IDs

October 20 full-field farmer-card revision (deployed in runtime `ddcc8d8`): the landscape
extends to every edge on both sides without boxed information panels. Dark
forest-green text sits over a continuous warm wash in the shared background;
the QR keeps a small opaque cream scan area. Logo white margins blend into
the landscape using matching CSS/canvas multiply compositing; original uploaded
assets are retained unchanged. The larger QR panel is transparent. The front uses only the standalone agriculture mark on the left, retains
the issuing LGU seal on the right, labels the identifier Registry ID and adds
recorded declared area and mapped-parcel count below the photo. The back has no
system logo or wordmark; its header shows issuing-office details and available
contact channels, with return/lost-card/record-correction guidance above the ink
signature area. The existing record creation date is labeled Registered.
Screen/print and canvas use the same artwork version `20261020-open-field`.
No record, schema, permission, QR destination or parcel-address rule changes.

Sharp-mark follow-up — October 21, 2026 (local, not deployed): the farmer-card
front now uses the existing transparent 1,254 × 1,254 `eagrilgu-mark-v1.png`
source instead of the 236 × 236 compatibility raster. The same versioned source
is used by the HTML/print card and canvas PNG export; canvas downsampling enables
high-quality smoothing. The shared site compact mark and all stored branding
namespaces remain unchanged.

Logo/QR background follow-up: CSS and canvas both blend white logo pixels with
the landscape using multiply compositing. The larger white QR panel is removed;
a uniform cream `#f4f2d8` area remains beneath the QR and its quiet margin. Stored
logos and QR payloads are unchanged. Both synthetic exported and screen-rendered
QR codes decoded to the same map URL at 505 and 650 pixels card width; the export
also passed at 856 and 1011 pixels, and the screen version at 850 pixels. The 13
JavaScript tests and Blade compilation passed. No project dependency was added;
the decode checker was installed only in the temporary preview directory.

Panel-free follow-up: removed the header/detail/signature white rectangles and
the ID chip fill. Sector tags use outlines only. Screen and canvas use the same
dark text palette and shared full-surface gradient. Synthetic front/back screen
and PNG exports were inspected; text-region raster samples have minimum
contrast 6.60:1 for main values and 4.95:1 for labels against the artwork. This
is a targeted contrast check, not a full accessibility certification. The 13
JavaScript card tests, Blade compilation and diff checks passed again.

Verification: 34 card/LGU settings tests (268 assertions), 13 JavaScript
identity/address/issuer tests, Pint, Blade compilation and whitespace checks
passed. Synthetic desktop/mobile previews and both 1011 x 638 PNG downloads
were inspected. Missing front artwork blocked export. Physical printing and live production rendering were not performed.

## Current revision — October 20, 2026 (deployed)

The LGU issuer, field-artwork and Poppins revision deployed and passed verification in runtime `fed7289`. Live server checks rendered two existing cards including Ramos; scoped settings, public routes and all eleven mirrored public assets passed. Existing operational rows/schemas and environment/session/cache/provider state were preserved. Physical printing and interactive live PNG download were not repeated. See [the deployment receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md). The implementation runs in the current Laravel 12/PHP 8.3+ modular application. Historical sections below retain earlier names and release details; the current behavior is defined here.

Screen, print, digital dialog and 1011 × 638 PNG downloads share the outlined Poppins Bold **eAgriLGU** wordmark: the owner's corn-and-field mark replaces **A**, the lowercase **e** is agriculture green, and griLGU is green. `eagrilgu-wordmark-v4.png` is rasterized from the matching SVG source. One wordmark appears per card side. The front title is bounded and centered between branding and the issuing logo; the footer names local agriculture services. Body/card data keeps its existing dedicated typesetting.

The shared `farmer-card-background.svg`, `farmer-card-front-art.svg` and `farmer-card-back-art.svg` supply a visible green-field landscape and readable panels. CSS/print and canvas use the same versioned files. The white QR panel and quiet zone stay clear of decoration. Physical cards remain 85.6 × 54 mm. Missing artwork or text exceeding safe export space produces a clear failure rather than an incomplete download.

## Issuer settings and seals

`Settings → LGU profiles, websites & IDs` stores the approved office logo, heading, address, website/contacts and optional printed signatory. `App\Modules\Farmers\Application\FarmerCardIssuer` resolves the farmer's numeric municipality relationship and supplies one payload to HTML and canvas. Full office and parcel-address details remain on the print sheet. Authorized same-origin logo delivery prevents remote-image dependencies.

A configured LGU logo takes precedence. Without one, `FarmerCardEmblem` keeps the supplied municipal seal only for active Ramos under active Tarlac; other offices use the DA logo. Ramos retains its municipal heading when unconfigured. The Republic emblem/heading is removed from all cards. Agency seals and application branding remain distinct.

`FarmerCardSignatory` prefers a saved profile's paired printed name/title, including an intentionally blank pair that omits the office line. Only offices without profiles use the municipality/province-matched fallback in `config/farmer_card.php` (Ramos: Engr. Dennish C. Pascua, Head Agriculturist). Every back prints the farmer's signature line above the printed name; office lines appear only when configured. Signatures are added in ink after printing, with no signature image stored.

Unconfigured offices keep fallback rendering, including when the profile table is absent. Printed/downloaded cards retain details present when generated. Profile changes affect subsequent rendering only. See [LGU_PROFILE_SETTINGS.md](LGU_PROFILE_SETTINGS.md) and [LGU_PUBLIC_WEBSITES.md](LGU_PUBLIC_WEBSITES.md).

## Data and release safeguards

`FarmerCardLocations` retains complete distinct, non-delisted, same-municipality imported parcel addresses; residence or mapped-plot names are never substituted. Long names, issuer fields, parcel addresses and signature captions retain bounded wrapping and incomplete-export refusal. Farmer identity data, `AGRI-F-######`, external RSBSA/FFRS values, role scope, public QR destination and the disclaimer that this is not a national government ID remain unchanged.

Deploy the modular issuer/signatory/emblem services, controller, view, styles, v3 branding and three SVG artwork sources together. Mirror public assets with 0644 permissions and rebuild autoload/configuration/route/view caches without clearing provider budgets. The branding/design needs no data migration; enabling profile/website settings requires only their two explicitly named additive migrations after backup. Do not run unrelated pending migrations or populate office data automatically. Both named migrations were applied after backup and their new tables remain empty. Current verification and live status are recorded in [the October 20 receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md).

## Historical artwork and identifier releases

The dated sections below describe earlier artwork and config-only signatory behavior. They are retained for provenance and are superseded by the current issuer/branding rules above.

The October 12 Ramos seal/field-artwork follow-up deployed in runtime `d89aa10`. It introduced the active Ramos/Tarlac seal resolution and released shared artwork, signature lines, HTML/canvas rendering and five mirrored assets together without changing farmer records, identifiers, QR destinations, accounts or stored signatures. Its national-emblem treatment for other offices is superseded by the October 20 DA fallback above.

## Field-background refinement — October 10, 2026 (local)

The front footer's compact AgriLGU corn mark sits directly on the green field-row band, without a white tile. CSS uses a transparent background, and canvas draws the same transparent PNG directly. The back wordmark and QR panels retain their white surfaces.

The subsequent Ramos seal follow-up adds `FarmerCardEmblem`, injected into the card controller and called after scope authorization. It resolves the supplied seal only by active Ramos municipality and active supervising Tarlac province, never by a fixed database ID or the legacy province string. Ramos cards use Municipal Agriculture Office / Ramos, Tarlac in HTML and canvas; other offices retain the Philippine coat of arms, provincial heading and Wikimedia attribution. The local-seal credit is displayed only on Ramos cards. The two renderers use the same resolved asset; its 98 × 98 canvas placement preserves the circle instead of stretching it to the national emblem's 98 × 109 ratio. Missing seal artwork still prevents export. AgriLGU branding and the DA logo are unchanged.

The user supplied `308617779_175968198334666_8293117291324502804_n.jpg`, copied byte-for-byte to `public/images/branding/ramos-municipality-seal.jpg`. It is a 405 × 405 JPEG, has no EXIF tags, and has SHA-256 `7c92f55808e3547919f3bf0dcd6277b59ea97d5c44f12f373f1c4e1dc90e0b0f`. No image editing or reconstruction was performed. Two added tests cover actual HTML/canvas data, other offices, a same-named municipality in another province, and inactive supervising scope. Synthetic desktop preview and the actual 1011 × 638 front download show the seal and municipal heading.

The shared background is now an original SVG illustration of rice-field beds, irrigation channels, distant hills and rice stems. The two overlays place light reading panels above it, with a thin gold border and patterned green header/footer. The back keeps the entire QR/frame area opaque white and the signature strip almost white. Artwork uses the existing 1011 × 638 coordinate system; physical print remains 85.6 × 54 mm. The illustration is decorative and does not represent the farmer's actual land.

HTML and canvas load the same three artwork files with `?v=field-20261010-1`, preventing the earlier cached pattern from appearing after release. Exports retain their fail-safe artwork loading. Label line height, ID-chip layout and spacing keep every front field inside the reading panel. All six classification chips fit in one line so the printed QR panel does not displace the signature space. Digital close/side controls have 44px targets. Multibyte-safe names, initials and province/municipality capitalization preserve accented characters and prevent invalid UTF-8 in the canvas payload. Signatories, agency emblems/attribution, QR destination, identifiers and complete parcel-address safeguards are unchanged.

Verified locally with disposable SQLite fixtures: 14 focused card/signatory/workspace tests and three JavaScript address/overflow tests passed; Pint, PHP syntax, Blade compilation and whitespace checks passed. Desktop and approximately 390 CSS-pixel layouts were inspected with three complete slash-separated sample parcel addresses and all six classifications. Actual front/back downloads are 1011 × 638; the back QR decoded to the same fixture map link at widths 1011, 856, 650, 505 and 428 pixels and with a light blur. The review also checked missing-photo initials and full signature captions. No real farmer data was used. Physical printing and a live authenticated card export were not performed.

The field-artwork/seal follow-up above supersedes this earlier local-only note.

Ramos follow-up verification: card/signatory, identifier and province-card access checks passed in the focused run, including same-name province isolation, same-municipality address rules and rejection of foreign/veterinary access. The broader run passed 30 tests and failed one existing records-page wording assertion (`ProvinceReportingScopeTest::test_municipal_staff_can_open_an_empty_profile_only_in_their_municipality`, expecting "Locked to your assigned municipality"). That route and wording were not changed by this card update; the broader suite is not claimed as fully passing.

The October 7 artwork and signature-line changes deployed on Hostinger on October 8, 2026 in runtime `18cf956`. Both SVG overlays and the stylesheet were mirrored to the served public directory and verified over HTTP; new public SVG files require 0644 permissions. The authorized server render confirms Ramos office and cardholder lines without altering account or farmer records. See `docs/GITHUB_DEPLOYMENT.md` for backup, verification and live print/PNG limitations.

## ID-style design details — October 7, 2026

Two artwork overlays sit above the existing `farmer-card-background.svg`: `farmer-card-front-art.svg` (top green/gold strip, a fine-line guilloche rosette, grain-ear accents beside the title, a microtext rule in place of the plain divider, gold photo corners, and the patterned footer band) and `farmer-card-back-art.svg` (the patterned header band with grain ears and a faint rosette). The CSS card layers each overlay over the background, and the canvas export draws the same files, so screen, print and PNG stay identical; the export still refuses to run if any artwork fails to load.

Per-farmer details are drawn by both renderers: a 20% grayscale repeat of the photo (shown only when a photo exists), the AgriGOV ID in a highlighted chip, the AgriGOV mark and a shield icon in the footer, icons beside each back-of-card label from one shared path list, classification pills (falling back to one fitted line when they need more than a row), and scan-frame corners kept clear of the QR code's own margin. The QR panel stays plain white; renders of the new back decode at the same sizes and blur levels as the previous design. No data, QR destination, permission or address rule changed, and no migration is needed. Deploy the two new SVGs with the view and CSS, mirrored to both Hostinger public directories.

### Cardholder and office signatures

Every card's back ends in one row: the cardholder's signature line on the left (the farmer's printed name in capitals, captioned "Cardholder's signature"), the not-a-national-ID notice and issue date in the middle, and the office signatory on the right when one is configured. Both lines sit at the same fixed height in the printed card and the canvas export, with clear space above them for an ink signature. Farmers sign their printed card when it is handed over; no signature is captured or stored, and the digital ID and PNG downloads show the unsigned lines. The printed card now uses the export's shorter notice wording so both versions match.


The back of a Ramos, Tarlac card prints a signature line with **ENGR. DENNISH C. PASCUA** and **Head Agriculturist** beneath it; the card is signed by hand after printing, and the digital ID and PNG downloads show the unsigned line. Signatories live in `config/farmer_card.php`. `App\Support\FarmerCardSignatory` matches the farmer's municipality by name and its active supervising province, never the legacy province string or a database ID, so a same-named municipality in another province and every unlisted office keep the original unsigned footer. An incomplete entry prints nothing. To give another office its own signatory, add an entry with its municipality, province, name and title, then run `php artisan config:cache` on the server.

To leave clear space above the signature line (about 5 mm at CR80 size), the back header is 18 px shorter (`header` height 18.65%) and the body moves up accordingly; the QR code still decodes at the same sizes and blur levels as before. `FarmerCardSignatoryTest` covers Ramos, other offices, a same-named municipality under another province, inactive scope, incomplete entries and the rendered card.

## Green-and-gold registry card design — September 25, 2026

The front and back card previews, print layout, digital dialog, and PNG exports now share a DA-aligned green-and-gold treatment. The front uses a pale green/yellow gradient with subtle crop-line artwork, the AgriGOV wordmark, the Department of Agriculture logo, and the Philippine coat of arms beside the heading. The back uses the AgriGOV wordmark alone in its green header so the repeated title and identifier are not duplicated. The QR code remains on a plain white panel for reliable scanning. The back keeps parcel addresses, classifications, and the read-only map QR flow.

The coat-of-arms raster is stored at `public/images/branding/philippines-coat-of-arms.png`; the supporting background is `public/images/branding/farmer-card-background.svg`. The page includes attribution to the [Wikimedia Commons source](https://commons.wikimedia.org/wiki/File:Coat_of_arms_of_the_Philippines.svg) and [CC BY-SA 2.5 license](https://creativecommons.org/licenses/by-sa/2.5/). The card footer continues to state that it is a local agriculture registry card and not a substitute for a Philippine national government ID. The artwork is a visual redesign only: farmer data, QR destination, municipality authorization, address filtering, print size, and download behavior remain unchanged.

The card uses `farmer-id-card.css` for shared screen/print styles. Canvas exports load the same background and logos before drawing; if an artwork asset cannot load, the export fails with a visible retry message rather than producing an incomplete ID. No database migration or record update is required.

The card redesign was installed on Hostinger in runtime `d796071f6afff34bd609c92bae85a3fdbd793e29` on September 25, 2026. Production checks confirmed the homepage and login returned HTTP 200, all eight changed public assets matched their `public_html` copies, and the database remained unchanged.

## Outcome and status

Every saved farmer uses AGRI-F-###### as the primary displayed and searchable
identifier. The six digits are a minimum width: record 123 is AGRI-F-000123,
and record 1000000 is AGRI-F-1000000. IDs do not depend on name, municipality,
RSBSA, or whether portal access has been issued. Unsaved records have no ID.

Implemented on September 21, 2026 and committed to the repository on
September 24, 2026. Installed on Hostinger in runtime `bd75ced` through GitHub and a fast-forward pull.
No migration, backfill, portal-account issuance, credential change, or database
key replacement is needed. Existing farmers receive the display ID immediately
when the updated code runs; new farmers receive it after persistence.

## Implementation

App\Support\FarmerIdentifier formats the numeric key and resolves complete
AgriGOV or legacy PAIS-FRM-... search terms to indexed numeric equality.
Matching is case-insensitive and accepts surrounding whitespace. Zero, malformed
IDs and values beyond PHP's integer range are rejected by the parser.
Farmer::agri_gov_id and FarmerPortalAccount::canonicalLoginId() share this
formatter. Farmer::registry_id retains the old printed-card representation.

Registry, card, farmer history, public token map, authenticated map, portal,
cooperative membership, machinery holder and beneficiary picker displays use
the AgriGOV ID. Existing FFRS/RSBSA values remain separate external references.
The authenticated map payload adds only the derived agri_gov_id; no additional
contact or birth-date data is added to bulk map results.

Farmer directory, map lookup, beneficiary picker, assistance, harvest and
machinery searches accept AgriGOV IDs. Existing municipality/province/region
scope is applied before ID matching, aggregates and pagination. Numeric route
parameters, selection values, foreign keys and random public QR tokens remain
unchanged. Official workbook layouts, import matching, and compatibility fields
are retained.

An AgriGOV ID is an identifier, not proof of identity or a credential. Farmers
still need staff-assisted activation and their own password to sign in.

## Existing truncation and local repair

The owner reported having emptied the farmers table in both environments.
A read-only local check confirmed zero farmers and 41 assistance records whose
farmer links no longer resolved. The owner explicitly authorized clearing only
these broken local links while preserving assistance history.

The local repair verified a private backup, locked the affected rows, set the
41 stale farmer_id values to null, refreshed their updated_at values, and
recorded an owner-only audit event. It verified that every other history field
and all 41 records were preserved. There were no linked local parcel, cooperative
membership, machinery, harvest or portal-account rows at preflight.

No production truncation or cleanup was performed by this change. Check
Hostinger for orphaned farmer links before creating new farmers after a reset:
reusing numeric IDs can otherwise attach old history or portal access to a new
person. Treat any production repair as a separate, explicitly authorized
operation with a verified backup. Never disable foreign-key checks to install
this display-ID update.

## Deployment

Back up the deployed application, review the focused diff, and deploy the new
identifier helper together with the changed farmer/portal models, farmer picker,
four controllers, views, and two JavaScript assets. The commit contains only this
feature; the CAR boundary, geofence-appearance and sign-in-notice work it was
originally held back from was released separately and is already deployed.

Mirror farmer-finder.js and farmer-picker.js to both
Hostinger public directories before refreshing compiled views. No new
environment values, schema changes or Composer dependencies are required.

Verify a saved farmer's ID on the registry, card and map, lookup by both current
and legacy ID, beneficiary selection, municipality isolation, and unchanged
portal activation/sign-in behavior. Existing portal login IDs are not rewritten.

## Verification

Focused isolated SQLite/PHP tests cover new and existing farmers, protected
numeric links, portal-account non-issuance, immutable IDs after profile edits,
current/legacy searches, municipality isolation and limited map serialization.
JavaScript tests cover map identity selection, picker data and numeric form
values. Verification completed with 33 PHP tests / 372 assertions across the
identifier unit suite, isolated identifier workflow and existing portal suite;
31 JavaScript tests passed across finder, picker, map rendering and portal.
Pint, changed-PHP syntax checks, Blade compilation, farmer route inspection and
diff whitespace checks passed. Synthetic SQLite browser previews verified the
registry ID, generated digital card, and staff portal ID before access issuance.
No real farmer was created for verification. Live Google Maps, production
acceptance and the broader legacy MySQL test suites were not run for this change.
