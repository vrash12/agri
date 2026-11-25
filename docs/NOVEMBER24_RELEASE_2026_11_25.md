# November 24 features — November 25 release verification

Status: tested locally; deployment pending.

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
