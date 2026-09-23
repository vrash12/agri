# Farmer workspace hierarchy and coverage statistics

Status: the dashboard coverage and initial chooser are installed in production at `d69eb4a`; the simplified Region → Municipality flow was installed on Hostinger through GitHub at runtime commit `88d3bdc` on September 23, 2026. This feature requires no data import or migration. Separately authorized CALABARZON account provisioning is recorded in `CALABARZON_BOUNDARY_SOURCES.md`.

## Farmer workspace

The System Owner first selects a region, then a municipality. Municipality options are grouped by province or independent city for recognition without adding another required step. Regional Heads keep their assigned region; provincial accounts keep their province; municipal accounts open directly. Only available authorized steps appear. Native GET forms and Show municipalities/View farmers buttons work without JavaScript; the small `farmer-workspace.js` script adds automatic progression and preserves the current registry/map destination. Changing a parent submits no stale children. Registry filters are carried through selection.

`FarmerWorkspace` validates all supplied choices against active `MunicipalityAccess` results and checks parent/child consistency. A bookmarked `municipality_id` derives its province and region. Provinces without configured regional membership remain in the explicit “Region not assigned” group; the chooser does not guess or alter geography. Separate city scopes remain separate from surrounding provinces.

Before municipality selection, `/farmers` returns only the chooser, with no farmer, assistance, parcel or boundary queries. Broad directory links now lead through selection; dashboard and geofence modules retain their existing oversight reports. The chosen municipality controls registry figures, map boundaries, parcel requests and weather. Direct farmer profiles link back to that municipality and offer Change workspace when permitted.

## Coverage statistics

`DashboardCoverage` uses database aggregates and existing authorization scope. Its query count remains constant as municipality count grows. Figures appear in dashboard Reports under the reporting-year filter:

- Farmers reached: distinct current farmer IDs with at least one release dated within the reporting year and an identical farmer/release municipality.
- Registered farmers without a linked release in the year, and reach as a percentage of the current registry. This is not a historical registry snapshot or eligibility determination.
- Release records lacking a valid same-municipality farmer link, plus an explicit count of undated releases excluded from year totals.
- Active municipality geofence coverage: exactly one active boundary counts as covered. No active boundary counts as missing; multiple active versions are reported separately for review. Inactive municipalities/provinces are excluded. An absent boundary table renders unavailable rather than zero coverage.

No differently measured quantities are added together. Empty denominators render “Not available”. Existing assistance and geofence pages provide the review destinations.

## Verification

- 73 focused PHP tests / 692 assertions: `FarmerWorkspaceHierarchyTest`, `DashboardCoverageTest`, `FarmerWorkspacePresentationTest`, `DashboardPresentationTest`, `ProvinceReportingScopeTest`, `MunicipalityGeofenceTest`, and the accompanying `WelcomePageTest`.
- Coverage includes valid selections, region/municipality isolation, malformed and mismatched IDs, inactive scopes, independent cities, unassigned regions, preserved bookmarks and filters, unavailable modules, empty denominators, repeat beneficiaries, cross-municipality links, reporting years, and constant query counts.
- Early chooser tests intentionally provide no operational tables; any premature records query fails.
- Local browser preview uses synthetic in-memory fixtures. Verified initial region-only state, municipality reveal, dashboard figures, and 390px layout with no horizontal overflow. It does not verify live production data.
- Pint, PHP/JavaScript syntax, route registration, Blade compilation and whitespace checks pass.

## Deployment

No migration, dependency installation, data import or account setup is needed. Push the reviewed changes to GitHub main and install with Hostinger `git pull --ff-only origin main` after a clean-tree check and private backups. Deploy the new support classes, both controller changes, views, and `public/js/farmer-workspace.js` together. Mirror this new script to `public_html/js`, using readable asset permissions. Refresh production autoload metadata if authoritative classmaps are enabled and refresh compiled views. Retain the prior pending map-hover assets in the release.

Production verification: all seven CALABARZON account sign-ins reached the dashboard, then opened Farmers with no province-selection step. The Regional Head received 142 municipality/city choices; each Cavite, Batangas and Laguna administrator/staff account received only its assigned 23, 34 or 30 choices. Verification sessions were signed out. Owner/municipal flows, bookmarks and reporting-year behavior were covered locally; this release did not repeat those checks through live owner or municipal sessions. See `GITHUB_DEPLOYMENT.md` for backups and asset/cache checks.
