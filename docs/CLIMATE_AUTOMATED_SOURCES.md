# Automated climate evidence

## What runs automatically

`climate:refresh-sources --provider=open-meteo` refreshes each active
municipality's saved boundary reference point every six hours. It stores a
bounded seven-day forecast summary and the provider date range. The existing
dashboard forecast endpoint still works and reuses its normal cache.

`climate:refresh-sources --provider=chirps` runs daily. It queries the free
ClimateSERV CHIRPS dataset for a recent 30-day area-average rainfall period,
leaving the latest two days out because preliminary CHIRPS data has a short
reporting delay. It compares that period with a rolling three-year reference
when enough reference periods are available. This is a planning indicator, not
an official drought category.

Unscoped scheduled runs process a rotating batch of up to 100 municipalities,
so a large national reference set does not create a single provider burst. The
cursor is stored in the normal application cache; explicitly scoped manual
runs (`--municipality=12`) always refresh that municipality immediately.

Sentinel-2 is intentionally on-demand by default because processing consumes
the existing Copernicus account allowance. `--provider=sentinel2` is available
only after `CLIMATE_SENTINEL_AUTO_ENABLED=true`; it is capped by
`CLIMATE_SENTINEL_AUTO_MAX_PLOTS` and stores only the same bounded NDVI summary
already produced by a parcel Satellite field check. It never labels damage.

The scheduler is registered in `app/Console/Kernel.php`. Hostinger must run
`php artisan schedule:run` from its existing cron entry. A manual refresh can
be limited to an ID:

```text
php artisan climate:refresh-sources --provider=open-meteo --municipality=12
php artisan climate:refresh-sources --provider=chirps --municipality=12 --force
```

## Evidence boundaries

Automated rows live in `climate_risk_source_readings`. They are not
`climate_risk_assessments`, do not alter Normal/Watch/Alert/Critical, and do
not mark parcels affected. Staff still verifies official PAGASA coverage and
records an office assessment. Missing, delayed, stale or failed providers are
shown as unavailable; the system never converts missing data to zero rainfall.

The source links are Open-Meteo, CHIRPS v3 via ClimateSERV and the Copernicus
Data Space Sentinel-2 Statistical API. Raw provider payloads, coordinates and
farmer fields are not stored in the source snapshot table.
