# Geofence opacity and logout history fixes

## Changes

- The opacity slider now controls saved boundaries, editable boundaries, and new
  drawing previews. Drafts retain half-strength fill. During editing the saved
  fill is suppressed so it cannot blend with the preview; cancellation restores
  the saved fill at the current slider value. Coordinates, stored colors, and
  snapshot exports are unchanged.
- Authenticated responses and login/session responses carry private, no-store
  cache headers. The shared authenticated layout loads `session-history.js`,
  which hides persisted browser-history snapshots and reloads them through normal
  server authentication. Existing session invalidation remains in place.

## Verification

- 24 JavaScript tests passed, including source-coordinate preservation, save
  failures, renderer caching, editing/drawing opacity, cancel restoration, and
  browser-history events. The two added opacity tests failed before the fix.
- 9 PHP tests passed with 50 assertions using an isolated in-memory SQLite
  database. Coverage includes cache headers and logout invalidation followed by a
  protected-page request and a rejected heartbeat.
- Changed PHP files passed Pint and syntax checks. JavaScript syntax, Blade
  compilation, the logout route, and `git diff --check` passed.
- This does not replace a signed-in live browser check. No farmer records,
  account credentials, database schema, or role permissions were changed.

## Deployment requirements

Install both JavaScript assets in Hostinger's `public/js` and `public_html/js`,
the security middleware, and the layout together. The layout uses the file time
under `public` to version the new asset. Clear compiled Blade views after
installation. No migration or new environment setting is needed.

The private installer verifies previous file hashes, saves rollback copies,
installs only its eight allowed application/documentation targets, and checks
the resulting hashes. Tests and the PCAF gap review remain local. Record the
deployment receipt and remove the temporary scheduling job after completion.

Deployment completed on Hostinger at **2026-09-19 08:53:02 UTC**. All eight
targets passed installed-file checks; private rollback copies were verified and
compiled Blade views were cleared. Both JavaScript files served by the public
site match the tested local files byte for byte. No database changes were made.
The public login returned HTTP 200 with the new private/no-store cache policy.
The temporary deployment job was removed and its absence verified in hPanel.

The signed-in live opacity and Logout → Back checks remain pending an active
browser session; do not report those interaction checks as completed.
