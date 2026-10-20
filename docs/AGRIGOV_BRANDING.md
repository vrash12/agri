# AgriLGU identity

October 20 AgriLGU rename (deployed in runtime `e804e57`): visible application branding
now reads AgriLGU. The wordmark removes the e and uses the original A mark plus
Poppins Bold green griLGU; `agrilgu-wordmark-v2.svg` is 306 x 104 and its PNG is
1,224 x 416. The shared component, titles, navigation, public LGU pages, farmer
portal, identifier labels and Assistant copy use the new name. Existing farmer
IDs, QR destinations, routes, stored namespaces, private APP_NAME, provider
headers and original compact mark remain compatible. Both old/new names still
resolve in the Assistant. Cards retain their standalone front mark. The release
also preserves the LGU Settings/public-site design from `8d5b497`. See
[the verified deployment receipt](AGRILGU_NAME_DEPLOYMENT_2026_10_20.md).

The earlier October 20, 2026 LGU/Poppins revision deployed in runtime `fed7289`. Eleven source/served public assets match; HTTP text/SVG assets match byte-for-byte, while the hosting CDN losslessly recompresses the two PNGs with identical decoded RGBA pixels and dimensions. Welcome, office/farmer sign-in, the LGU directory and scoped card renders passed. Existing operational data, environment/session/cache namespaces and provider counters were preserved. See [the deployment receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md) for test and acceptance evidence.

The application wordmark reads **AgriLGU**: the owner's supplied corn-and-field mark replacing **A**, then agriculture-green **griLGU**. The owner selected Poppins Bold (700). Use `<x-brand />` for this full lockup and `<x-brand compact />` for the supplied square mark. Accessible alternative text is AgriLGU. Shared sizing lives in `public/css/branding.css`; standalone documents include `partials.branding-head`.

The full-field farmer-card revision deployed in runtime `ddcc8d8` uses the standalone mark on the front and no system logo/wordmark on the back. Front logo white margins blend into the field through multiply compositing in CSS and canvas, without modifying stored image files; the site wordmark is unchanged. See [the card guide](AGRIGOV_FARMER_IDS.md).

The deployed identity covers welcome/about pages, office and farmer sign-in, navigation, dashboards, Assistant, farmer portal, public parcel pages, LGU public websites, browser icons and farmer IDs. The sign-in card uses the full wordmark. Collapsed navigation/browser icons use the compact mark. Body text, forms and tables retain the existing local Inter typography and shared tokens.

## Source assets and provenance

- `public/images/branding/agrilgu-wordmark-v2.svg`: 306 x 104, outlined Poppins Bold with the original embedded corn-and-field mark as A.
- `public/images/branding/agrilgu-wordmark-v2.png`: 1,224 x 416 transparent raster, used by the shared site brand component.
- `public/images/branding/eagrilgu-system-mark.png`: existing compact icon and standalone front-card mark. Filename retained for compatibility.
- `public/images/branding/licenses/Poppins-OFL.txt`: retained font license.

The new wordmark removes only the e path and trims the canvas to the remaining
mark and lettering. The original embedded logo and remaining letter paths are
unchanged. Lettering uses agriculture green `#12633F`. Older versioned assets and
deployment records remain historical evidence. Cards use the standalone front
mark and no wordmark on the back; LGU logos remain distinct from application
branding.

## Rename verification (local)

The new SVG preserves every remaining letter path and the embedded A mark;
only the e path and excess canvas were removed. The PNG is 1,224 x 416. Visible
runtime copy was checked across app services, views and public scripts. Prior
brand strings remain only in compatibility settings, asset names, storage keys
and historical documents. PHP syntax, Pint, Blade compilation, diff checks and
71 focused JavaScript tests passed. A separate focused PHP branding run passed
25 tests / 221 assertions.

The broader PHP run completed 914 tests / 6,658 assertions: 903 passed, ten
FarmerPicker fixture errors require an existing provinces table, and one farmer
identifier workflow failed with a missing audit_logs fixture. Both failure types
were reproduced using the pre-rename test files in the same disposable SQLite
environment. The identifier test's outdated card caption now expects Registry
ID. This is not claimed as a fully passing PHP run. No live provider evaluation
was performed during that local test run. The subsequent authorized production
deployment and combined 47-test verification are recorded in the receipt above.

## Compatibility and deployment

Keep `AGRI-F-######`, stored portal IDs, `agri_gov_id`/`agriGovId`, `X-AgriGOV-Crop-Modal`, existing routes and legacy `agrilgu-*` CSS hooks unchanged. The private production `APP_NAME`, effective session/cache/Redis namespaces and provider counters are preserved. Do not clear the general application cache for branding.

Deploy the component, head partial, changed views/scripts, photo credits, branding/card stylesheets, source/raster wordmark, compact mark, license and shared field SVGs together. Mirror public assets to Laravel `public` and served `public_html`, with 0644 file permissions. Regenerate optimized autoloading and configuration/route/view caches through the existing locked-dependency release process on PHP 8.3+. Branding alone needs no migration or environment change; the combined LGU feature requires the two explicitly named additive migrations after private backup.

Verify served asset hashes, welcome and both sign-ins, scoped office navigation, public LGU directory/pages, card front/back and artwork failures. Card checking includes long names, issuer/parcel text, signatures and unchanged QR destination. Physical printing and provider-backed browser behavior must be reported separately from automated checks. Results and deployment evidence are recorded in [the October 20 receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md). Physical printing and interactive live PNG export were not repeated.

## Historical releases

The October 15 eAgriLGU rebrand introduced the earlier gold e and corn wordmark; the October 10 AgriLGU and September 18 AgriGOV releases used earlier assets. Their dated records in [AGRILGU_BRANDING.md](AGRILGU_BRANDING.md), design history and deployment history remain evidence for those releases. The October 20 wordmark and card composition supersede those visual descriptions without changing registry identity or session/cache namespaces.
