# Multiple animal services for one owner

## Workflow

Record the owner and municipality once, then add animal/group rows. For two
dogs receiving the same service and one cow, use Dog / 2 served and Cattle /
Cow / 1 served, each with its own actual product and service. For dogs with
different names, details or medicines, use separate rows of 1 each. Only list
animals receiving a service. The resulting counts are service coverage, not
unique owned animals or a permanent inventory.

Up to 20 rows save together. A validation or persistence failure saves none,
including rolling back per-row audits. Every successful row uses the existing
`anti_rabies_vaccinations` model/table; reports, owner lookup and individual
version-checked edits remain compatible. There is no new batch identifier or
collective edit/delete operation. A later service visit legitimately adds
another service record for the same animal.

Owner lookup stays municipality-scoped, returns recorded group counts, and is
bounded to the latest 200 services with a partial-history notice. This is
previous service history, not proof of current ownership. When selecting a
prior animal, the form reuses identity/count fields only and never copies old
medicines or treatment instructions. An empty starting row can be reused.
Owner names remain the existing matching key; distinct people sharing a name
are not resolved by this change.

## Implementation and checks

`StoreAnimalHealthServicesRequest` validates shared owner fields and a bounded
list of rows. Its explicit per-row field allowlist rejects nested ownership
or unrelated fields. `AnimalHealthServiceRules` is shared with single-record
updates. Legacy flat create submissions retain their former defaults; new
rows require an explicitly entered product. MunicipalityAccess and the
existing policies retain municipal/provincial/veterinary isolation and
read-only oversight. CSRF and synchronized route middleware remain intact.

`RecordAnimalHealthServices` writes through `ConcurrentWrite` in one
transaction, invoking existing model audits. Species/count aggregate reports
continue to read existing records. No database schema or provider integration
changes are required.

Focused synthetic SQLite tests cover two dogs and one cow, separate medicines,
audits, atomic failure, validation recovery, follow-up dates, twenty-row bounds,
ownership field injection, scoped provincial/veterinary access, denied guest
and oversight writes, legacy creation, individual versioned edit, and bounded
owner lookup. Synthetic headless browser checks cover desktop and 390px form
layout, add/remove, row totals, unique live IDs, required validation and no
overflow. JavaScript uses text/option nodes for lookup content, aborts stale
requests and preserves the last valid state without inserting owner data as
HTML. No real animals, owner data or medicines were used in browser checks.

Verification passed 37 focused PHP tests / 163 assertions, Pint on all six
changed/new PHP files, PHP/JavaScript syntax checks, Blade compilation, all
seven existing Animal Health routes and `git diff --check`. Synthetic browser
checks additionally verified selecting a prior animal into the empty first
row, literal display of markup-like names, count preservation, sequential
form names after removal and the 20-row button limit. Local PHP was 8.4.10;
the application's supported production PHP 8.3 has not been exercised for
this follow-up. No live MySQL, production browser or real-user operation ran.

## Deployment status

Local only; not pushed or deployed. Deploy the controller, Form Request, shared
support classes, create/edit form partials and
`public/js/animal-health-services.js` together through the normal GitHub and
Hostinger release. Mirror the script to `public_html/js` with readable 0644
permissions, rebuild views/routes, and verify the create form and owner lookup.
No migration, data conversion or account operation is needed. Existing records
must be preserved. User approval is required for a production release.
