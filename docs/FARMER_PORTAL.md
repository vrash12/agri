# Farmer portal — first release

## Workflow

1. Staff open Farmer registry → View profile/history → **Farmer portal access**.
2. Verify the farmer's identity using the office's identity-check procedure and confirm that the selected record belongs to them.
3. Every saved farmer already has an AgriGOV ID (`AGRI-F-######`). Issue portal access and privately provide that ID, activation page, and one-time code. The code is shown once and expires in 24 hours. No email or SMS is sent.
4. The farmer opens `/farmer-portal/activate`, enters the ID/code, chooses and confirms a password, then signs in at `/farmer-portal/login`.
5. For recovery, staff verify identity again and reissue the code. This revokes the previous password and sessions. Disabling access retains the farmer's operational records.

Installation creates no real farmer accounts. Birthdays, RSBSA numbers, or possession of a registry card cannot activate an account alone. Welcome and office login pages link to farmer sign-in.

The September 24 local sign-in update is intentionally limited to login: it accepts the AgriGOV ID or unique RSBSA alias and password, and shares the office sign-in layout with a switch between both audiences. It does not display activation-code prompts. Staff still use the separate activation route when issuing access, so existing account setup and recovery controls remain intact. This interface update is not yet committed, pushed or deployed. Deploy both login views, the welcome view and `public/css/login-layout.css` together, mirror CSS to both Hostinger public directories and refresh compiled views; no migration or account provisioning is required. The 23 portal tests and 11 office sign-in tests pass; a synthetic desktop preview verifies switching between both forms.

The primary-ID update uses the same AgriGOV ID on registry pages, cards, maps, portal pages and farmer selection controls before activation. The numeric farmer key and portal-account relationship remain unchanged. Old `PAIS-FRM-######` IDs remain searchable in staff tools, but are not portal login aliases. This display/search update is committed and awaits deployment; see [AgriGOV farmer IDs](AGRIGOV_FARMER_IDS.md).

## Included

- My Profile: own local registry ID, recorded identity/contact/farm details, managing office and login ID.
- My Farm: own parcel list, recorded area, selected-year wet/dry crop entries, and an individual private satellite map loaded on demand.
- My Assistance: own linked releases with date, category, item/variety, quantity and recorded unit. Unlinked legacy releases need staff correction before appearing.
- Mobile navigation, empty states, staff-help guidance, sign-out, idle expiration, password reveal controls, loading/retry messages and pagination.
- Staff issuance, recovery, disable, expiry, audits and concurrent-edit protection.

Not included: public self-registration, correction-request queue, farmer editing, announcements module, assistance applications/eligibility decisions, SMS, digital-ID download, or central RSBSA authentication. The profile displays a local registry identifier without claiming certification.

## Security and performance

Farmer accounts use an independent session guard and cannot authenticate office routes. Every read uses the account's farmer foreign key; assistance also matches municipality. Private data never uses the public QR token as authentication.

The canonical AgriGOV login ID works when RSBSA is missing or duplicated. The exact recorded RSBSA value is an optional login alias only if globally unique. Codes are random, hashed with SHA-256, one-use and consumed under a lock. Passwords are bcrypt hashed, 15 characters minimum and 72 bytes maximum. Password validation reuses the existing three-second breached-password check: the provider receives only a SHA-1 prefix, never the password; provider failures use the application's existing best-effort behavior.

Recovery and disable increment a session version. Every private request rechecks account state, current farmer ownership, active municipality/province, activation, password and idle time. A moved farmer requires newly authorized staff to verify identity and reissue access. No remember-me cookie is offered. Audit metadata excludes credentials; portal audit URLs omit query strings. Responses are non-storable; history restoration reloads through authentication. CSRF, generic authentication errors, per-route throttles and five-attempt lockouts protect public forms.

Page sizes are 10 parcels and 15 releases. Crop queries cover the current parcel page and selected year. Map requests return one owned parcel, capped at 10,000 points and 1 MB stored geometry. Geometry and Google Maps load after a button click, with 20-second browser timeouts. Existing map billing and website restrictions apply; stored geometry is not changed.

## Deployment

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

Verified locally on September 20, 2026:

- 42 PHP tests / 337 assertions across farmer portal, session history, idle timeouts, security headers and public welcome behavior. Tests cover same-municipality and cross-municipality isolation, code expiry/reuse, account recovery/revocation, stale writes, global duplicate RSBSA, password checks, geometry limits, scope changes, database uniqueness/cascade/rollback and guard separation.
- Five JavaScript tests cover lazy read-only map loading, expired sessions, retry, active-only heartbeat and idle logout; both new scripts pass syntax checks.
- Laravel Pint, PHP syntax checks, Blade compilation, route inspection and diff whitespace checks passed.
- Local MySQL unsigned BIGINT parent IDs verified and the additive migration applied successfully. No real farmer accounts issued.
- Browser checks at 320/390-pixel widths and a desktop viewport: profile, parcels, seasonal crops, assistance, staff access controls, activation and login; a real Google satellite map loaded the synthetic owned parcel without console warnings/errors.

Browser previews use disposable in-memory SQLite sample records, never access issued to real farmers. The developer CLI is PHP 8.4.10, outside the currently documented dependency support range; Hostinger PHP 8.3.33 deployment passed 55 read-only checks and public HTTP/asset checks. An approved real-farmer acceptance pilot remains pending.
