# Satellite field check request limits

October 12, 2026: implemented locally; not deployed yet.

## Problem and correction

The previous numeric `throttle:6,1` and `throttle:12,1` routes shared the same
authenticated account signature as unrelated numeric throttles. Loading map
data could increment that counter even when the user had made no recent
satellite search. Waiting while ordinary activity continued could therefore
still lead to “Too Many Attempts.”

`RouteServiceProvider` now registers two named limits, keyed by authenticated
account: `satellite-observations` allows six requests per minute;
`satellite-images` allows twelve. The names give each operation its own counter
and isolate it from other users and normal map activity. Authentication, CSRF,
idle/account scope, policies and municipality ownership remain in place. These
limits apply even to cached results, as before.

A rejected request returns HTTP 429 with a safe operation-specific message,
`Retry-After` and matching `retry_after` seconds. The shared modal/full-page
satellite script pauses the affected control, displays a countdown and returns
control to the user at expiry. It uses elapsed time even in a sleeping tab,
honors bounded waits up to five minutes and cancels timers when the page closes.
It sends no automatic retry. Date inputs remain editable and search duplicate
submissions remain blocked while a request is in flight.

Provider quota and local daily/monthly budget messages remain separate: they
are not mislabeled as a one-minute retry. `SatelliteRequestBudget` keys, limits,
counter values and namespaces are unchanged. No cache clear or allowance reset
is needed to activate the isolated short-window limits.

## Verification and release

Synthetic PHP coverage reproduces fifteen unrelated map requests before a
satellite search, exhausts each satellite bucket independently, checks a second
account and expiry, and confirms provider cache hits do not produce new paid
calls. JavaScript coverage runs the real page handlers with fake HTTP/timers to
check duplicate prevention, independent image/search waits, expiry without a
request, provider-budget messages and timer cleanup.

Release `RouteServiceProvider.php`, `routes/web.php` and
`public/js/parcel-satellite.js` together with the tests and guidance. Mirror the
script to Hostinger's separate `public_html/js` with 0644 permissions and rebuild
configuration/routes/views. Never clear the general cache or provider counters.
No migration, dependency, account, environment or operational data change is
required. Live signed-in Copernicus requests are not part of these synthetic
checks and are not required to test request-limit isolation.

Local acceptance: 240 JavaScript tests passed; 35 satellite/provider-usage PHP
tests completed with 255 assertions and no failures (existing missing
`.env.testing` warnings). Pint, PHP/JavaScript syntax, route middleware inspection,
Blade compilation and whitespace checks passed.
