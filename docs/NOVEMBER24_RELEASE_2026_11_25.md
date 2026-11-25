# November 24 features — November 25 release verification

Status: deployed and verified on November 25, 2026. Runtime `43e9b9b6be9d6220b6bff00b90e798fa2a3e83ee` is live at https://agri-lgu.online through GitHub main and Hostinger fast-forward installation.

Includes period reports and comparison CSV, harvest import/reminders, barangay and
focused Assistant answers, map questions/visit planning, parcel briefings and
explicit category-only AI advice, plus mobile record layouts.

Fixed during release review: local-month UTC registration boundaries, exclusion
of inactive supervising provinces from scheduled reports, foreign-municipality
stale harvest links in map notes, report-form spacing, and previous-month year
selection across New Year.

Verification: 1,336 focused PHP tests / 12,602 assertions passed; 381 JavaScript
tests passed. The final report/map regression suite has 17 tests / 206 assertions.
Separate disposable MariaDB checks passed: fresh migration, JSON map queries,
XLSX import and XLS repeat protection, report generation and five full page renders.
Synthetic report/import/parcel pages were inspected in a browser, including a
390px viewport and switching monthly/quarterly controls. This does not certify
physical devices, screen-reader audio or staff acceptance. Real provider output
and signed-in Google 3D map interaction were not exercised.

Deployment requires only the explicit generated_reports migration after a verified
private backup; never run the entire historical migration chain on production.
Mirror five changed/new public assets, rebuild autoload/config/routes/views, and
preserve environment and provider budgets (no general cache clear). The monthly
report command runs through the existing scheduler; no email or queue is required.
The separate demo remains pinned. Rollback restores runtime and public assets,
retaining the additive table if migration already succeeded.

## Production verification

A private DatabaseBackup archive was verified before installation. All 27 existing
tables and 10,077 rows were preserved, including operational data and accounts.
Only generated_reports and its single migration receipt were added. The archive
remains empty until staff generate reports or the scheduled monthly run occurs.
Private environment/configuration and provider allowance counters matched their
pre-deployment fingerprints. No provider requests or sample operational records
were created. The demo was not deployed.

Live read-only checks rendered report index/form, harvest import, dashboard and
parcel briefing using existing authorized scope. Actual MySQL report aggregation,
map crop/no-harvest/coverage queries and parcel visit-priority evaluation passed.
The combined read-only verification took 0.49 seconds (not a load test).
Public homepage/login returned HTTPS 200; guest requests for reports/import
redirected to login. All five served CSS/JavaScript assets exactly matched Git.
Laravel schedule:list confirms reports:generate-monthly at 04:30 office time on
the first day of each month. The existing host scheduler configuration was retained;
the future scheduled execution itself was not observed.

The first install was automatically rolled back after a private verifier called a
PHPUnit-dependent assertion unavailable in production. The application migration
had succeeded. The verifier was changed to a runtime-only condition, and the
retry passed all checks with the empty additive table retained. Previous runtime
and cache/asset backups and rollback diagnostics remain outside the web root at
/home/u330835917/.private/nov25-release. No database rollback or restore ran.

Remaining limitations: live OpenAI answer quality, live Google 3D highlight
interaction, physical devices, screen-reader audio and staff approval are not
certified by this release. The map layer uses planning heuristics, not a trained
crop-health model. XLS/XLSX import persistence was tested in isolated MariaDB,
not by adding artificial harvests to production.
