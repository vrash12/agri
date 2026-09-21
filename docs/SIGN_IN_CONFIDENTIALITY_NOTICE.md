# Sign-in confidentiality notice

Implemented September 21, 2026 at the owner's request. The office `/login` page
shows a notice below the Sign In button. It states that access is
provided solely for authorized testing and validation, asks participants to keep
nonpublic information confidential, and restricts sharing or reuse of confidential
materials to reproduce AgriGOV without the owner's prior written permission.

The owner's subsequent checkbox request adds **I have read and understood the confidentiality and testing notice.** The notice and checkbox now remain inside the sign-in form, below its submit button. The checkbox is initially unchecked and required. Native browser validation works without JavaScript; the server also requires an accepted value before checking credentials, so a direct POST cannot bypass it.

Unchecked submission displays “Please confirm that you have read and understood the confidentiality and testing notice.” Missing acknowledgment does not become a failed-password attempt or change the account's last sign-in time; the existing route throttle still applies. After an incorrect password, the acknowledgment and email are preserved for retry, while the password is never flashed back.

This acknowledgment is not a stored signature or separate acceptance record. No permission changes or farmer-portal authentication changes are introduced.
The wording concerns confidential materials rather than claiming exclusive
ownership of an agricultural software idea. Third-party ownership is unchanged.

The notice uses a semantic named aside, a heading, two short paragraphs and a labeled native checkbox. It
uses the shared color tokens and remains visible without JavaScript. Native
credential fields, CSRF, password managers, error recovery and the existing
slideshow remain intact.

Changed runtime files: `app/Http/Controllers/AuthController.php`, `resources/views/auth/login.blade.php` and
`public/css/login-layout.css`. Deploy these together, mirror CSS to both Hostinger public directories and
refresh compiled views. No migration, configuration or dependency change.

Original-notice verification: seven `SharedDesignPresentationTest` tests passed; Laravel Pint,
Blade compilation and whitespace checks passed. Browser inspection verified the
desktop placement and a 390px mobile layout without horizontal page overflow.
Local PHP 8.4 reports existing vendor deprecations; production support remains
PHP 8.1–8.3. Deployed on Hostinger September 21 at 00:38 UTC; the live page and CSS
were verified. See [the production receipt](CAR_DASHBOARD_DEPLOYMENT_2026_09_21.md).

The required-checkbox update is **local only, not yet deployed**. Its isolated sign-in throttle and design suites passed **18 tests / 177 assertions**, including missing/false acknowledgment, successful acknowledged sign-in, password retry, lockout and audit protections. Portal guard separation, regional access and provincial isolation suites passed **40 tests / 384 assertions** with acknowledged office sign-in fixtures. Existing idle-timeout, veterinary and audit test fixtures were updated for the new field; their legacy database-backed suites were not run against the local operational database.

Browser verification used a synthetic local page: unchecked submission stayed on the form, focused the checkbox and displayed the native required message; checking it with the keyboard allowed submission to the isolated preview endpoint. The server-error link focused the checkbox, and the inline error was linked through `aria-describedby`. Desktop/mobile screenshots were reviewed, with no horizontal overflow at 320, 390 or 1440 CSS pixels and no browser console errors. PHP formatting/syntax, Blade compilation and Git whitespace checks passed.
