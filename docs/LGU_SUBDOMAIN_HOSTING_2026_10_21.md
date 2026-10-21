# LGU subdomain hosting — October 21, 2026

The owner authorized Hostinger provisioning for the existing `agritarlac.online` domain. Browser control became available and the signed-in hPanel session was used to create these hostnames:

| Hostname | Purpose | Public result |
| --- | --- | --- |
| `ramos-mao.agritarlac.online` | Requested Ramos LGU hostname | HTTPS ready; HTTP 404 until an authorized manager saves and publishes its office profile/website |
| `mayantoc-tarlac-8.agritarlac.online` | Preserve the existing published Mayantoc page when enabling the shared tenant domain | HTTP 200 with the existing content |

Both use `/home/u330835917/domains/agritarlac.online/public_html/lgu-public`, a restricted entry point into the existing Laravel application. The existing isolated demo document root was preserved. No separate application, database, account or farmer data was created. Hostinger added ALIAS records to its CDN when the subdomains were created; no root-domain, nameserver or demo DNS record was changed. Lifetime SSL automatically became Active for both hosts. HTTP requests redirect to HTTPS.

## Entry point and configuration

`scripts/hosting/lgu-public/index.php` is the reproducible source for the hosted entry point. Copy it only to `public_html/lgu-public/index.php` (0644), copy the existing Laravel public `.htaccess` into that directory, and create `css`, `js`, `images` and `fonts` symlinks to the matching parent public directories. Never copy private application files, `.env`, storage or vendor into a public directory. The entry point returns 503 while the selected tenant domain is disabled; after activation the existing host middleware permits public GET/HEAD root/media routes only. It does not bypass publication or scope checks.

The production environment now has `LGU_WEBSITE_DOMAIN=agritarlac.online`; `APP_URL`, the application key, database credentials and session/cache/provider settings were preserved. Session cookies remain host-only. Only the configuration cache was rebuilt. No general-cache clear, migration, dependency install, account operation or operational data change ran.

Private recovery files are outside the document root at `/home/u330835917/.private/lgu-hosting-20261021`: the prior environment, entry point and before/after preservation receipts. Fingerprints matched for all **27 tables / 10,017 rows**; removing the one tenant-domain line from the environment produces the same remaining bytes. Do not publish or commit those private files.

## Verification and remaining setup

- Certificates validated over HTTPS at the origin and the public CDN without disabling certificate checks.
- The Mayantoc public page, stylesheet, logo and cover returned HTTP 200. A browser render confirmed that its sign-in links point to the central host.
- LGU `/login` and `/api/user` returned HTTP 404. Central homepage, directory and isolated demo returned HTTP 200.
- Ramos returned HTTP 404, as required for an unsaved/unpublished website. No Ramos office content was fabricated or published.

To activate Ramos content, an authorized manager must save its approved office profile and website using slug `ramos-mao`, then publish. For every future LGU, first create the matching Hostinger hostname using the same `lgu-public` folder and verify DNS/HTTPS before publishing its website. This setup does not create wildcard hosting or automate hPanel through credentials.
