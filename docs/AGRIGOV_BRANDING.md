# eAgriLGU identity

The current application identity is **eAgriLGU**, a local-government agriculture platform with a corn-and-leaf emblem. This document records the visible branding surfaces, compatibility rules and deployment requirements for the October 15, 2026 rebrand.

The visible name is eAgriLGU across the welcome page, office login, shared navigation, dashboards, farmer portal, Assistant, public parcel pages and digital farmer IDs. Internal compatibility identifiers such as `agri_gov_id`, `agriGovId`, `AGRI-F-######`, legacy CSS hooks and request headers remain unchanged so existing records, integrations and links continue to work. The private production `APP_NAME` value is also preserved to avoid changing session/cache namespaces; visible templates use the new brand assets directly.

The application wordmark reads **eAgriLGU**, with a gold lowercase “e” followed by the green AgriLGU name and corn-and-leaf emblem. Use `x-brand` for the wordmark and `x-brand compact` for the square mark. Accessible alternative text is eAgriLGU. Shared sizing lives in `public/css/branding.css`; each standalone document includes `partials.branding-head` for the stylesheet and browser icons.

The identity appears on the welcome page, office login, shared desktop/mobile navigation, collapsed navigation, public QR parcel page, and browser titles/icons. Farmer card attribution names eAgriLGU in both HTML and PNG exports. Actual agency seals and registry identifiers remain unchanged.

The login card uses only the compact A emblem at 96 pixels wide; other page headers retain the full wordmark.

## Assets and provenance

- `public/images/branding/eagrilgu-wordmark-v1.svg`: responsive transparent wordmark.
- `public/images/branding/eagrilgu-mark-v1.png`: matching transparent square emblem.
- `output/branding/agrigov-logo-v1.png`: original concept, retained for reference.

Created with the built-in ImageGen tool on September 18, 2026. Edit brief: replace the ordinary A in AgriGOV with the original leaf-and-field A; keep forest green, harvest gold, clean typography, and transparent background. The compact asset extracts the same emblem without lettering for navigation and browser icons. Generated PNG originals are preserved without bitmap post-processing.

Deploy the views, component, head partial, stylesheet, SVG and PNG together. On Hostinger, mirror public assets to both Laravel's `public` directory and the `public_html` document root. Recompile Blade views and rebuild the config/route/view caches after deployment. No migration, account, provider, credential or private environment change is required.

## Verification

Local verification: the branding-focused PHP runner reported 194 passed plus 266 warning-marked tests (3,609 assertions, no failures) and the focused JavaScript group passed 63 tests; Blade compilation and diff checks passed. The release keeps the existing database, accounts, provider quotas, session/cache namespaces and private environment values unchanged. Live login and both new branding assets are checked after deployment.
