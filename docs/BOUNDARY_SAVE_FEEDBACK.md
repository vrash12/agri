# Boundary save feedback

## Report and observed behavior

The user reported that Save boundary appeared not to work when changing a name or color. The live browser had reached the 15-minute session-timeout login page. After the user signed in again, submitting Paniqui's existing name and color successfully closed the editor and reloaded its workspace without a browser error. This confirmed a working save path; it did not establish the exact cause of the earlier attempt.

Previously, save failures appeared only in a toast that disappeared after 4.8 seconds. There was no pending indicator or duplicate-click guard. A successful HTML login response could also be mistaken for an empty JSON success before causing an error.

## Changes

- Saving disables the Save boundary button and changes its label to Saving.
- Local validation and server errors remain beside the controls in a focusable alert; errors and entered values remain after the toast disappears.
- HTTP 401/419 and unexpected HTML login redirects display session-renewal guidance.
- Name/color-only saves omit geometry when type and coordinates match, regardless of GeoJSON object property order.
- Changing an active shape requires replacement confirmation both in the interface and on the server.
- Success requires a returned boundary ID. An older save response cannot close a newer editor or return the user to an older selection.

No server authorization, stored geometry, timeout duration, or schema changes are required.

## Verification and deployment

Run the two municipality boundary JavaScript suites and `tests/Feature/MunicipalityGeofenceTest.php`. Compile Blade views and check JavaScript syntax and diff whitespace. The added regressions cover object property order, unconfirmed shape changes, duplicate clicks, validation/session failures, HTML login responses, and an editor changed while a save is pending.

Deploy the Blade view and both JavaScript copies when Hostinger serves `public_html` separately from Laravel's `public` directory. Clear compiled Blade views. Existing pages must reload to receive the new script and inline error element. File backups should be verified before replacement. The installer needs no database mutation.
