# Regional supervision

## Scope and implementation plan

The owner requested a Regional Head and provincial heads for Region I, Region II, Region III and Negros Island Region, and confirmed regional read-only oversight with account management across the region, including separate city scopes.

1. Add explicit regions and nullable region foreign keys to provinces and users; keep municipality ownership unchanged.
2. Enforce regional reads in shared scope helpers, policies, audit queries and account management. Preserve provincial and municipal isolation.
3. Add regional assignment to the existing account forms and verify authentication, escalation resistance, data access and concurrent edits.
4. Apply the additive migration after a verified backup, configure only the requested region memberships, and issue four regional and nineteen provincial identities through an audited, private provisioning operation.
5. Verify live scope and sign-in behavior, preserve existing accounts/data, remove the temporary deployment schedule and record the outcome.

## Access

`regional_head` is an office role with a required active `regions.id` assignment and no province or municipality assignment. Only the System Owner can assign/manage Regional Heads. Regional Heads have read-only operational oversight, regional dashboards/maps/exports and access to audit events with a province snapshot belonging to their region. They cannot edit farmers, parcels, assistance, animal-health records, machinery or geofences, and cannot use Backup Folder.

Regional Heads manage provincial Super Admins and lower roles within their region. Their own form permits only profile/password changes. They cannot manage the System Owner, peers, another region or their own privileges. Mutations retain `ConcurrentWrite`, locked-manager reauthorization and fresh version checks. Provincial heads use the existing `super_admin` role and remain restricted to one province; their geofence-management exception remains unchanged.

`MunicipalityAccess::scopeMunicipalities()` and `scopeProvinces()` use numeric region/province ownership. `User::hasUsableScope()` rejects missing, inactive or mixed regional assignments. Names never decide runtime access. Global and unknown-province audit events remain owner-only. Region membership is configured explicitly, has no public editing route, and cannot silently move an already assigned province to another region.

## Approved membership

| Region | Provinces | Separate city scopes included in regional oversight |
| --- | --- | --- |
| Region I | Ilocos Norte, Ilocos Sur, La Union, Pangasinan | None in the existing configuration |
| Region II | Batanes, Cagayan, Isabela, Nueva Vizcaya, Quirino | Santiago City |
| Region III | Aurora, Bataan, Bulacan, Nueva Ecija, Pampanga, Tarlac, Zambales | Angeles City, Olongapo City |
| Negros Island Region | Negros Occidental, Negros Oriental, Siquijor | Bacolod City |

Existing municipality IDs, province IDs, independent-city separation and accounts remain unchanged. CAR scopes (Benguet, Baguio City and Mountain Province) are outside this requested setup. The configured Pangasinan scope retains its existing geographic grouping; this does not redefine legal city classifications.

## Deployment

Back up and verify the existing database. Apply only `2026_09_21_000100_add_region_supervision.php` against the verified baseline; do not run the legacy baseline migrations or demo seeders. Install the Region model, support/command, modified scope/policies/controllers/observer, and changed account/dashboard/layout/audit views. No new public asset or Composer dependency is required. Refresh application caches after installing files.

Run `php artisan region-access:configure --owner=<existing-active-system-owner-id>` explicitly. `RegionSupervision` resolves the 23 existing province/city scopes, stops on missing/inactive/conflicting assignments, records owner-only audit events and rolls back the full configuration on failure. Repeat configuration preserves IDs and audit history. The command does not issue accounts or alter credentials.

Account issuance is separate: four Regional Heads and nineteen provincial Super Admins with owner-requested `.test` login identifiers. Existing emails must never be overwritten or reset implicitly. Password hashes belong only in the private provisioning operation and database; never commit account passwords/hashes or include them in receipts. The one-time owner-approved initial credential does not change the normal minimum-length or breached-password checks in account forms. `.test` identifiers are sign-in names, not deliverable mailboxes.

Rollback disables Regional Head identities before removing the additive foreign keys/table. Do not roll back against active regional sessions without maintenance and an explicit recovery decision. Application rollback can instead leave the additive schema in place; prior application code rejects the unfamiliar role.

## Verification status

The focused account-password, province-isolation, province-management, regional-access and presentation suites passed (53 tests across the completed runs). Changed PHP passed Pint and syntax checks; Blade compilation, account routes and whitespace checks passed.

A transaction-backed rehearsal on the verified local database passed 394 checks, including all 23 identities, account-management decisions, existing-row preservation and view rendering. The rehearsal rolled back every temporary identity and region membership; the additive local migration remains installed. Local rehearsal municipality coverage was Region I 125, Region II 93, Region III 130 and Negros Island 63; production has additional existing municipality rows.

Hostinger's read-only preflight passed at 2026-09-20 20:26 UTC using PHP 8.3.33. All 21 runtime files matched the reviewed base, all requested scope rows were available and no planned account email existed.

After the owner's final activation confirmation, production deployment completed at **2026-09-20 20:29:08 UTC** (September 21, 04:29 in the owner's timezone). The installer verified a database backup and source backups, installed 21 runtime files, applied only the additive regional migration, created **4 Regional Heads and 19 provincial Super Admins**, refreshed views/configuration/routes, verified installed hashes and unchanged environment, and brought the site online. The private production receipt passed **394 checks**, including stored credential-hash equality, usable scopes, policy decisions, account/dashboard/map/audit rendering and preservation of existing records. No existing account credentials or municipality ownership were changed.

Production scope totals reflect existing active municipality rows: Region I **127**, Region II **93**, Region III **136**, Negros Island **63**. The separate city scopes remain separate from their surrounding province administrators. Both temporary preflight and activation schedules were removed. Private deployment receipts and backups remain outside the web root. Office login and homepage returned HTTP 200 afterward; login retained private, non-storable response headers. Authentication routing was covered by automated tests; interactive sign-in was not performed in the owner's browser session.

## CALABARZON addition — September 22, 2026

After the explicit CALABARZON boundary import, `region-access:configure --owner=<id> --region=region4a` configures Cavite, Laguna, Batangas, Rizal, Quezon and the separate Lucena City scope. Other region memberships remain unchanged. The original no-option command retains the original four-region setup. Account creation is separate; no credentials or accounts are generated by this command. See [CALABARZON_BOUNDARY_SOURCES.md](CALABARZON_BOUNDARY_SOURCES.md) for release status.

CALABARZON was configured on Hostinger with release `496b706`; the Regional Head login and 142-boundary visibility were verified. Provincial account selection remains pending.

## MIMAROPA addition — September 23, 2026

After the explicit `MimaropaBoundarySeeder`, `region-access:configure --owner=<id> --region=mimaropa` links Marinduque, Occidental Mindoro, Oriental Mindoro, Palawan, Romblon and the separate Puerto Princesa City scope. Calapan remains a component-city workspace under Oriental Mindoro. The configuration is atomic, audited and idempotent, rejects conflicting memberships, preserves other regions and accounts, and does not expand the original no-option command. See [MIMAROPA_BOUNDARY_SOURCES.md](MIMAROPA_BOUNDARY_SOURCES.md) for source checks and deployment status. This addition issues no accounts.
