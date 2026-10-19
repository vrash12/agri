# Automated climate evidence

## October 19 monitoring and reliability follow-up

The selected municipality now opens with Automatic monitoring: dated rainfall
and temperature evidence, the number of usable sources, and simple field-check
prompts. Opening the dashboard reads saved summaries, without provider work.
Verified office assessments and their map remain separate. This feature does
not declare a Super El Niño, diagnose crop damage or invent affected parcels.

The transparent, configurable planning triggers are: a forecast high of at
least 35°C; less than 10 mm of rain forecast over seven days; or at least 30%
less recent rain than a complete three-year reference. These are prompts to
inspect irrigation, crop stage and actual farm conditions, not official drought
categories. One usable source can raise a prompt; missing evidence is shown.

Weather must have seven complete consecutive daily rainfall values, cover the
current Philippine date, and have been fetched within 12 hours. Heat prompts
also require seven valid daily temperatures. CHIRPS must have 30 complete dated
days, a period ending within the past 21 days, and a fetch within 48 hours.
The deficit prompt requires every configured reference year (at least three)
and a positive baseline. A genuine zero rainfall value remains valid; missing,
negative, duplicate, future, incomplete or stale readings never become zero.

## What runs automatically

The Laravel scheduler registers Open-Meteo every six hours and CHIRPS daily at
03:15 in application scheduler time. Hosting must invoke `artisan schedule:run`
every minute. Registration alone does not prove the host runs cron. The panel
shows each provider's last completed command run, retained seven days; this is
a run heartbeat, not proof of an installed cron job or successful data for each
area. Deployment receipts distinguish manual refreshes from scheduler proof.

Unscoped runs cover active municipalities with farmers, an active nonvisitor
municipal office account, or an assessment, under an active province. A rotating
batch defaults to 100 areas with a 240-second runtime allowance. A shared
900-second lock prevents concurrent commands; the cursor advances only past
processed areas. Invalid providers, IDs and batch sizes fail closed.

```text
php artisan climate:refresh-sources --provider=open-meteo --municipality=12
php artisan climate:refresh-sources --provider=chirps --municipality=12 --force
```

Weather uses the existing reference-point forecast cache and preserves its
actual fetch timestamp, including stale fallback. Snapshot fingerprints bind
to the current boundary reference point; a changed point requires a refresh.

CHIRPS uses ClimateSERV dataset 0, documented as UCSB CHIRPS Rainfall. The API
does not document its version as v3, so the application makes no v3 claim. The
default endpoint is `https://climateserv.servirglobal.net/api`; an explicitly
configured endpoint remains unchanged. It asks for a completed five-day period
with publication lag, then finds an actual complete 30-day window from dated
rows. The comparison averages matching 30-day windows from each of the prior
three years by default, not a 30-year climate normal. It is a small area around
the saved municipality reference point, not a whole-municipality or parcel
measurement. CHIRPS is delayed rainfall evidence, not live rainfall.

Asynchronous job IDs and validated daily windows are cached so a later run can
resume rather than submit duplicate jobs. Submission, polling and processing
are bounded; unavailable or pending work stays unavailable until complete.
Historical reference periods can be reused. No automatic retry runs in the
browser. Source dates and completeness, not publication assumptions, determine
whether a result may raise a prompt.

Sentinel-2 remains on-demand by default. Existing opt-in
`CLIMATE_SENTINEL_AUTO_ENABLED` and daily/monthly provider budgets stay intact.
Its configured maximum parcels applies globally per command run, not per
municipality. Completed NDVI snapshots recheck current ownership and boundary;
NDMI, SAVI and EVI are not mixed into NDVI summaries. Opening this dashboard
never starts satellite processing.

## Data and release boundaries

Rows live in the existing `climate_risk_source_readings` table. They do not
create `climate_risk_assessments`, alter Normal/Watch/Alert/Critical, or add
reported-parcel links. Scoped reads select one latest row per source, exclude
moved/deleted parcels and compare current context. Stored metadata is bounded;
no raw provider payloads, farmer fields or coordinates are retained here.

Release command, module classes, configuration, controller, Blade/CSS and
reviewed guide passage together. Rebuild optimized autoloading and config,
route and view caches. No migration, new service, account operation or general
cache clear is required. Preserve quotas and private configuration. Verify
hosting cron separately and run a bounded initial municipality refresh.

## Primary sources

- [Open-Meteo forecast API](https://open-meteo.com/en/docs)
- [ClimateSERV API and dataset identifiers](https://climateserv2.servirglobal.net/develop-api)
- [UCSB CHIRPS](https://www.chc.ucsb.edu/data/chirps)
- [CHIRPS publication cadence](https://chc.ucsb.edu/data/chirps3)
- [PAGASA ENSO advisories](https://www.pagasa.dost.gov.ph/climate/el-nino-la-nina/advisories)
