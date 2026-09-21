# GitHub-to-Hostinger deployment

The owner selected GitHub deployment on September 21, 2026. The source repository is `https://github.com/vrash12/agri`; production follows `main`. Prefer reviewed Git commits, a normal push, and `git pull --ff-only origin main` over ZIP/File Manager releases. Never force-push a deployment or discard unexplained server edits. The owner must authorize each production release.

## Normal release

1. Inspect the local working tree and isolate the approved changes. Keep unrelated unfinished work out of the release. Run focused tests, Pint, syntax/Blade checks and a secret review before committing. Never commit environment files, private backups, account directories, credentials or local output.
2. Check the live checkout, PHP version and database baseline over SSH. Record the current Git revision and verify that tracked runtime files are clean or reconcile explained manual changes after backup. Do not print environment values or credential files.
3. Push the reviewed commit to GitHub. On Hostinger, fetch and review the intended revision before entering a short maintenance window. Back up the database and affected application/public files into a private directory outside the document root.
4. In the application directory, run `git pull --ff-only origin main`. Confirm HEAD is the approved revision. Production runs PHP 8.3; use the lockfile and production Composer options when refreshing dependencies/autoloading. Do not run Composer update.
5. Run only the release's explicitly reviewed additive migrations, by path, after verifying the existing baseline. Do not run legacy migrations, DatabaseSeeder, geography imports or other data setup automatically.
6. Hostinger currently serves a separate `public_html` directory. Mirror changed tracked public assets from `public` to the same relative location in `public_html`. Preserve its existing entry point, configuration and protected storage. Do not use a deletion sync on either public directory.
7. Refresh configuration, route and view caches. Verify preservation, access scope, rendered pages and both public asset copies, then leave maintenance mode. Check public HTTP and the live interface. Record the installed revision and backup receipts.

SSH authentication is interactive or uses an owner-approved existing key. Never include a password in a command argument, script, repository, deployment document or log. Existing verified SSH host keys must remain enforced.

## One-time reconciliation

The old server checkout was at `6822f9e`, while individually deployed runtime files matched GitHub `4546096` except for the known CAR/dashboard/sign-in release. A metadata-only mixed reset to that verified baseline preserves working files; it is only appropriate for this audited transition, after recording the old revision. Back up and preserve the known manual changes and any incoming untracked-file collisions before the subsequent fast-forward pull. A normal future release should not need this reconciliation.

The scoped September 21 release keeps the already installed CAR/dashboard changes, adds saved geofence appearance and the notice acknowledgment, and excludes the independent unfinished farmer-ID display/search work. Its only schema change is `2026_09_21_000100_add_fill_opacity_to_municipality_boundaries.php`. Its only intentional data change is the explicitly requested white/20% appearance for 93 Region II active boundaries, with attributed audit events.

`scripts/releases/2026_09_21_geofence_appearance.php` provides CLI-only `snapshot` and `verify` phases for this one-time release. It requires maintenance mode, a private recovery directory outside the app, and an explicit authorized owner ID. It verifies backup readability, existing-table fingerprints, boundary geometry, environment integrity, exactly the expected migration and 93 audits, matching map payloads, rendered controls and a zero-change repeat preview. It does not perform Git changes, migrations or style writes itself.
