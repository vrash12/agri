# LGU public websites

Status: deployed and verified October 20, 2026 in runtime `fed7289`. Both named LGU migrations were applied after backup; the new tables remain empty. The public directory returned HTTP 200 and an unpublished slug returned 404. Existing data, environment/session/cache namespaces and provider counters were preserved. No profile, publication, DNS or account change was made. See [the deployment receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md). Each active municipality/city may publish one public eAgriLGU website linked to its approved LGU profile; no page, content or DNS is provisioned automatically.

## Public and office experience

The searchable `/lgus` directory lists published pages. Each page has a fixed `/lgus/{slug}` address and displays approved office name/logo, contacts, address/location, welcome content, services, announcements, hours and optional cover photograph. Farmer-ID guidance links residents to their issuing office. Farmer records, parcels, account details and dashboards remain private. Sign-in links point to the central application.

Use **Settings → LGU profiles, websites & IDs → Manage LGU website**. System Owners, scoped provincial Super Administrators and municipal heads edit and publish. Regional Heads, provincial agriculture staff and municipal staff may view/preview within scope; veterinary, GIS evaluator, visitor and farmer-portal identities have no access. Publishing requires an active municipality, active supervising province and saved office profile. Pages begin unpublished; authorized preview shows saved content before publication. Unpublishing withdraws the page and media URLs.

The first save establishes a lowercase unique slug, 3–63 characters with letters/numbers/hyphens and reserved-word protection. Later saves cannot change it, preserving community bookmarks. The editor accepts up to six services and three announcements. Plain text is escaped by Blade; service and official-profile URLs require HTTP(S) without embedded credentials. No remote image/content URL is fetched.

Cover uploads are private local PNG/JPEG/WebP files, at most 4 MB and 320 × 180 through 4,000 × 4,000 pixels. Logo validation comes from the profile workflow. Public media is served only after publication/active-office checks, validated path/MIME and restrictive CSP, with no-store headers. Private storage bytes are never exposed by arbitrary paths.

## Application boundaries

`App\Modules\LocalGovernment\Application` owns `MunicipalityWebsites`, `LguWebsiteAddress`, `LguWebsiteMedia` and profile persistence. Models/policies/Form Requests/controllers remain the existing HTTP/persistence adapters. `ModuleMap` declares LocalGovernment. Shared `MunicipalityAccess`, policies, parent locks, `ConcurrentWrite` HMAC versions and a database unique constraint protect scope and simultaneous edits/slug claims. Explicit safe **LGU websites** audit events omit cover paths and private bytes; generic model observers are not registered for these models.

A hostname selects public content only and never replaces office ownership/policies. `ResolveLguWebsiteHost` accepts GET/HEAD root/media paths on a matching tenant hostname and denies sign-in, API, office and mutation routes there. Unpublished, unknown and reserved tenants fail closed.

## Optional subdomain hosting

Leave `LGU_WEBSITE_DOMAIN` blank to publish at central paths. To serve `https://{slug}.{domain}/`, configure a bare parent hostname only after the DNS provider and web server are ready. Example placeholders:

```dotenv
APP_URL=https://app.example.test
LGU_WEBSITE_DOMAIN=example.test
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
```

Wildcard DNS must target the same Laravel public root, and HTTPS needs a certificate covering `*.example.test`. Keep `APP_URL` on the central sign-in host and session cookies host-only (`SESSION_DOMAIN` blank). Tenant pages need no office session. Confirm trusted proxy/HTTPS settings before changing HSTS. Settings and migrations do not create DNS records, certificates or hosting virtual hosts. A blank tenant domain requires none of that hosting work.

## Deployment and verification

After a verified private backup and PHP 8.3+ runtime confirmation, apply only:

```sh
php artisan migrate --path=database/migrations/2026_10_19_000100_create_municipality_profiles_table.php --force
php artisan migrate --path=database/migrations/2026_10_19_000200_create_municipality_websites_table.php --force
```

The migrations create empty additive tables without changing existing operational records/accounts or publishing pages. Release the modular services, scoped adapters, middleware/routes, views, shared branding and `lgu-website.css` together. Mirror public assets with 0644 permissions and rebuild optimized autoload/configuration/route/view caches. Preserve private environment/session/cache namespaces and provider counters; do not clear the general application cache. Ensure writable private storage and include `lgu-logos`/`lgu-covers` bytes in backups.

`MunicipalityProfileSettingsTest` contains isolated coverage for unpublished/published behavior, preview, role scope, unique/reserved slugs, tenant isolation and restricted media delivery. Integrated results and live acceptance are recorded in [the October 20 receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md). Central-path publication is ready for approved office setup; optional subdomain hosting remains unconfigured. Populate profiles/content only from approved office information, review before publishing and verify optional subdomains separately from central-path publication.

## Interface refresh — October 20, 2026

The Settings directory, office-profile form, website editor and public directory/page were redesigned without changing routes, fields, validation, permissions, scope or storage. Settings screens share `resources/views/settings/lgu/partials/styles.blade.php` and `partials/scripts.blade.php`; public pages use `resources/views/public-lgu/partials/icon.blade.php` and the rewritten, readable `public/css/lgu-website.css`. Release is view/CSS only: no migration, configuration, dependency or data change. Mirror `public/css/lgu-website.css` to both public directories (0644) and rebuild the view cache. See the matching `DESIGN_IMPLEMENTATION.md` entry for behaviour and verification.
