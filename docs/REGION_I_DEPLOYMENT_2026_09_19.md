# Region I geofence deployment — 2026-09-19

## Result

Activated 125 planning/reference municipality and city boundaries on localhost and Hostinger:

| Province | Boundaries |
| --- | ---: |
| Ilocos Norte | 23 |
| Ilocos Sur | 34 |
| La Union | 20 |
| Pangasinan | 48 |

Live deployment completed at 01:54:12 UTC (09:54:12 Philippine time), after a short maintenance window. The four named seeders ran inside one outer database transaction. No schema migration or account reassignment was required. Existing province-level evaluation workspaces for Pangasinan and La Union were preserved.

## Verification

- Focused SQLite suite: 9 tests, 1,933 assertions passed.
- Laravel Pint passed for all five new PHP files; PHP syntax and route checks passed.
- All 125 geometries are unchanged from their pinned geoBoundaries source features; source checksum and identity checks passed.
- Local dry run rolled back cleanly; local activation succeeded and the System Owner geofence page rendered.
- Live installer verified file baselines/dependencies, package checksums, PHP syntax and a private compressed database backup before writing.
- Existing database rows were verified by fingerprints before commit. A second import changed no rows, styles, geometry, IDs or audits.
- Live geofence view rendered with the new provinces; public login returned HTTP 200 after deployment.
- Live counts after import: 173 municipality workspaces, 164 boundary records (including pre-existing history), 15 province scopes, and 795 audits. The Region I references account for 125 new workspaces, 125 active boundaries and 125 import audits; Ilocos Norte and Ilocos Sur added two province scopes.
- Existing operational totals remain 60 users, 1,667 farmers, 6 farm plots, 43 assistance releases, 3 cooperatives and 7 memberships. All original row contents were preserved, including account roles and password hashes.

Source provenance, limitations, checksums, exact identities and explicit re-import commands are documented in [REGION_I_BOUNDARY_SOURCES.md](REGION_I_BOUNDARY_SOURCES.md). Paoay retains the source’s lake-inclusive administrative outline; its area is not a measurement of farmland. Dagupan is included in the geographic Pangasinan planning group.

These are approximate planning references requiring LGU/NAMRIA verification for official use.

The temporary Hostinger preflight and deployment cron jobs were removed and their absence verified. The CLI-only deployment files and backup remain outside the public web root for audit and recovery.
