# AgriGOV farmer IDs

## Green-and-gold registry card design — September 25, 2026

The front and back card previews, print layout, digital dialog, and PNG exports now share a DA-aligned green-and-gold treatment. The front uses a pale green/yellow gradient with subtle crop-line artwork, the AgriGOV wordmark, the Department of Agriculture logo, and the Philippine coat of arms beside the heading. The QR code remains on a plain white panel for reliable scanning. The back keeps parcel addresses, classifications, registry identifier, and the read-only map QR flow.

The coat-of-arms raster is stored at `public/images/branding/philippines-coat-of-arms.png`; the supporting background is `public/images/branding/farmer-card-background.svg`. The page includes attribution to the [Wikimedia Commons source](https://commons.wikimedia.org/wiki/File:Coat_of_arms_of_the_Philippines.svg) and [CC BY-SA 2.5 license](https://creativecommons.org/licenses/by-sa/2.5/). The card footer continues to state that it is a local agriculture registry card and not a substitute for a Philippine national government ID. The artwork is a visual redesign only: farmer data, QR destination, municipality authorization, address filtering, print size, and download behavior remain unchanged.

The card uses `farmer-id-card.css` for shared screen/print styles. Canvas exports load the same background and logos before drawing; if an artwork asset cannot load, the export fails with a visible retry message rather than producing an incomplete ID. No database migration or record update is required.

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
