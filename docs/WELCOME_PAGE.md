# Public farmer welcome page

## Implementation scope

`GET /` now renders the welcome view for guests. Existing authenticated root redirects and `/login` are preserved. Public content is static, needs no database query, and exposes no farmer records, counts, maps, or tokens. No migration, live feed, CMS, new configuration, public application, or registration workflow is introduced.

Files: `resources/views/welcome.blade.php`, `public/css/welcome.css`, `public/js/welcome.js`, the six photographs in `public/images/welcome/`, and the narrow root-route change. Styles consume `partials.design-tokens`; the application security headers remain active without widening the CSP.

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
- Motion is limited to a 150 ms button-color transition and honors reduced-motion preferences. No carousel, auto-scroll, or animated counts.

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

Deployment: include the welcome Blade view, CSS, JavaScript, all six local JPEG assets, and the root-route change in the normal reviewed release, then rebuild Blade views. No deployment was performed.
