# System Owner and province supervision

The System Owner oversees all configured provinces. Each Super Admin supervises one assigned province. Provincial agriculture and veterinary staff also receive an explicit province assignment; municipal accounts keep their municipality assignment. Records in other provinces are retained and hidden from accounts outside their scope.

| Account | Visibility | Administration |
| --- | --- | --- |
| System Owner | All configured provinces and global/unassigned audit events | Province Super Admins, lower roles, and geofences; profile edits to own account |
| Benguet Super Admin | Benguet workspaces, records, reports, and province audit events | Benguet staff accounts and geofences |
| Tarlac Super Admin | Tarlac workspaces, records, reports, and province audit events | Tarlac staff accounts and geofences |
| Provincial Staff | Assigned province | Operational records within that province |
| Provincial Veterinary Office | Assigned province, Animal Health only | Animal Health records within that province |
| Municipal Head / Staff | Assigned municipality | Existing operational permissions; a head manages only that municipality's staff |

System Owners and Super Admins have read-only access to ordinary operational records and cannot open Backup Folder. Super Admins cannot manage another Super Admin or the System Owner. The web interface cannot create an additional System Owner, delete any System Owner, or change an administrator's own role, assignment, or active status.

## Province ownership

`provinces.id` is the supervision boundary. Provincial roles use `users.province_id`; municipal records inherit `municipalities.province_id`. The existing municipality `province` text remains for display and legacy imports. It must not be used to authorize a request.

The initial migration maps the existing Benguet display group, including the separate Baguio City workspace, to Benguet supervision. This is an application office assignment, not a change to Baguio's administrative status. Any future change to that supervision requires an explicit ownership decision and audit review.

`MunicipalityAccess` restricts queries before filters, totals, pagination, exports, weather, and map serialization. Policies separately protect record-bound actions. Missing or inactive assignments are rejected at sign-in and on authenticated application/API requests. Hidden form fields and client-side selectors are never trusted as authorization.

Audit events retain a province snapshot. Province administrators see only matching snapshots, including after the actor moves or is deleted. Global events, records with unknown ownership, and events that transfer records between provinces remain owner-only. Known historical municipality events are backfilled; historical global events are not assigned to a province by guessing the actor's current office.

## Explicit deployment steps

1. Take a database backup and confirm the existing baseline schema and migration history. Do not run the full migration set blindly on this repository's legacy database.
2. Apply only the reviewed additive migration to the intended environment:

   ```sh
   php artisan migrate --path=database/migrations/2026_09_08_000100_add_province_supervision.php
   ```

3. Choose the existing active administrator account ID that should become System Owner. Use explicit province names when assigning previously unassigned provincial staff and preparing province Super Admin accounts:

   ```sh
   php artisan province-access:setup OWNER_ID --staff-province=Tarlac --admin-province=Benguet --admin-province=Tarlac
   ```

   `OWNER_ID` is a placeholder, not a new account. The command keeps that account's name, email, and password. It requires an active Super Admin or the existing System Owner and rejects a different existing owner. It does not run automatically on deployment.

4. Sign in using the existing owner login. Open **User Management**, edit each prepared Super Admin, replace its placeholder email if needed, set and confirm its password, enable **Account active**, and save.
5. Verify Benguet and Tarlac access separately, including attempts to open a foreign municipality through direct URLs. Keep the System Owner credentials private.

Prepared account addresses are `superadmin.benguet@agri-ms.test` and `superadmin.tarlac@agri-ms.test`. These are placeholder login identifiers; no email is sent. Accounts start inactive with separate, randomly generated password hashes whose source values are discarded. Activation requires a newly entered password. The setup command is repeatable and will not duplicate existing provincial administrators or overwrite their passwords.

The setup transaction records attributed administration events, promotes only the explicitly selected owner, assigns only previously unassigned provincial staff to the explicit staff province, and creates only missing requested Super Admin accounts. Municipal ownership, geofences, farmer records, and assistance records remain intact. Unsupported legacy roles are not promoted or granted access.

## Rollback

Rollback changes security scope and must be coordinated with a code rollback and an account-assignment backup. The migration's `down()` removes the new columns and province table; it does not delete municipalities, users, geofences, or audit events and does not guess which account should regain the old global Super Admin role. Restore reviewed account roles/assignments explicitly before returning to code that does not recognize `system_owner`. Never perform an automatic production rollback of province isolation.

Reference-boundary imports use `ReferenceBoundaryAccess` to select the active System Owner or an active Super Admin assigned to the target province. New reference workspaces receive a province foreign key. No importer may attribute a Benguet change to a Tarlac-only Super Admin or vice versa.

## Local application and verification — 2026-09-08

The reviewed migration and explicit setup were applied to the local development database after a database backup outside the repository. The sole existing active Super Admin became System Owner with the same name, email, password, and active status. Existing unassigned provincial staff were assigned to Tarlac; municipal account assignments were preserved. The two provincial Super Admin accounts listed above were created inactive and still require passwords and activation in User Management.

The System Owner can select all 32 active workspaces. Scope checks using unsaved active account models returned only the 14 Benguet workspaces, including Baguio, or only the 18 Tarlac workspaces. The prepared accounts themselves remained inactive throughout verification. Existing values in ten operational tables, including municipality and geofence records, matched their pre-migration fingerprints; existing audit values and all existing passwords were preserved. Running setup again changed neither accounts nor audit counts.

The focused isolated SQLite suite passed 86 tests with 1,175 assertions, covering province access, account management, reports/audits, migration rollback, geofences, and reference imports. Blade compilation and changed PHP syntax checks passed. Fifteen synthetic account-page browser states passed at 390, 768, and 1440 CSS pixels, including role-dependent required fields and native controls with external CDN requests blocked. Production was not deployed; live sign-in as the prepared provincial accounts awaits password assignment and activation.
