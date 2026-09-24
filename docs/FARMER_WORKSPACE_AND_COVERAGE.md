# Farmer workspace hierarchy and coverage statistics

## September 24 overview update — deployed in `bd75ced`

Farmers now opens the paginated directory and an expanded parcel map directly, across active municipalities the account can access. System Owners see an **All regions** filter; Regional Heads see **All provinces in your region**, including separate city scopes. Provincial users see their province and can optionally narrow to a municipality; municipal users stay assigned to their municipality. The optional Municipality / City dropdown preserves direct bookmarks and municipality-only map exports/weather.

The top panel follows the owner's second screenshot: location controls sit side by side on desktop, with **Show municipalities** and **View farmers** buttons, a **Showing …** scope summary and registry/map links below. Both controls and the full directory are present from the initial visit. Municipality labels include their province or separate city scope. Phone layouts stack the controls without horizontal overflow. Returning from a farmer profile preserves that farmer's municipality in both registry and map links.

`FarmerWorkspace` supplies a validated set of municipality IDs to the registry, aggregates, map totals, farmer finder and parcel endpoint. Region/province filters propagate to map request URLs and registry forms/pagination. Parent filters submit no stale child IDs. Empty selections restore the authorized overview; empty scope renders an empty directory rather than a mandatory chooser. No ownership, role, account or data changes occur.

The administrative-outline layer defaults to `MAP_MAX_BOUNDARIES_PER_REQUEST=1000` (hard maximum 1000), which includes the full active set on the broad Farmers map when it fits under that ceiling. A deployment can lower the setting for slower connections; the page then shows the returned and total counts and instructs staff to narrow by location. Stored geometry is unchanged. Parcel responses retain the existing `MAP_MAX_PLOTS_PER_REQUEST=2000` cap and truncation metadata. Table rows paginate, map farmer searches remain bounded, and same-municipality assistance links are enforced in totals.

The support/controller/view/config changes and changed `farmer-finder.js`, `farmer-workspace.js` and `farmers-maps.js` were deployed together through GitHub and Hostinger `git pull --ff-only` in `bd75ced`. The production database backup, cache refresh, public-asset mirroring and HTTP checks passed. No migration, dependency update or data import ran. The prior deployment notes below describe the older chooser release.

Local verification for that release: 87 PHP tests / 1,011 assertions cover the workspace, primary farmer ID, geofences, portal and office sign-in. The unchanged JavaScript checks pass 140 tests. Pint was run explicitly on the nine changed PHP classes/tests. Synthetic browser checks cover desktop and 390px phone layout, region/municipality selection, clearing both filters, and the expanded map's provider-unavailable state. No Google Maps key was used in the preview, so actual satellite loading and production performance were not verified.

The separate farmer portal records enhancement is still local. Its verification and pending release requirements are recorded in `docs/FARMER_PORTAL.md`.

## Earlier deployed chooser — September 23, 2026

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
