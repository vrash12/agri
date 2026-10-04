# Rice seed import validation — October 4, 2026

## Long Lot Series follow-up

The initial validation removed the 500 error but still rejected the owner's
workbook at Excel row 9. Lot Series legitimately needs room for longer batch
references. This follow-up expands only that nullable column from VARCHAR(120)
to TEXT; import and manual-entry validation share a 10,000-character limit,
which safely fits TEXT even with four-byte Unicode characters. Full values are
preserved for display, export and exact repeat-import matching. Other text
limits, permissions, municipality scope and transaction behavior are unchanged.

Deploy code and `2026_10_04_000100_expand_rice_seed_lot_series.php` in one
maintenance window with a verified private backup. Apply only that migration
by path using `--force`; never run the incomplete baseline or visitor migration.
The migration preserves the original charset and collation. Down refuses to
reduce the column while any reference exceeds 120 characters. SQLite already
stores VARCHAR as text, so tests check persistence/rollback guards; actual
MySQL column expansion must be checked on Hostinger.

Local checks passed 14 import tests / 46 assertions and 18 operations view tests
/ 66 assertions, including long-reference import/reimport, multibyte boundaries,
atomic failure and refusal of lossy rollback. Deployment of this follow-up is
pending. No actual confidential workbook is available to the coding agent.

## Verified production cause

An authorized read-only Hostinger inspection identified the October 4 06:25 UTC
import failure: MySQL `SQLSTATE[22001]`, error 1406, because `lot_series` exceeded
its existing `varchar(120)` limit. The exception originated in the import save,
not the GET import form. Production was running commit `4033cc2`, PHP 8.3.33,
with no tracked runtime changes. Logs were inspected with cell contents and SQL
bindings withheld. No operational or account data was changed.

## Deployed fix

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
PHP 8.4.10. The harvest projection is mocked in the isolated import regression
suite; its production behavior is preserved and was not re-exercised live.

The owner explicitly authorized push and deployment. Runtime `3e81caf` was
pushed to GitHub main and installed on Hostinger by `git pull --ff-only origin
main` from baseline `4033cc2`. No migration, dependency update, account change
or operational import ran. Previously pushed visitor and satellite UX code was
included in the fast-forward; visitor provisioning and its additive migration
were not run. The uncommitted Land Plots UI work was preserved locally.

Before the pull, a verified private backup captured 21 tables / 8,304 rows,
3,277,550 compressed bytes, SHA-256
`b48e4e4464375af993880ce1e14a788cd6813fa796605dba596f999047182223`.
Private archives preserve the prior tracked runtime, served public directory
and environment. All 21 table fingerprints and the environment hash remained
unchanged after deployment and the read-only render check.

Configuration, route and view caches rebuilt. Five intervening public assets
were mirrored to `public_html` and their hashes match `public`. Hostinger PHP
8.3.33 passed syntax checks, an authorized import-page render, and the exact
120/121-character validation boundary. Homepage and office sign-in returned
HTTP 200; the unauthenticated import URL returned its expected HTTP 302. The
tracked server checkout was clean. No actual confidential workbook was
resubmitted during verification; the original oversized cell still needs
correction before that workbook can import successfully.

Credentials and database backups must remain outside source control and public
directories. Follow `docs/GITHUB_DEPLOYMENT.md` for private backups and release
verification.
