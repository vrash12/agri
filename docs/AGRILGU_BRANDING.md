# AgriLGU identity

Status: local implementation on October 10, 2026; not pushed or deployed. Earlier release documents describe the former AgriGOV identity.

AgriLGU is the application's name: an LGU Agriculture Information and GIS Management System. Its original emblem combines a golden corn ear, forest-green husk leaves and an agricultural field swoosh into the letter A. It follows the requested green-and-gold agriculture style. The Department of Agriculture seal and Philippine coat of arms retain their separate meaning and attribution.

## Shared assets

- `public/images/branding/agrilgu-wordmark-v1.png`: transparent integrated wordmark, 2025 × 777 pixels.
- `public/images/branding/agrilgu-mark-v1.png`: matching transparent compact corn emblem, 1254 × 1254 pixels.
- `resources/views/components/brand.blade.php`: `<x-brand />` and `<x-brand compact />`, with AgriLGU alternative text and intrinsic dimensions.
- `public/css/branding.css` and `partials.branding-head`: shared sizing, browser icons and stylesheet versioning.

The PNGs were generated with the built-in ImageGen tool on October 10, 2026. The original brief retained the established lettering, then added the owner's requested corn-and-leaf A. The compact asset uses the matching emblem. Generated originals remain outside the repository; the selected PNGs are copied without bitmap post-processing. Earlier AgriGOV PNGs remain for compatibility but current interfaces use the new assets.

## Interface and card coverage

Welcome/about pages, office and farmer sign-in, dashboard/navigation, visitor overview, farmer portal, registry search/pickers, public parcel pages, photo credits and browser titles/icons use AgriLGU. Shared asset consumers use the `agrilgu-*` CSS classes. Dynamic farmer-picker labels use the same name, with file-versioned JavaScript.

Farmer cards use the new corn mark on the front and the wordmark on the back; front microtext, ID labels and application attribution read AgriLGU. The screen/print layout and canvas digital/PNG exports load the same assets. Existing signatures, separate agency emblems, QR destination/white panel, parcel addresses and oversized-address failure behavior remain in place.

This is a branding change. Actual `AGRI-F-######` farmer numbers, stored portal login IDs, `agri_gov_id`/`agriGovId` compatibility fields and the internal `X-AgriGOV-Crop-Modal` header remain unchanged. Existing sign-ins, searches, imports, bookmarks and QR cards keep working. No migration or account provisioning is required.

## Deployment requirements

After explicit owner authorization, commit/push through GitHub and use Hostinger `git pull --ff-only`. Deploy the changed component, views, JavaScript, CSS, SVG microtext and both PNGs together. Mirror changed public assets to both Laravel's `public` directory and the served `public_html` root, with readable 0644 permissions. Rebuild Blade views with `php artisan view:cache`.

`config/app.php` defaults to AgriLGU and `.env.example` documents `APP_NAME=AgriLGU`. An existing private environment can still override it. Before changing the live `APP_NAME`, preserve the currently resolved `SESSION_COOKIE`, `CACHE_PREFIX` and `REDIS_PREFIX` as explicit private settings when they were derived from the old name. This avoids changing sessions or the cache namespace used for locks and NDVI request budgets. Then refresh configuration with `php artisan config:cache`. Do not clear the general application cache for a branding release. Never copy the live environment or resolved settings into documentation or command output.

Verify public welcome/sign-in and logo responses, office navigation/dashboard and the private farmer portal. Inspect card front/back in screen, print preview and digital/PNG modes; check signatures, wrapped parcel addresses and QR destination. Credentials and operational farmer data must stay outside screenshots and repository artifacts.

## Local verification

- 86 focused PHP tests passed across public welcome, shared navigation/sign-in, dashboard presentation, farmer portal, identifiers/search scope, card signatures, registry presentation and provider usage. A newly added profile assertion was corrected to match the existing "AgriLGU farmer ID" wording, then all 34 portal tests passed.
- 26 JavaScript checks passed for address wrapping/overflow protection, farmer finder/picker labels, numeric selection values and map-label behavior.
- Laravel Pint, changed PHP syntax checks, Blade compilation, portal route verification and `git diff --check` passed.
- Six actual Blade pages were rendered against disposable SQLite/sample fixtures: welcome, office/farmer sign-in, dashboard, farmer profile and registry card. Browser checks confirmed loaded corn assets and current names, including 376-pixel mobile layouts with no horizontal overflow on welcome, both sign-ins, profile and the card/digital dialog.
- The card's canvas-rendered front and back loaded at 1011 × 638. The back PNG was visually inspected for the corn wordmark, clear QR panel, cardholder/office signature lines and disclaimer. The HTML card retained the same signatures and artwork sources. Physical printing and live deployment were not performed.

The developer machine runs PHP 8.4 and emits pre-existing test-runner deprecation notices; the supported production PHP range remains 8.1–8.3.
