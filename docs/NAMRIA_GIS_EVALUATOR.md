# NAMRIA GIS evaluation access

AgriGOV supports a dedicated `gis_evaluator` office identity for external administrative-boundary evaluation.

The account can sign in and open only the municipality-geofence viewer, its active-boundary data endpoint, and available Ramos or Baguio barangay planning references. The server returns active municipality boundaries only. It does not query or return farmers, parcels, assistance, operational totals, snapshots, exports, drafts, account administration, or audit records.

`RestrictGisEvaluatorAccess` rejects every other authenticated application route. Operational model policies independently reject this role, and `MunicipalityBoundaryPolicy` denies create, import, update, style, activate, and archive actions.

Provision credentials directly in the production environment. Never commit or record the password in this repository. Evaluators require a future `evaluation_expires_at` and null geographic assignments. Set `evaluation_password_pending` to true when issuing temporary access. `/evaluation/password` requires the current password and a new confirmed password of at least 15 characters before allowing map access. Expired accounts fail login and existing-session scope checks. Boundary visits and password changes are audited without credentials.

Deploy the additive `2026_09_22_000100_add_evaluator_access_limits.php` migration explicitly after a private backup. Create the NAMRIA evaluator with a 30-day expiry. No farmer records or parcel geometry are changed.
