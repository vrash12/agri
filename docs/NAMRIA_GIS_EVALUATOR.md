# NAMRIA GIS evaluation access

AgriGOV supports a dedicated `gis_evaluator` office identity for external administrative-boundary evaluation.

The account can sign in and open only the municipality-geofence viewer, its active-boundary data endpoint, and available Ramos or Baguio barangay planning references. The server returns active municipality boundaries only. It does not query or return farmers, parcels, assistance, operational totals, snapshots, exports, drafts, account administration, or audit records.

`RestrictGisEvaluatorAccess` rejects every other authenticated application route. Operational model policies independently reject this role, and `MunicipalityBoundaryPolicy` denies create, import, update, style, activate, and archive actions.

Provision credentials directly in the production environment. Never commit or record the password in this repository. Evaluators require a future `evaluation_expires_at` and null geographic assignments. Set `evaluation_password_pending` to true when issuing temporary access. `/evaluation/password` requires the current password and a new confirmed password of at least 15 characters before allowing map access. Expired accounts fail login and existing-session scope checks. Boundary visits and password changes are audited without credentials.

Deploy the additive `2026_09_22_000100_add_evaluator_access_limits.php` migration explicitly after a private backup. Create the NAMRIA evaluator with a 30-day expiry. No farmer records or parcel geometry are changed.

## Verified release — September 22, 2026

Runtime commits `0e2fae3` and `f3c5bee` were pushed to GitHub main and installed through Hostinger `git pull --ff-only origin main`. The changed map JavaScript was mirrored to `public_html`; route, view and configuration caches were refreshed. The additive migration completed after a private users-table backup, and existing account values were compared before provisioning.

The NAMRIA GIS Evaluator account was created with expiry October 22, 2026 at 06:01 Philippine time and a mandatory first-use password change. Its random temporary credential was delivered as a private local file, excluded from Git and this document. No email was sent.

Verification: 26 geofence feature tests, 5 province-isolation tests, 11 sign-in tests and 22 map JavaScript tests passed; the evaluator test was extended and rerun for password replacement and expiry. Blade compilation and route registration passed. Live HTTP sign-in reached the password-change form and the farmer endpoint returned 403. A read-only production controller check returned the Ramos boundary with zero operational queries; five protected routes were rejected by evaluator middleware. The pending password flag remained enabled in the database. Interactive Google Maps rendering was not visually tested in this release.
