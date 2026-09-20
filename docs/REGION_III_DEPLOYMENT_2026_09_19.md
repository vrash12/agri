# Region III boundary deployment — 19 September 2026

Completed on localhost and production (`agritarlac.online`). The production installer completed at 03:02:20 UTC after verifying a private database backup, package checksums, existing file baselines, and PHP syntax. No schema migration or account change was required.

## Active reference coverage

| Scope | City/municipality boundaries |
| --- | ---: |
| Aurora | 8 |
| Bataan | 12 |
| Bulacan | 24 |
| Nueva Ecija | 32 |
| Pampanga | 21 |
| Tarlac | 18 |
| Zambales | 13 |
| Angeles City | 1 |
| Olongapo City | 1 |
| **Total** | **130** |

Angeles City and Olongapo City have independent supervision scopes, as requested. Access checks verified that Pampanga and Zambales administrators cannot access those city workspaces. No users were created or reassigned.

Production gained 112 municipality workspaces and reference boundaries. Existing Tarlac references were preserved. Bulacan's coarse province reference was archived, with its geometry and history retained, after importing its 24 municipality/city references. Existing IDs and operational rows were verified unchanged.

## Verification

- 57 focused tests passed, with 2,237 assertions: geometry, Region III imports, municipality geofences, province isolation, and Bulacan imports.
- Pint, PHP syntax checks, route verification, and diff whitespace checks passed.
- Source feature identities, checksums, geometry, and area comparisons were checked; see `REGION_III_BOUNDARY_SOURCES.md`.
- Both local and production imports were verified repeatable without duplicate rows or further changes.
- The production System Owner boundary page rendered with the independent city scopes.
- Production retains 1,667 farmers, 6 plots, 43 assistance releases, and 60 users. Totals after import: 285 municipalities, 276 boundary-history rows, and 17 province/city supervision scopes.
- The live login returned HTTP 200 with AgriGOV branding after deployment.
- Temporary preflight and deployment cron jobs were removed after verification. Private deployment receipts and backup artifacts remain outside the public web directory.

The geometry change corrects false overlap detection when a concave polygon's centroid lies outside its own boundary. Regression coverage includes holes, concave rings, and multipart geometry.

These are administrative planning references, not surveyed parcel or legal boundary certifications. No Google Maps visual interaction was required for the server-rendering and data checks above.
