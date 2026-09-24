# Farmer portal — first release and read-only records

## Workflow

1. Staff open Farmer registry → View profile/history → **Farmer portal access**.
2. Verify the farmer's identity using the office's identity-check procedure and confirm that the selected record belongs to them.
3. Every saved farmer already has an AgriGOV ID (`AGRI-F-######`). Issue portal access and privately provide that ID, activation page, and one-time code. The code is shown once and expires in 24 hours. No email or SMS is sent.
4. The farmer opens `/farmer-portal/activate`, enters the ID/code, chooses and confirms a password, then signs in at `/farmer-portal/login`.
5. For recovery, staff verify identity again and reissue the code. This revokes the previous password and sessions. Disabling access retains the farmer's operational records.

Installation creates no real farmer accounts. Birthdays, RSBSA numbers, or possession of a registry card cannot activate an account alone. Welcome and office login pages link to farmer sign-in.

The September 24 release (`bd75ced`) installed the shared office/farmer sign-in layout, farmer audience switch, simplified Farmers workspace and related public assets on Hostinger. It accepts the AgriGOV ID or unique RSBSA alias and password, does not display activation-code prompts, and keeps staff-assisted activation at its separate route. The release required no migration or account provisioning; focused portal and office sign-in tests passed and live login/assets returned HTTP 200.

The follow-up read-only records update is local and not yet pushed or deployed. It adds an overview with plotted-parcel area, seed/assistance count, harvest count and bounded recent activity; richer seed-release details; and `/farmer-portal/harvests` with an optional year filter, production, harvested area, owned parcel links and unit-safe summaries. My Farm now opens with one map for all owned boundaries, a parcel selector and area/crop details. It uses the existing tables and adds no migration or farmer accounts.

The primary-ID update uses the same AgriGOV ID on registry pages, cards, maps, portal pages and farmer selection controls before activation. The numeric farmer key and portal-account relationship remain unchanged. Old `PAIS-FRM-######` IDs remain searchable in staff tools, but are not portal login aliases. This display/search update was installed on Hostinger in `bd75ced`; see [AgriGOV farmer IDs](AGRIGOV_FARMER_IDS.md).

## Authorized sample testing access

On September 24, the owner explicitly limited live testing access to the 12 synthetic Region I farmers in Bacarra, Narvacan and Bacnotan. A private one-time transaction created their ready-to-sign-in accounts after checking exact sample markers and the original import digest, taking a verified backup, and checking that all non-target records were preserved. The owner-supplied testing credential was hashed individually and was never added to application code, defaults or seeders. Normal staff-assisted activation and password validation remain unchanged. All 12 live HTTPS sign-ins, own profile/parcel/assistance pages, non-storable responses and sign-outs passed. No harvest records were invented. See `REGION_I_SAMPLE_DATA.md`.

## Included

- My Profile: own local registry ID, recorded identity/contact/farm details, managing office, gender, farm province, ecosystem and login ID.
- My Farm: a map-first view of all owned parcel boundaries, fitted into one private satellite map on entry. A compact parcel selector shows the selected area and selected-year wet/dry crop entries; the existing individual parcel map URL remains available for direct links, and a records disclosure remains available when maps cannot load.
- My Assistance: own linked releases with date, category, item/variety, quantity and recorded unit, plus seed bags, lot, claimed area, planted variety/class and sowing details where recorded. Unlinked legacy releases need staff correction before appearing.
- My Harvests: own harvest records with commodity, variety, season/year, date, quantity/unit, harvested area and a parcel map link only when the linked parcel is also owned by the signed-in farmer. Missing quantities remain clearly marked; units are never converted or mixed.
- Overview: bounded cards for plotted parcels/area, assistance and harvest totals, up to three parcel previews and five recent releases/harvests.
- Mobile navigation, empty states, staff-help guidance, sign-out, idle expiration, password reveal controls, loading/retry messages and pagination.
- Staff issuance, recovery, disable, expiry, audits and concurrent-edit protection.

Not included: public self-registration, correction-request queue, farmer editing, announcements module, assistance applications/eligibility decisions, SMS, digital-ID download, or central RSBSA authentication. The profile displays a local registry identifier without claiming certification.

## Security and performance

Farmer accounts use an independent session guard and cannot authenticate office routes. Every read uses the account's farmer foreign key; assistance and harvests also match municipality. Related parcel names and distribution-program details are scoped before loading. Private data never uses the public QR token as authentication.

The canonical AgriGOV login ID works when RSBSA is missing or duplicated. The exact recorded RSBSA value is an optional login alias only if globally unique. Codes are random, hashed with SHA-256, one-use and consumed under a lock. Passwords are bcrypt hashed, 15 characters minimum and 72 bytes maximum. Password validation reuses the existing three-second breached-password check: the provider receives only a SHA-1 prefix, never the password; provider failures use the application's existing best-effort behavior.

Recovery and disable increment a session version. Every private request rechecks account state, current farmer ownership, active municipality/province, activation, password and idle time. A moved farmer requires newly authorized staff to verify identity and reissue access. No remember-me cookie is offered. Audit metadata excludes credentials; portal audit URLs omit query strings. Responses are non-storable; history restoration reloads through authentication. CSRF, generic authentication errors, per-route throttles and five-attempt lockouts protect public forms.

