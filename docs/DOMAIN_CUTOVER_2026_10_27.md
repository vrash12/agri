# AgriLGU primary domain — October 27, 2026

The owner purchased `agri-lgu.online` and explicitly requested live deployment.
Hostinger confirmed the registration Active and connected it through **agritarlac.online
→ Domains → Parked Domains**. The alias serves the existing public root and
application; no duplicate installation or database was created.

Application runtime remains `6cdf65a` (checkout `5106355` before this documentation
update). The configuration and database preservation check completed at
**04:35 PHT on October 27** (`2026-10-26T20:35:40Z`).

## Hosting and application configuration

- Main site: `https://agri-lgu.online`.
- The private environment changed only `APP_URL` to that address. Configuration
  cache was rebuilt; application key, database credentials, provider configuration,
  session/cache settings and host-only cookies were retained.
- The original application remains at
  `/home/u330835917/domains/agritarlac.online`, with its existing `public_html`
  entry point and scheduled commands. Future releases still target that directory.
- The shared public `.htaccess` redirects GET/HEAD requests only from the exact
  `agritarlac.online`, `www.agritarlac.online` and `www.agri-lgu.online` hosts to
  `https://agri-lgu.online`, retaining the URI and query string. Subdomains do not
  match the redirect. Unsafe requests are not forwarded across domains; old open
  forms should be reopened on the new host. Existing printed QR paths retain
  their tokens through the redirect; no token rotation ran.
- `LGU_WEBSITE_DOMAIN=agritarlac.online` remains intentional. Published Mayantoc
  stays on its existing hostname and its `/login` entry redirects to
  `https://agri-lgu.online/lgus/mayantoc-tarlac-8/login`. New-domain LGU hostnames
  and wildcard hosting were not provisioned by this operation.
- `demo.agritarlac.online` remains a separate pinned installation.
- Browser sessions are host-only. Users sign in again when moving to the new
  domain; the same accounts and passwords apply.

The provider's [parked-domain documentation](https://www.hostinger.com/support/1583404-how-to-park-a-domain-at-hostinger/)
describes this alias arrangement. HTTPS was verified with normal certificate
validation, without bypassing warnings or disabling checks.

## Backup and preservation

Private recovery files are outside the public root at
`/home/u330835917/.private/domain-cutover-20261027`, restricted to the hosting user.
They contain the original environment, rewrite rules, configuration cache,
database backup and before/after verification receipts. Do not publish them.

The verified compressed database backup covers **27 tables / 10,039 rows**, with
**3,414,973 bytes**, SHA-256
`7908ae9a9fcd87941cbc2eefa997ddb7c8464e11c3ee8661eb31ccf6580d595b`.
All table/row/schema fingerprints and the private settings matched after the
configuration change. No migration, seed, account provisioning, dependency
installation, operational data edit or general-cache clear ran.

## Verification

- New HTTPS homepage, office sign-in, farmer sign-in and LGU directory: **200**.
- Old `/login?from=domain-check`: **301**, retaining path and query on the new host.
- `www.agri-lgu.online`: **301** to the canonical host. Plain HTTP upgrades to HTTPS.
- Old www QR-shaped URL: **301**, retaining its full path and query. No actual
  farmer QR token was exposed or changed for the test.
- Guest `/farmers`: **302** to new-host sign-in; `.env`: **403**.
- Tokenless POST to new sign-in: **419**, confirming CSRF protection remains active.
- Existing Mayantoc website: **200**; its sign-in entry: **302** to new central URL;
  tenant `/api/user`: **404**. Demo sign-in: **200**.
- A browser render confirmed the production homepage, images and new-host links.
- A temporary, data-free map check on the new origin reached **MAP_TILES_LOADED**
  with satellite imagery using the existing configured Google Maps key. No key
  value or farmer data was logged. The check file was removed and subsequently
  returned **404**. This is one browser map initialization, not a billing audit
  or certification of every authenticated mapping/export workflow.

No production credentials were entered or reset during acceptance. Existing
account records were preserved; full staff workflow/device acceptance is separate.

## Rollback

If a domain problem requires rollback, restore `APP_URL` from the private
environment backup and restore the prior public `.htaccess`, then rebuild only
the configuration cache. Verify the old central sign-in and tenant links before
bringing the application online. Do not restore the database for a domain-only
rollback; subsequent legitimate records must be retained. Keep the alias in place
until the replacement hosting plan is clear; do not remove domains or alter DNS
for the isolated demo or existing LGU hosts as part of this rollback.
