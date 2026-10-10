# Satellite and map usage dashboard

Status: local implementation, not pushed or deployed. No production data or credentials changed.

## What administrators see

Active, scoped Super Admins and the System Owner see a **Satellite & map usage** panel on the operations dashboard. It shows shared infrastructure activity across offices, rather than provincial operational records. Regional Heads, municipal/provincial staff, evaluators, farmers and restricted visitors receive no provider data. No new route is introduced; dashboard authentication and scope middleware remain in force.

The current local panel labels use the AgriLGU application name; see [branding deployment requirements](AGRILGU_BRANDING.md), especially preserving cache namespaces and existing NDVI budget counters.

NDVI satellite checks show the local daily/monthly request ceilings configured by `SENTINEL_DAILY_REQUESTS` and `SENTINEL_MONTHLY_REQUESTS`, counts consumed, remaining requests and UTC reset times. Near-limit warnings begin at 80%; a zero ceiling pauses new processing. `SatelliteRequestBudget` uses the existing Sentinel cache keys and atomic reservation lock, so existing allowance consumption is retained. Statistical, NDVI image and true-color image attempts share these ceilings. OAuth calls and cached results do not consume them; failed processing attempts do. NDVI authorization, geometry limits and upstream requests remain unchanged.

Google Maps displays daily/monthly outgoing Maps Static API attempts made by the authorized parcel-image and municipality-snapshot proxies. Failed attempts count, cached image responses and denied/unconfigured requests do not. The first recorded timestamp is shown for each period. Tracking begins with this release and does not reconstruct earlier traffic. Logging these aggregates is best-effort under a shared cache lock and cannot block an image export on a recording failure.

Configuration badges indicate that settings exist, not that authentication, permissions, billing or provider connectivity have been verified. Failed counter reads show an unavailable message rather than zero.

## Interpretation and limits

All counters use UTC day/month keys and expire at the next period boundary. Application cache clearing also erases counters. Existing NDVI budget counters are preserved across normal code deployments; Google has no historical baseline. Telemetry can be incomplete if the cache or its lock fails. The dashboard makes no upstream monitoring requests and stores no user, municipality, geometry, URL, key or token alongside these counters.

Google's interactive Maps JavaScript loads are not measured by the server. Full usage, quota and billing reports require access to the Google Cloud project; the existing map key is not a reporting credential. The panel links to the [Google Cloud Maps overview](https://console.cloud.google.com/google/maps-apis/overview). See Google's [reporting and monitoring documentation](https://developers.google.com/maps/documentation/javascript/report-monitor).

Copernicus processing units depend on processing workload; local request counts cannot establish the remaining account-wide processing allowance. The panel links to the official [quota guide](https://documentation.dataspace.copernicus.eu/Quotas.html). See the [processing-unit definition](https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Overview/ProcessingUnit.html). No bill, credit balance or provider quota remaining is invented.

Automated provider-wide reporting would require separately authorized, privately provisioned read-only reporting access. It is not configured by this release.

## Deployment and verification

No migration or additional environment setting is required. Deploy the changed support services, three controllers and dashboard views together through the normal GitHub push and Hostinger fast-forward pull after explicit authorization. Refresh compiled views; avoid clearing the general cache merely for this view change because doing so resets usage safeguards. Existing hosting cache storage must remain persistent and support atomic locks; use shared storage/Redis before multiple servers. Keep provider credentials outside source control and never paste them into reports or logs.

Verified locally on October 10, 2026:

- 70 focused tests passed across `ProviderUsageTest`, `ParcelSatelliteTest`, `DashboardPresentationTest` and `MunicipalityGeofenceTest`. Coverage includes role restrictions, secret-free snapshots, existing NDVI allowance enforcement/cache reuse, UTC rollover, zero/lowered ceilings, cache failure, readable dashboard states and actual Google proxy calls including failure/recovery/cache hits and denied access.
- Laravel Pint passed for changed PHP files. Blade compilation, PHP syntax checks, dashboard route verification and `git diff --check` passed.
- A browser preview using synthetic counts was visually inspected at desktop and narrow mobile widths (376 CSS pixels). Cards stack, labels remain readable and document width equals viewport width; no horizontal overflow was observed. The preview uses the actual panel partial and dashboard styles and makes no provider requests.

The local machine runs PHP 8.4 and emits pre-existing test-runner deprecation notices; the supported production range remains PHP 8.1–8.3. Provider-wide billing integration is not configured.

Deployed on Hostinger on October 10, 2026 in runtime `c322a5e`. Read-only live checks confirmed owner access, staff exclusion and panel rendering. Existing NDVI budget values and cache namespaces were preserved; no general cache clearing or provider requests were performed. See `GITHUB_DEPLOYMENT.md` for the verified release receipt.
