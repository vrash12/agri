# NAMRIA GIS evaluation access

AgriGOV supports a dedicated `gis_evaluator` office identity for external administrative-boundary evaluation.

The account can sign in and open only the municipality-geofence viewer, its active-boundary data endpoint, and available Ramos or Baguio barangay planning references. The server returns active municipality boundaries only. It does not query or return farmers, parcels, assistance, operational totals, snapshots, exports, drafts, account administration, or audit records.

`RestrictGisEvaluatorAccess` rejects every other authenticated application route. Operational model policies independently reject this role, and `MunicipalityBoundaryPolicy` denies create, import, update, style, activate, and archive actions.

Provision credentials directly in the production environment. Never commit or record the password in this repository. Disable or delete the account when the external evaluation ends.
