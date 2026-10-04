# Rice seed import validation — October 4, 2026

## Verified production cause

An authorized read-only Hostinger inspection identified the October 4 06:25 UTC
import failure: MySQL `SQLSTATE[22001]`, error 1406, because `lot_series` exceeded
its existing `varchar(120)` limit. The exception originated in the import save,
not the GET import form. Production was running commit `4033cc2`, PHP 8.3.33,
with no tracked runtime changes. Logs were inspected with cell contents and SQL
bindings withheld. No operational or account data was changed.

## Local fix

`RiceSeedImportValidation` checks persisted text lengths before each save and
returns the Excel row, column label and permitted character count. It never
echoes a cell value and never truncates identifiers. A failure inside the
existing transaction rolls back prior inserts, updates and harvest projections.
The first rejected row is reported; correct it and upload the workbook again.

The spreadsheet reader is limited to XLS/XLSX. Recognized reader exceptions
produce a safe recovery message; unexpected failures remain reportable.
Worksheet objects are released after conversion. The existing 10 MB upload
limit, scope resolution, policies, matching rules and successful-import behavior
remain in place. This change does not enlarge columns or authorize production
imports. The existing reader still materializes the selected worksheet; a
separate large-workbook streaming change is outside this fix.

The import screen explains the 120-character limits for Lot Series, seed
varieties and sowing labels. Rejected imports preserve the municipality choice;
the browser requires the file to be selected again.

## Verification and release

Focused tests use disposable in-memory SQLite and synthetic workbooks. They
cover valid imports, repeat updates, length boundaries including multibyte text,
rollback of inserts and updates, confidential-message redaction, farmer matching
within municipality scope, forbidden oversight writes and unreadable files.
Production MySQL confirms the column limits; no live workbook was submitted.

Local verification passed 12 import tests / 41 assertions and 18 operations
presentation tests / 66 assertions, Pint, changed-file PHP syntax checks, Blade
compilation, both import routes and `git diff --check`. These local tests ran on
PHP 8.4.10; production's PHP 8.3.33 was inspected but the patch has not been run
there. The harvest projection is mocked in the isolated import regression
suite; its production behavior is preserved and was not re-exercised live.

Deployment is pending explicit owner authorization. No migration, dependency
update, account change or data import is required. Deploy the controller,
support class and import view together through GitHub and Hostinger
`git pull --ff-only`, refresh the optimized autoloader if required by its
configuration, and rebuild view/route caches. Preserve unrelated local Land
Plots UI work and review intervening commits before advancing production from
`4033cc2`; the already-pushed visitor work is a separate release scope.

Credentials and database backups must remain outside source control and public
directories. Follow `docs/GITHUB_DEPLOYMENT.md` for private backups and release
verification. This document records a local fix, not a completed deployment.
