# eAgriLGU identity

The October 20, 2026 revision is **deployed and verified** in runtime `fed7289`. Eleven source/served public assets match; HTTP text/SVG assets match byte-for-byte, while the hosting CDN losslessly recompresses the two PNGs with identical decoded RGBA pixels and dimensions. Welcome, office/farmer sign-in, the LGU directory and scoped card renders passed. Existing operational data, environment/session/cache namespaces and provider counters were preserved. See [the deployment receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md) for test and acceptance evidence.

The application wordmark reads **eAgriLGU**: a blue/red lowercase **e**, the owner's supplied corn-and-field mark replacing **A**, then agriculture-green **griLGU**. The owner selected Poppins Bold (700). Use `<x-brand />` for this full lockup and `<x-brand compact />` for the supplied square mark. Accessible alternative text is eAgriLGU. Shared sizing lives in `public/css/branding.css`; standalone documents include `partials.branding-head`.

The same identity covers welcome/about pages, office and farmer sign-in, navigation, dashboards, Assistant, farmer portal, public parcel pages, LGU public websites, browser icons and farmer IDs. The sign-in card uses the full wordmark. Collapsed navigation/browser icons use the compact mark. Body text, forms and tables retain the existing local Inter typography and shared tokens.

## Source assets and provenance

- `public/images/branding/eagrilgu-wordmark-v3.svg`: 365 × 104 source artwork with Poppins Bold lettering converted to paths and the owner's mark embedded unchanged as A.
- `public/images/branding/eagrilgu-wordmark-v3.png`: 4× rasterization, 1,460 × 416, shared by site and farmer-card screen/print/canvas output. Versioned filenames refresh earlier cached logos, and the raster avoids canvas implementations omitting an SVG's embedded image.
- `public/images/branding/eagrilgu-system-mark.png`: the owner's supplied 236 × 236 corn-and-field mark; source resolution is limited by the supplied screenshot.
- `public/images/branding/licenses/Poppins-OFL.txt`: retained SIL Open Font License.

Poppins Bold comes from [`Poppins-Bold.ttf` in Google Fonts](https://github.com/google/fonts/blob/main/ofl/poppins/Poppins-Bold.ttf), SHA-256 `983676516167748b74de6f4771fb384c664fd913acb8b471122ecacf5da5ea6c`. Outlined lettering needs no installed client font or runtime font request. The e uses blue `#1D5EB8` and red `#D63942`; griLGU uses green `#12633F`. The supplied eGovPH reference informed compactness only; its letter shapes and PH badge are not copied. Small card logos omit tiny tagline lettering; office details occupy their own readable columns.

Older wordmarks remain historical assets where already committed. Active views consume v3 and the supplied compact mark. Agency seals remain separate: configured LGU logos take precedence on IDs; active Ramos, Tarlac retains its municipal seal as an unconfigured fallback, and other unconfigured offices use the DA logo. Cards no longer print the Republic emblem/heading. See [farmer ID rendering](AGRIGOV_FARMER_IDS.md).

## Compatibility and deployment

Keep `AGRI-F-######`, stored portal IDs, `agri_gov_id`/`agriGovId`, `X-AgriGOV-Crop-Modal`, existing routes and legacy `agrilgu-*` CSS hooks unchanged. The private production `APP_NAME`, effective session/cache/Redis namespaces and provider counters are preserved. Do not clear the general application cache for branding.

Deploy the component, head partial, changed views/scripts, photo credits, branding/card stylesheets, source/raster wordmark, compact mark, license and shared field SVGs together. Mirror public assets to Laravel `public` and served `public_html`, with 0644 file permissions. Regenerate optimized autoloading and configuration/route/view caches through the existing locked-dependency release process on PHP 8.3+. Branding alone needs no migration or environment change; the combined LGU feature requires the two explicitly named additive migrations after private backup.

Verify served asset hashes, welcome and both sign-ins, scoped office navigation, public LGU directory/pages, card front/back and artwork failures. Card checking includes long names, issuer/parcel text, signatures and unchanged QR destination. Physical printing and provider-backed browser behavior must be reported separately from automated checks. Results and deployment evidence are recorded in [the October 20 receipt](EAGRILGU_LGU_DEPLOYMENT_2026_10_20.md). Physical printing and interactive live PNG export were not repeated.

## Historical releases

The October 15 eAgriLGU rebrand introduced the earlier gold e and corn wordmark; the October 10 AgriLGU and September 18 AgriGOV releases used earlier assets. Their dated records in [AGRILGU_BRANDING.md](AGRILGU_BRANDING.md), design history and deployment history remain evidence for those releases. The October 20 wordmark and card composition supersede those visual descriptions without changing registry identity or session/cache namespaces.
