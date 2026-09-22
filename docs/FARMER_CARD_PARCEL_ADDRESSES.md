# Farmer-card parcel addresses — September 22, 2026

Farmer registry cards use distinct imported parcel addresses separated by ` / `. Each address joins the nonempty `PARCEL ADDRESS 1–3` components with commas. Whitespace and case-insensitive duplicates are normalized; delisted rows and rows belonging to another municipality are excluded. Residence fields and mapped-plot names are never used as a fallback. Missing data reads **Parcel address not recorded**.

The front shows the registry municipality separately. The back wraps the parcel addresses in HTML, digital preview and PNG exports. The full list remains visible above the preview and on the printed sheet. An address list that exceeds the printed card area is explicitly referred to the attached list; an oversized digital back/PNG fails with guidance rather than exporting an incomplete address. Without JavaScript, longer lists use the attachment reference conservatively.

The existing authorized farmer-card route supplies addresses through `FarmerCardLocations`; Blade does not query source records. This change performs no geocoding, schema changes, operational writes or account changes. CALABARZON provincial account provisioning remains pending the owner's four-versus-five-province choice.

## Verification

- 18 isolated PHP tests / 150 assertions passed for province/report scope and farmer workspace presentation, including the actual card route, address source filtering, normalization, missing source table and unauthorized/veterinary access.
- Three JavaScript tests passed for complete slash-separated output, unbroken Unicode address wrapping and refusal of oversized PNG output.
- Laravel Pint, PHP syntax checks, Blade compilation, route verification and diff whitespace checks passed.
- Synthetic normal/long card fixtures rendered. Browser visual, real print and PNG scan checks remain unverified because this session has no connected browser. Local CLI runs PHP 8.4; production preflight confirms supported PHP 8.3.33.

## Release

The owner authorized this release through GitHub push followed by Hostinger `git pull --ff-only origin main`. Before release, production and GitHub main were both `2248a51`, with no tracked server edits.

Deploy `app/Support/FarmerCardLocations.php`, `app/Http/Controllers/FarmerController.php` and `resources/views/farmers/id-card.blade.php` together. No public asset mirror, migration, dependency update, environment change, geography import or account provisioning is required. Take private application/database backups, refresh optimized autoloading and application caches, verify rendered card data and unchanged table fingerprints, then return the site online. Keep the full address list with any printed card that refers to it.

Production installation verification will be recorded after the pull completes. Roll back code through a reviewed Git revert and rebuild caches; this release needs no data rollback.
