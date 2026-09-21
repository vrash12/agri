# Remaining CAR geofences — local setup, September 21, 2026

## Applied result

The requested remaining Cordillera municipality/city references were imported into the verified local development database at **2026-09-20 21:03:36 UTC** (September 21, 05:03:36 Philippine time).

| Province | Municipality/city workspaces added | Active geofences added |
| --- | ---: | ---: |
| Abra | 27 | 27 |
| Apayao | 7 | 7 |
| Ifugao | 11 | 11 |
| Kalinga, including Tabuk City | 8 | 8 |
| **Total added** | **53** | **53** |

Together with the thirteen Benguet municipalities, separate Baguio City and ten Mountain Province municipalities, local CAR coverage is **77 municipality/city planning references**. The existing legacy Benguet office workspace is preserved but is not an additional geographic municipality. Tabuk City remains supervised by Kalinga; Baguio remains separate from Benguet.

Sign in as the System Owner and open **Municipality geofences**. In **Municipality workspace**, select a municipality/city labeled with Abra, Apayao, Ifugao or Kalinga, or use **Find municipality** to locate it. Existing province/municipality policies control other accounts. This task creates no accounts or Regional Head assignments; the four new province rows retain unassigned regional membership, following the existing explicit region-configuration process.

## Preservation and backup

The import runner refused production environments and any database host other than loopback. Before writing, it created and verified a private gzip database backup outside the repository and public directories: **2,125,745 bytes**, SHA-256 `ddeedb1969d80d11e031e1598dc56bec260fe86ed61a7ffdf59bc8d6252ff1ff`. Backup contents and credentials are not included in this record. Backup verification checked readability and every expected table definition; this run did not perform a scratch restore.

All four imports shared one outer transaction and used the existing importer's activation locks, geometry checks, row locks, attribution and cache invalidation. Local totals changed from 26 to 30 province scopes, 436 to 489 municipality workspaces and 436 to 489 boundary records. Exactly 53 attributed import audit events were added.

Every pre-existing row across **20 tables** retained its fingerprint, including users, region assignments, farmers, parcels, seasonal crops, farmer portal accounts, assistance, harvests, cooperatives, machinery and historical audits. Operational tables received no new records. Existing municipality and province IDs, colors, boundaries, assignments and archived history were preserved. Repeating the four imports inside the transaction changed no stored rows or audit entries.

## Verification

- `RemainingCarBoundarySeederTest`: **26 tests, 1,479 assertions**, run with an isolated in-memory SQLite schema. Includes all 53 identities and source attribution, province isolation, repeated names, checksum failures, rollback on late ownership/inactive/overlap conflicts, changed-boundary preservation and repeat-import stability. An integration test imports all 77 CAR references and preserves the existing 24.
- Independent source comparison checked all 53 geometries and identities against pinned geoBoundaries and GeoRiskPH/PSA sources. See [source evidence and accuracy limits](REMAINING_CAR_BOUNDARY_SOURCES.md).
- All **53 local map-controller responses** returned the selected municipality and exactly one active boundary. The existing geofence workspace rendered with all four new province choices.
- Unsaved provincial administrator fixtures received exactly 27/7/11/8 municipal choices and could not access a foreign province's municipality. Municipal fixtures received only their assigned workspace. No real test accounts were created.
- Laravel Pint and PHP syntax checks passed for the four seeders and feature test. The ten geofence routes loaded; final Git whitespace checks passed. Workspace rendering compiled the existing Blade views; no view or public asset was changed.
- Local PHP was **8.4.10**, with existing dependency deprecation notices. Production's documented supported PHP range remains 8.1–8.3; this task did not run a production-runtime check.

Interactive Google Maps/satellite rendering was not re-tested in a signed-in browser. The application map-data and rendered-view checks passed without contacting a map provider.

### Completion recheck, September 21, 2026

A subsequent read-only check confirmed that the local import is already complete; no second import or database change was necessary.

| Geographic scope | Workspaces with an active geofence |
| --- | ---: |
| Abra | 27 |
| Apayao | 7 |
| Benguet municipalities | 13 |
| Ifugao | 11 |
| Kalinga, including Tabuk City | 8 |
| Mountain Province | 10 |
| Separate Baguio City | 1 |
| **Total geographic workspaces** | **77** |

All 77 geographic workspaces have exactly one active geofence, populated geometry, positive recorded area and at least three recorded vertices. The additional legacy Benguet office workspace has no municipality geofence and is intentionally excluded from this geographic total. The focused in-memory `RemainingCarBoundarySeederTest` suite passed again: **26 tests**. The checks did not modify local operational data or inspect/change Hostinger.

All seven CAR province/city scopes currently have no `region_id` assignment in the local database. Their geofences are available through existing owner/province/municipality permissions; assigning them to a Regional Head remains a separate scope-configuration operation.

## Deployment status and requirements

The local work above was followed by an explicitly authorized **Hostinger deployment on September 21 at 00:38 UTC**. Production added all 53 references and verified all 77 CAR geographic workspaces, existing-data preservation and repeat-import stability. See [the production receipt](CAR_DASHBOARD_DEPLOYMENT_2026_09_21.md). No migration, environment change or new dependency was required. Do not run legacy baseline migrations or demo seeders. Source provenance, checksums and explicit commands are documented in [remaining CAR boundary sources](REMAINING_CAR_BOUNDARY_SOURCES.md).

These are approximate 2020 planning-reference outlines, not legal, cadastral or surveyed boundaries. LGU/NAMRIA validation remains necessary before official use.
