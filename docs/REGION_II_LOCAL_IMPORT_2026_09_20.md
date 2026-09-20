# Region II local import — September 20, 2026

Completed on localhost. Production (`agritarlac.online`) was not changed.

## Result

| Scope | Active planning references |
| --- | ---: |
| Batanes | 6 |
| Cagayan | 29 |
| Isabela | 36 |
| Nueva Vizcaya | 15 |
| Quirino | 6 |
| Santiago City | 1 |
| **Total added** | **93** |

The owner selected separate Santiago City supervision. Isabela administrators are limited to their 36 workspaces and cannot access Santiago. No accounts were created or reassigned.

The six named seeders ran in one outer database transaction after a verified private compressed database backup. The importer retained its activation lock, source checksum checks, geometry/area checks, identity matching, overlap protection and audit attribution. The transaction verified that existing municipality, boundary, province and audit rows were unchanged, and compared all existing ID-bearing operational tables before committing. This included users, farmers, parcels, assistance, animal health, cooperatives, machinery, harvests and farmer portal accounts. Local municipality and boundary-history totals each changed from 333 to 426.

## Verification

- 12 focused Region II tests passed, with 1,940 assertions: coverage, ownership, attribution, repeat imports, style/ID preservation, cache invalidation, duplicate names, wrong province, inactive workspaces, overlap, changed boundaries and checksum rejection.
- The existing local map controller rendered the page successfully and included all six scopes. Its data action returned the active reference for every one of the 93 new workspaces.
- Source geometry, identities and area checks passed for all 93 features; see [REGION_II_BOUNDARY_SOURCES.md](REGION_II_BOUNDARY_SOURCES.md).
- Pint, PHP syntax, route verification and scoped whitespace checks passed.
- Authenticated Google Maps interaction was not visually tested in the browser; the open browser was signed out. No Google snapshot request was made.

To view locally, sign in as System Owner and open **Municipality Boundaries**. Choose the province or the separate Santiago City scope. A provincial administrator sees only the scope assigned to that account.

No migration, new application dependency or environment change was needed. Production deployment must explicitly copy the six seeders and six GeoJSON files, verify the shared importer dependencies, back up the live database, and run the named imports transactionally. Files alone do not activate the references. Do not run legacy baseline migrations or unrelated seeders.