Page sizes are 10 parcel records, 15 releases and 15 harvests. Crop queries cover the selected year and the visible parcel records. Overview activity is capped at five releases/harvests and three parcels; quantity summaries are capped at 24 type/unit groups. The private collection map loads owned boundaries through a cursor endpoint in pages of 20, with each page capped at 1 MB of stored geometry and each ring capped at 10,000 points. The browser fits every returned valid boundary, reports records that need office review, and never substitutes another farmer's geometry. After 20 pages, unusually large collections offer **Load remaining parcels**, preserving the cursor and visible land. Google Maps and collection data have 20-second timeouts with plain-language retry/session/office-help messages. Map failures open the records disclosure; expired sessions remove loaded map boundaries. Stored geometry is not changed.

## Follow-up records deployment (pending)

Deploy the controller, support service, route and all changed portal views together. Mirror `public/css/farmer-portal.css` and `public/js/farmer-portal-map.js` to both Hostinger public directories, refresh route/view caches and verify the overview, seed details, harvest-year filters, map-first My Farm page, parcel selection, crop details, fallback records, existing parcel links and privacy. The existing harvest, seasonal-crop and release tables must already be installed. No migration, dependency update, account provisioning or password change is part of this interface update.

## Original installation

Deployed to Hostinger on September 20, 2026. See `docs/FARMER_PORTAL_DEPLOYMENT_2026_09_20.md` for the release receipt, checks and remaining pilot. Do not copy the local database to Hostinger.

1. Back up production files/database. Verify PHP/dependency compatibility and the baseline migration history. `farmers.id` and `municipalities.id` must support unsigned BIGINT foreign keys.
2. Apply only the additive migration after reviewing pending migrations:

   ```powershell
   php artisan migrate --path=database/migrations/2026_09_20_000100_create_farmer_portal_accounts_table.php --force
   ```

3. Deploy new portal classes, requests, controllers, middleware, policy and views; updated auth config/policy registration, routes, exception secret exclusion, office login guard cleanup, response headers, audit handling and entry links.
4. Mirror `public/css/farmer-portal.css`, `public/js/farmer-portal.js`, `public/js/farmer-portal-map.js` and existing `public/js/session-history.js` to both Hostinger public directories. Preserve branding assets and shared partials.
5. Refresh config, route and view caches using the deployment workflow. No new environment values, SMS provider, worker or npm build is required.
6. Perform an approved pilot: verified issuance, activation, login, own records, map, logout/back, recovery, expiry and disable. Check office roles and cross-farmer isolation, including farmers in the same municipality.

For a code rollback, retain the additive table to avoid destroying issued accounts. The migration's `down()` drops that table and requires an approved backup/recovery plan once accounts are in use.

## Verification

The follow-up records enhancement was verified locally on September 24, 2026 using PHP 8.3.35:

- 30 portal PHP tests / 352 assertions pass, including guard separation, cross-farmer and cross-municipality isolation, malformed related links, escaping, bounded lists/aggregates, missing quantities, year filtering, empty states and pagination.
- Seven portal JavaScript tests and syntax checks pass, including lazy read-only maps, session expiry, network retries, and safe messages for malformed/server error responses.
- Pint on the changed PHP files, nine compiled portal templates, named-route checks, PHP syntax and diff whitespace checks pass.
- Synthetic browser checks at 1440px desktop, 390px phone and 320px small-phone widths cover profile, overview, seed details/totals, harvest filtering and empty state, own parcel links and a map failure/retry state. The totals disclosure works with Enter and visible focus. No horizontal page overflow was observed in the checked layouts; the final harvest page had no console warnings/errors. Actual Google satellite loading and real-farmer acceptance were not repeated.

These richer screens remain local pending release. The separately authorized testing-account change is already live and verified as described above.

The subsequent map-first My Farm enhancement was also verified locally on September 24:

- 34 PHP tests / 424 assertions pass, including same-municipality foreign-farmer exclusion, cursor traversal beyond the record-list page, fixed query counts, byte/point limits, malformed boundaries, empty records and validation.
- 11 JavaScript tests pass, including multi-page maps, selected parcel details, partial-page retry without duplicate polygons, session-expiry cleanup and automatic record fallback when maps cannot load.
- Pint, PHP/JavaScript syntax, nine compiled portal templates, named-route checks and diff whitespace checks pass.
- A real Google satellite map displayed all three synthetic owned parcels in the local preview. Desktop and 390px phone layouts were reviewed; parcel selection updated the recorded area/details, and no browser warnings/errors were captured. Production maps and real-farmer acceptance were not tested in this pass.

Verified locally on September 20, 2026:

- 42 PHP tests / 337 assertions across farmer portal, session history, idle timeouts, security headers and public welcome behavior. Tests cover same-municipality and cross-municipality isolation, code expiry/reuse, account recovery/revocation, stale writes, global duplicate RSBSA, password checks, geometry limits, scope changes, database uniqueness/cascade/rollback and guard separation.
- Five JavaScript tests cover lazy read-only map loading, expired sessions, retry, active-only heartbeat and idle logout; both new scripts pass syntax checks.
- Laravel Pint, PHP syntax checks, Blade compilation, route inspection and diff whitespace checks passed.
- Local MySQL unsigned BIGINT parent IDs verified and the additive migration applied successfully. No real farmer accounts issued.
- Browser checks at 320/390-pixel widths and a desktop viewport: profile, parcels, seasonal crops, assistance, staff access controls, activation and login; a real Google satellite map loaded the synthetic owned parcel without console warnings/errors.

Browser previews use disposable in-memory SQLite sample records, never access issued to real farmers. The developer CLI is PHP 8.4.10, outside the currently documented dependency support range; Hostinger PHP 8.3.33 deployment passed 55 read-only checks and public HTTP/asset checks. An approved real-farmer acceptance pilot remains pending.
