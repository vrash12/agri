# LGU profiles and farmer ID issuers

Status: deployed and verified October 20, 2026 in runtime `fed7289` on the current Laravel 12/PHP 8.3+ modular application. The profile migration was applied after verified private backup; its new table remains empty. Three scoped settings renders, existing-card rendering and guest denial checks passed. Existing operational records/accounts and configuration were preserved. No profile, account or logo is created automatically. See [the deployment receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md).

## Office workflow

Open **Settings → LGU profiles, websites & IDs**, search within the current office scope and open an active municipality/city. Unconfigured offices appear automatically. Enter the approved office name, public HTTP(S) website, email, phone, address and description. Optional latitude/longitude must be supplied together and describe the office; they do not change geofences or parcels.

Upload an official PNG/JPEG/WebP logo, at most 2 MB and 64–2,400 pixels per side. A transparent PNG is suitable. SVG and remote logo URLs are rejected; remove and replace cannot be combined. Enter paired printed signatory name/title or clear both to omit the office line. No signature image is collected; cards are signed in ink. Maintain the current office head here.

Saving updates subsequent farmer-card screen, print, digital and PNG rendering for that municipality. Existing printed/downloaded cards retain their original details. Full office information also appears on the print sheet. The profile links to **Manage LGU website**, where approved office content can be reviewed and explicitly published separately. Publishing exposes the approved profile, contacts, location and logo; ensure these are public office details.

## Permissions and safeguards

| Account | View | Update |
| --- | --- | --- |
| System Owner | All active LGUs | All active LGUs |
| Provincial Super Administrator | Assigned province | Assigned province |
| Municipal head | Assigned municipality | Assigned municipality |
| Regional Head | Assigned region | No |
| Provincial agriculture staff | Assigned province | No |
| Municipal staff | Assigned municipality | No |
| Veterinary, GIS evaluator, visitor, farmer portal | No | No |

`MunicipalityProfile` has a unique municipality foreign key. Route binding, `MunicipalityProfilePolicy` and shared `MunicipalityAccess` authorize scope before profile/file reads. Caller-supplied ownership and stored paths are ignored. `App\Modules\LocalGovernment\Application\MunicipalityProfiles` locks the parent municipality, applies HMAC state versions through `ConcurrentWrite` and rejects stale first creation/updates. File staging occurs before database locks, and failed/replaced uploads are cleaned up. Explicit **LGU settings** audits include safe public fields and ownership snapshots, excluding private paths and bytes; the generic model observer is not registered for these models.

Private logos live under `storage/app/lgu-logos/{municipality_id}`. Same-origin authorized delivery validates the stored prefix/name and inspected MIME, with `nosniff`, no-store and restrictive CSP. No public storage link or server-side URL fetch is required. Website/location links use only validated public data.

`App\Modules\Farmers\Application\FarmerCardIssuer` supplies consistent office details to HTML/canvas. Configured logos take precedence; active Ramos, Tarlac keeps its municipal seal when unconfigured, and other offices use the DA logo. The Republic emblem/heading is removed. `FarmerCardSignatory` prefers saved paired signatory fields; a blank saved pair omits the office signature, while the config fallback applies only without a profile. QR tokens, identity data, parcel-address rules and permissions are unchanged. Missing artwork or oversized canvas text refuses incomplete export. Without the table, cards retain fallback behavior and Settings explains the pending database update.

## Deployment and verification

After a verified private backup, apply only:

```sh
php artisan migrate --path=database/migrations/2026_10_19_000100_create_municipality_profiles_table.php --force
```

This creates an empty additive table and does not modify existing office, farmer, assistance or account rows. Do not run unrelated pending migrations against the legacy baseline. Release models/policies/requests/controllers, LocalGovernment services, modular card services, route/middleware wiring, views and public assets together. Ensure private storage is writable, mirror public assets to both Hostinger directories with 0644 permissions and rebuild optimized autoload/configuration/route/view caches without clearing the general cache. Public website support additionally requires its named migration; see [LGU_PUBLIC_WEBSITES.md](LGU_PUBLIC_WEBSITES.md).

Focused fixtures cover permitted/scoped roles, denied foreign offices, validation, uploads, stale edits, cleanup, safe logo delivery, same-named municipalities, signatory fallback and card/QR consistency. Integrated test results, targeted migrations and live preservation/acceptance checks are recorded in [the October 20 receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md). No live office profile or logo was populated during deployment.

Back up the profile table and private `lgu-logos` files together. Rollback first withdraws dependent routes/code; dropping the table discards configured profiles. A database-only backup does not contain logo bytes.
