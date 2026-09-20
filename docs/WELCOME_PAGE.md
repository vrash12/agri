# Public farmer welcome page

## Implementation scope

`GET /` now renders the welcome view for guests. Existing authenticated root redirects and `/login` are preserved. Public content is static, needs no database query, and exposes no farmer records, counts, maps, or tokens. No migration, live feed, CMS, new configuration, public application, or registration workflow is introduced.

Files: `resources/views/welcome.blade.php`, `resources/views/partials/welcome-slideshow.blade.php`, `public/css/welcome.css`, `public/js/welcome.js`, `public/js/welcome-slideshow.js`, existing images in `public/images/welcome/` and `public/images/login/`, and the inherited DA seal `public/images/da.jpg`. Styles consume `partials.design-tokens`; application security headers remain active without widening the CSP. Root routing is unchanged by the slideshow refresh.

## Design direction

Reading this as a public agriculture-service guide for farmers and fisherfolk, in the existing green/yellow identity: ENERGY 2 / RHYTHM 2 / MOTION 1.

References reviewed September 18, 2026:

- [eGovPH](https://e.gov.ph/): prominent introduction, clear navigation, and distinct user entry point. No eGov branding, download badges, statistics, or integration claims were copied.
- [Anti-slop](https://github.com/miqdadbadjuber/anti-slop), core `antislop.md`: applied during implementation. Repository instructions were used as design guidance; no installer or global agent setting was changed.

Major choices and their purposes:

- Shared forest green and white keep the page recognizable alongside the office system; limited yellow connects the field photograph and key staff entry.
- Roboto retains the existing application typography. Larger public-page headings introduce services before the denser office forms.
- A Filipino headline welcomes the intended audience; English service labels stay consistent with the system. Filipino passages carry `lang="fil"`.
- An actual rice-field photograph gives agriculture a concrete presence. It is a credited scene from Negros Occidental, not a claim about registered land or coverage.
- Six native disclosures group service questions in a directory. Small photographs help distinguish farming, crop inputs, fisheries, livestock, mapping, and machinery; adjacent text carries each service name. Decorative thumbnails use empty alt text to keep the accessible disclosure names concise.
- One RSBSA feature panel is emphasized because registration is foundational; other agency links use compact editorial rows.
- External-link arrows identify outgoing resources; the hero arrow identifies an in-page destination. No decorative icon library is required.
- Flat surfaces and varied section spacing separate service discovery, agency resources, visit preparation, and staff entry.
- The fixed light theme follows the application direction and keeps reading surfaces clear; there is no theme switch.
- Twenty Philippine agriculture photographs form five distinct four-photo collages, cycling every eight seconds with a brief fade and captions. Selection and pause controls have 44-pixel targets. Playback suspends on hover and hidden tabs; keyboard entry and manual selection pause until resumed. Reduced motion disables autoplay and fade. Only the first collage and upcoming collage load initially during playback; the first four photos remain visible without JavaScript.
- A forest-green About AgriGOV section groups six actual office capabilities in an expandable toolkit. Text explains the use of each tool without fictional statistics, testimonial claims, or sample private records.
- The DA seal appears beside the DA resource link, separately from the AgriGOV wordmark. It is the existing unmodified asset, inherited from the initial repository commit; its original download source is not documented. It does not represent a new endorsement or integration claim.

## Public information and sources

Service descriptions follow the actual module catalog. They direct inquiries to the responsible agriculture or veterinary office and do not promise assistance, availability, eligibility, land ownership, or online processing.

- [RSBSA Finder](https://finder-rsbsa.da.gov.ph/ph)
- [PhilRice RCEF Seeds and Extension](https://rcef-seed.philrice.gov.ph/rcef_site/home)
- [ATI e-Learning](https://elearn.e-extension.gov.ph/)
- [BFAR](https://www.bfar.da.gov.ph/)
- [Department of Agriculture](https://www.da.gov.ph/)
- [PAGASA Agri-Weather](https://pagasa.dost.gov.ph/agri-weather)

These are links to third-party official services, not integrations or a news feed. Review destinations and descriptions when programs change. BFAR's main site was intermittently unavailable during source verification and returned a certificate validation error during browser testing; no live status is represented on this page.

## Photograph license

“Rice fields under the clear blue sky,” by Mark Daniel Lecciones, April 7, 2019, Murcia, Negros Occidental.

- [Source and authorship](https://commons.wikimedia.org/wiki/File:Rice_fields_under_the_clear_blue_sky.jpg)
- [CC BY-SA 4.0 license](https://creativecommons.org/licenses/by-sa/4.0/)
- [Downloaded 1280 × 960 derivative](https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c0/Rice_fields_under_the_clear_blue_sky.jpg/1280px-Rice_fields_under_the_clear_blue_sky.jpg)
- Local file: `public/images/welcome/rice-fields.jpg`, 494,589 bytes.

The source photograph was resized by Wikimedia and is cropped visually with CSS `object-fit: cover`. The photo and its adaptations remain CC BY-SA 4.0. Source, photographer, license, and display-crop notice appear in the page footer. The license applies to this photograph, not a blanket relicensing of the application.

Five additional service photos use 330-pixel Commons derivatives totaling 165,277 bytes (about 161 KiB), with explicit dimensions and lazy loading. They depict rice planting, harvested rice panicles, a fishing boat, a carabao, and a tractor. Complete sources and individual licenses are recorded in [WELCOME_PHOTO_SOURCES.md](WELCOME_PHOTO_SOURCES.md) and the public photography disclosure. Subjects are illustrative; they are not identified as registered farmers or assistance recipients.

## Verification

Focused automated tests: 8 tests, 42 assertions covering the public view without database queries, browser security headers, local assets, municipal/System Owner/veterinary root redirects, and protected office routes. PHP formatting, PHP/JavaScript syntax, Blade compilation, and whitespace checks pass.

Browser checks passed at 320, 390, 768, 901, 1024, and 1440 CSS pixels without horizontal overflow. Desktop and phone screenshots were reviewed. All six service disclosures opened and closed with Enter; their guidance links reached the intended sections. The skip link focused main content. Mobile navigation opened, closed with Escape, restored focus, and closed after section selection. Both office links reached the existing login page. The photography disclosure showed all six credits. Service images loaded, and the five new thumbnails total about 161 KiB.

RSBSA Finder, PhilRice, ATI, DA, and PAGASA links were clicked and their official pages loaded. BFAR's external certificate error remains a third-party limitation. The local page had no console warnings or errors during its initial interaction check. Key text/color combinations were calculated at 4.78:1 or higher. No-JavaScript resilience and reduced-motion handling were reviewed in source; they were not tested through browser preference overrides. This is not a full accessibility audit or a measured field-usability study.

### Delivery review

- **Design intent — PASS:** documented low-motion green/yellow direction, real Philippine agriculture photography, and service-led content.
- **Content and assets — PASS:** working local service guidance, official resource destinations, no invented statistics or endorsements, linked photographer/source/license credits.
- **Interaction and accessibility — PASS within the checks above:** native disclosures, menu and keyboard checks, visible focus, readable contrast, and six responsive widths. BFAR availability is outside this application.
- **Engineering and scope — PASS:** focused tests and compilation pass, public rendering performs no operational queries, existing sign-in redirects are retained, and no extra package, migration, or production change is required.

### Slideshow and feature-guide refresh — September 19, 2026

The refresh reuses five full-size licensed photos already supplied with the login:
rice planting, vegetable harvest, a fishing boat, farm machinery, and rice fields.
The footer links `photo-credits.html` for their original sources, authors, and
licenses; see `LOGIN_SLIDESHOW.md`. Existing service-thumbnail credits remain.
The new gallery is independent of login behavior. CSS/JavaScript URLs include a
file modification version so updated assets replace browser-cached copies.

Refresh verification: 6 focused PHP tests and 13 JavaScript lifecycle tests pass,
along with Pint, PHP/JavaScript syntax, Blade compilation, route verification,
and scoped whitespace checks. Local browser review covered all five photographs
and captions, initial next-photo loading, first-click pause, manual selection,
automatic resume, mobile navigation and section focus, and DA seal/link rendering.
No horizontal overflow was found at 320, 390, 768, or 1440 pixels; desktop and
phone screenshots were inspected. No browser warnings/errors were captured.
Hidden-tab, hover, reduced-motion changes, failure fallback, and pointer/focus
ordering are covered by lifecycle tests; OS reduced-motion and image-network
failure were not forced in the browser. Existing public rendering still performs
no operational database queries. No live sign-in or production change was made.

Deployment: include the welcome view, new slideshow partial, CSS, existing menu
script, and new slideshow script. Verify the referenced existing photos, DA seal,
and photo-credits page in both Hostinger public directories, then rebuild Blade
views. No migration, new dependency, configuration change, or production data
operation is required. This refresh is local and has not been deployed.

## Neobrutalist photo collage — September 20, 2026

The homepage uses a warm paper background, strong square borders, solid offset
shadows, and the existing green/yellow identity. Three photo frames make a collage:
the main five-photo slideshow and two small licensed crops/fishing prints. Only
photo frames and the decorative label are tilted; controls remain level. Images
reuse existing assets and retain public attribution. No new image downloads,
font families, packages, database queries, routes, or migrations are required.

The hero provides clear Find farmer services and Farmer sign in actions. Services
now precede the office-system overview. Service disclosures, official DA links and
seal, the visit checklist and staff sign-in remain available. Page-specific
geometry intentionally follows the requested neobrutalist style; authenticated
modules and shared design tokens are unchanged.

Verification: six PHP tests / 45 assertions, thirteen slideshow lifecycle tests,
Blade compilation, Pint and scoped whitespace checks passed. Browser review
covered desktop and phone screenshots, loaded collage images, no horizontal
overflow at 320/390/760/761/950/951/1024/1440 pixels, mobile Menu/Escape focus,
all six native disclosures using Enter, gallery selection and farmer entry.
Reduced-motion, hidden-tab and image-failure handling remain covered by existing
JavaScript tests. This is not a full accessibility audit or a user study.

Status: implemented locally; this redesign has not been pushed or deployed.
Deploy the welcome view, slideshow partial and versioned CSS together with the
previously local welcome-slideshow script when publishing. Mirror public assets
to both Hostinger public directories and rebuild views; preserve the live farmer
portal sign-in route and existing photo sources.

### Hero and microinteraction refinement — September 20, 2026

The headline has three deliberate lines and a static yellow marker beneath
“sa magsasaka.” The introduction names the visitor's main tasks, two level actions
share the available width, and first-time account guidance sits below a divider.
Phone layouts stack the actions with comfortable tap targets.

Subtle feedback includes button lift/press states, directional link arrows,
navigation underlines, photo-frame settling on fine-pointer hover, service-panel
color and plus/minus changes, and a short disclosure opening animation. Five
section headings settle once as they enter view, staying visible throughout.
Section shortcuts move keyboard focus while preserving native fragment history;
the navigation marks the visible section with `aria-current="location"`.

Motion respects `prefers-reduced-motion` in both CSS and JavaScript. Live preference
changes cancel heading motion; missing observer/animation APIs leave content
visible and links usable. There are no new libraries or recurring decorative
animations. The existing slideshow keeps its own accessible playback controls.

Verification: six PHP tests / 45 assertions, twenty JavaScript tests, Pint,
JavaScript syntax, Blade compilation and scoped whitespace checks pass. Browser
review confirmed the hero at phone and desktop widths, no horizontal overflow at
320/390/761/900/951/1440 pixels, section focus and active navigation, and keyboard
opening/closing of a service disclosure. Reduced-motion changes and API fallbacks
are automated-test/source checks; OS settings were not overridden. This is not a
full accessibility audit. Changes remain local and are not deployed.

Include the updated `public/js/welcome.js` as well as the welcome view and CSS when
deploying this refinement; mirror public assets and rebuild views as above.

### Inside AgriGOV toolkit — September 20, 2026

The office introduction now uses a larger three-line heading, yellow emphasis,
shorter supporting copy, and a prominent office sign-in action. A single bordered
white panel with an offset yellow shadow groups the six office capabilities.
Numbered native disclosures show a benefit first and the existing feature details
when expanded. Plus/minus, background and short opening transitions give feedback;
the details work without JavaScript and all motion respects the existing preference
rules. Feature text remains static public guidance, with no additional data query.

Green inset focus outlines remain readable on the light toolkit; the office entry
retains inverse focus on the surrounding dark surface. The panel stays level,
reflows below the introduction on tablets, and uses one feature column on phones.
No new scripts, dependencies, routes, migrations or configuration are required.

Verification: the six existing welcome-page PHP tests / 45 assertions pass, as do
Pint, Blade compilation and PHP syntax checks. All six toolkit disclosures were
opened and closed with Enter, with focus retained and the details visible. Browser
layout checks found no horizontal overflow at 320/390/480/481/760/761/1050/1051/1440
pixels. Reduced-motion behavior is enforced by the existing CSS media query;
OS preferences were not overridden. This refinement is local and not deployed.
Deployment adds only the updated welcome view and CSS to the previous welcome-page
release scope; mirror public assets and rebuild views.

### Twenty-photo collage expansion — September 20, 2026

The hero now contains 20 unique photos across five different four-photo layouts.
Rice farming, highland vegetables, fisheries, fruit crops, and mixed farm life each
use a different arrangement of landscape and portrait frames. Previous static
side photographs are replaced by unique photos within each changing collage.
The gallery height stays steady; photo captions and controls stay readable.

The complete image set totals 1,947,849 bytes. Five main photos use 960-pixel
derivatives; fifteen supporting photos use 330-pixel derivatives. JavaScript loads
the current and next collage only while playback is active, or the selected
collage during manual browsing. Reduced motion keeps the first collage still.
Each photo has its own failure fallback. See `WELCOME_COLLAGE_PHOTOS.md` for all
sources, authors, licenses, dimensions and download URLs. Public credits retain
the existing login-photo attribution and add the twelve new subjects.

Verification: all 20 JPEGs have unique hashes, valid dimensions and loaded in the
browser. All five arrangements were checked at 320/390/760/761/950/951/1440 pixels
without horizontal overflow, overflowing captions or collapsed photo areas.
Desktop and narrow-phone screenshots were reviewed. Six welcome-page PHP tests
(45 assertions), sixteen slideshow lifecycle tests, Pint and Blade compilation
passed. Reduced-motion changes, hidden tabs and failed-image behavior are covered
by lifecycle tests; browser OS preferences and network failures were not forced.

Deployment includes the complete `public/images/welcome/collage/` directory,
`public/photo-credits.html`, welcome CSS/JavaScript and the slideshow partial.
Mirror assets to both Hostinger public directories before rebuilding views.
No migration, dependency or configuration change. This update remains local.
