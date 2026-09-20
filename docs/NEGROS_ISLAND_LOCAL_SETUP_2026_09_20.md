# Negros Island Region local setup — September 20, 2026

## Applied outcome

The owner selected the full region including Siquijor and a separate Bacolod City supervising scope. All 63 municipality/city geofences were already present locally from an earlier import. The setup verified and preserved them, updated the import snapshots to current Region XVIII PSGC metadata, and separated Bacolod from Negros Occidental supervision.

| Scope | Municipalities/cities | Active geofences |
| --- | ---: | ---: |
| Negros Occidental | 31 | 31 |
| Negros Oriental | 25 | 25 |
| Siquijor | 6 | 6 |
| Bacolod City | 1 | 1 |
| **Total** | **63** | **63** |

Sign in as the System Owner, open **Municipality geofences**, choose one of these scopes and select a municipality/city. Scoped administrators see only their assigned area. These are municipality/city planning references; this setup does not add barangay geofences.

## Preservation and scope change

The target was explicitly verified as the local development database. A private verified gzip backup was written outside the repository before applying changes (`ag_system-20260920-162113.sql.gz`, SHA-256 `5e78b65e6fd28802c2e785960afa96392e81fceb166d413a60247fdc5cf61dae`). The setup used a municipality mutation lock, `ConcurrentWrite` freshness validation, row locks and one outer transaction. A required owner-only scope audit was recorded before commit.

- Bacolod municipality ID 5404 kept its name, code and geometry; its province foreign key and compatibility province string now identify Bacolod City supervision.
- No Bacolod accounts or operational records existed at the time of transfer.
- Overall counts remained 426 municipalities and 426 boundary records. Province-scope records increased from 24 to 25; audit records increased from 704 to 705.
- Fingerprints matched for all 16 checked tables, including all users, farmer portal accounts, farmers, plots, crop seasons, assistance releases, harvests, machinery, cooperatives and boundaries.
- Other municipality identities, existing province rows and all historical audit rows were unchanged. Historical audit province snapshots were preserved.
- Running the four current reference seeders reused every existing boundary and added no duplicate workspaces, boundaries or import audits.

## Verification

- `NegrosIslandRegionBoundarySeederTest`: **11 tests passed**, using isolated in-memory SQLite. Covers 63 identities, attribution, non-overlap, repeat imports and cache invalidation, separate Bacolod permissions, cross-province restrictions, checksum rejection, rollback on late conflicts, and preservation of locally reviewed boundaries.
- All 63 source geometries match the previous tracked snapshots exactly. All current PSGC codes and supervising groups match the independently checked identity manifest; all four file checksums match.
- Local map-controller checks returned one active boundary for each of the 63 selected municipalities/cities. The municipality map view rendered successfully with all four scope choices.
- Local unsaved administrator checks returned 31/25/6/1 choices and excluded Bacolod from Negros Occidental access. No real accounts were created or changed for testing.
- Pint, PHP syntax checks, municipality-boundary route verification, Blade compilation and relevant `git diff --check` passed.

The browser session was signed out during the final check; interactive Google Maps rendering was not re-tested. The map-controller and rendered-view checks succeeded. The test runner emitted existing vendor deprecation warnings without test failures.

No schema migration, public asset change or new dependency is required. Nothing was pushed or deployed to Hostinger. Production needs a separate live-data review and authorization for any Bacolod ownership transfer. Source attribution, current identity manifest, accuracy limitations and deployment commands are in [Negros Island boundary sources](NEGROS_ISLAND_BOUNDARY_SOURCES.md).
