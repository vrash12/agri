# Harvest Excel template and import — November 24, 2026

Release status: deployed in `43e9b9b` on November 25. See [verification and remaining limits](NOVEMBER24_RELEASE_2026_11_25.md). Local-only descriptions below are historical implementation checkpoints.

Status: **local, not deployed.** Makes routine recording of non-rice harvests
practical: offices that keep harvest figures in spreadsheets can download a template
and upload it, instead of typing each record. Single-record entry is unchanged.

## Using it

1. **Harvest records → Import from Excel** (shown only to accounts that can manage
   operational records). Provincial staff choose an authorized municipality.
2. **Download template.** The `Harvests` sheet has headers only; `Allowed values`
   lists commodity, season and unit codes with their labels.
3. Fill one row per harvest and upload the `.xlsx`/`.xls` file (10 MB, 2,000 rows).

| Column | Required | Accepts |
| --- | --- | --- |
| Farmer ID | No | AgriLGU ID (`AGRI-F-000123`), FFRS or RSBSA number of a farmer in the chosen municipality |
| Commodity | Yes | Code or label, e.g. `corn` or `Corn` |
| Variety | No | Text up to 120 characters |
| Season | No | `dry`/`wet` or the label |
| Harvest Year | Yes | 1990 to next year |
| Date Harvested | No | Excel date or `YYYY-MM-DD`; within one year of the harvest year, not in the future |
| Area Harvested (ha) | No | 0–100,000 |
| Quantity | Yes | 0–99,999,999 |
| Unit | Yes | Code or label (kg, sacks, cavans, bags, metric tons, pieces) |
| Notes | No | Up to 1,000 characters |

## Rules

- **All or nothing.** Every row is validated with the same rules as the single-record
  form (`App\Modules\Agriculture\Application\HarvestFieldRules`, now shared by
  `StoreHarvestRecordRequest`). If any row fails, nothing is saved and the page lists
  up to 20 row errors ("Row N: …") plus a remainder count. Cell values are not echoed.
- **Farmers stay in scope.** A farmer ID must match exactly one farmer in the chosen
  municipality inside the account's scope. Unknown IDs and other municipalities'
  farmers get the same message, so the import cannot probe other offices. Ambiguous
  IDs are rejected.
- **Re-uploading is safe.** A row identical to an existing direct (non-projected)
  harvest — same municipality, farmer, commodity, variety, season, year, date,
  quantity and unit — is skipped as *Already recorded*; repeats inside the file are
  skipped as *Repeated in file*. Area and notes are not part of the comparison.
- The import never links parcels or planted sections; use the single-record form for
  that. Rice harvested from an assistance release is still recorded on the release.
- Formulas are read as the value Excel last saved; nothing is calculated on the server.
- Concurrent imports into one municipality are serialized (municipality row lock
  inside the transaction after parsing/validation) so two uploads cannot both pass
  the duplicate check. One audit summary with counts only is recorded per import;
  rows are bulk-inserted in chunks of 250, so per-row model audit entries are not written.

## Files

`app/Modules/Agriculture/Application/HarvestWorkbook.php`, `HarvestFieldRules.php`,
`app/Http/Requests/StoreHarvestRecordRequest.php`, `app/Http/Controllers/HarvestRecordController.php`
(`importForm`, `template`, `import`), `routes/web.php` (`harvest-records.import.form`,
`harvest-records.import.template`, `harvest-records.import` throttled 6/min),
`resources/views/harvest_records/import.blade.php` and the index link.

`HarvestWorkbook` repeats the small AgriLGU ID parse from `FarmerIdentifier` because
importing it would create an Agriculture → Farmers → Land → Agriculture module cycle;
tests build IDs with `FarmerIdentifier::format()` so the two cannot drift silently.

## Verification (local, SQLite)

`tests/Feature/HarvestImportTest.php` (15 tests): non-rice success, all-or-nothing,
other-municipality and ambiguous farmers, repeat import no-op, code and label inputs,
row limit, missing columns, unreadable file, formula cells, error truncation,
read-only and veterinary refusal, provincial municipality choice and the template.
`HarvestRecordModuleTest`, `HarvestFromReleaseTest`, `FarmerIdentifierWorkflowTest`
and `ModularArchitectureTest` still pass. A throwaway 2,000-row import took about
5.4 s, 23 queries and ~90 MB peak locally. Not exercised on MySQL, with an `.xls`
upload, or in a browser.
