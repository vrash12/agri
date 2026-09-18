# AgriGOV identity

The application wordmark reads **AgriGOV** with the leaf-and-field emblem replacing its first letter A. Use `x-brand` for the wordmark and `x-brand compact` for the square mark. Accessible alternative text is AgriGOV. Shared sizing lives in `public/css/branding.css`; each standalone document includes `partials.branding-head` for the stylesheet and browser icons.

The identity appears on the welcome page, office login, shared desktop/mobile navigation, collapsed navigation, public QR parcel page, and browser titles/icons. Farmer card attribution names AgriGOV in both HTML and PNG exports. Actual agency seals and registry identifiers remain unchanged.

The login card uses only the compact A emblem at 96 pixels wide; other page headers retain the full wordmark.

## Assets and provenance

- `public/images/branding/agrigov-wordmark-v2.png`: transparent wordmark.
- `public/images/branding/agrigov-mark-v1.png`: matching transparent square emblem.
- `output/branding/agrigov-logo-v1.png`: original concept, retained for reference.

Created with the built-in ImageGen tool on September 18, 2026. Edit brief: replace the ordinary A in AgriGOV with the original leaf-and-field A; keep forest green, harvest gold, clean typography, and transparent background. The compact asset extracts the same emblem without lettering for navigation and browser icons. Generated PNG originals are preserved without bitmap post-processing.

Deploy the views, component, head partial, stylesheet, and both PNGs together. On Hostinger, mirror public assets to both Laravel's `public` directory and the `public_html` document root. Recompile Blade views after deployment. No migration, account changes, or new environment settings are required.

## Verification

Local verification: 17 existing tests passed across WelcomePageTest, SharedDesignPresentationTest, and FarmerWorkspacePresentationTest using SQLite in memory. Blade compilation passed. Browser checks confirmed loaded assets and no horizontal overflow on the login, welcome, synthetic authenticated navigation, and synthetic public-map previews at mobile widths; desktop navigation and login were also inspected. The collapsed rail displays the compact mark, and the mobile menu retains its controls. Deployed to Hostinger on September 18, 2026 at 07:33 UTC. The guarded release verified all 18 installed files (15 source files plus 3 mirrored public assets), saved a private application backup, and rebuilt Blade views. Live welcome/login titles and logo loading passed browser checks. No database or account changes were made.
