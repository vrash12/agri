# AgriGOV farmer IDs

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
