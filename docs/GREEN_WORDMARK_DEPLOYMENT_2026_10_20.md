# Compact green wordmark deployment ? October 20, 2026

Runtime `eeb9ed97ed8afc68f024da904bc1a58a8d04f058` was pushed to GitHub main and
installed on Hostinger by fast-forward pull from `3915849`. The application is
online at https://agritarlac.online/.

## Result

The lowercase e is agriculture green `#12633F`, matching griLGU. The supplied A
mark moves 6.15 SVG units left, its outer white margins are clipped, and griLGU
moves 15 units left. Letter outlines and the embedded logo bytes are unchanged.
The shared v4 SVG is 350 ? 104 and its transparent PNG is 1,400 ? 416. The shared
brand component and farmer-card front/back/canvas references use this same PNG;
the component's intrinsic dimensions match the new canvas. Earlier versioned
assets remain available for compatibility.

## Preservation and deployment

The private verified database backup covers **27 tables / 9,987 rows**, with
**3,412,302 compressed bytes**, SHA-256
`e227e13fb9f7afc9b7d0a50da6daab5989e1cd3481724df67e7c9313dfb90a85`.
Baseline receipt SHA-256:
`190f748dcebb882b33c25cd05c57eabf8a830d3fee0427ec51af62c1009dbbc9`.
Recovery files are outside the document root in the private green-wordmark
release directory. Source and previously served wordmarks were backed up.

All existing table schemas and complete row fingerprints matched before and
after deployment and read-only renders. Environment bytes, session/cache
namespaces and provider counters matched. No migration, account operation,
provider call, dependency installation or general-cache clear ran. Only compiled
views were rebuilt. Both new assets were mirrored to public_html with 0644
permissions and matched their repository source files on the server.

## Verification

- 58 local existing page/card tests passed, with 637 assertions; Pint, Blade
  compilation, SVG source checks and diff whitespace checks passed.
- Three scoped settings/layout renders and two existing authorized card renders
  passed in a read-only database transaction with memory sessions. Front/back
  and canvas configuration use v4; public directory and guest policy denial
  passed. The Ramos seal, card artwork and local-registry disclaimer remain.
- HTTPS welcome, office sign-in, farmer sign-in and LGU directory returned 200,
  each referencing v4 and omitting v3. SVG response bytes match the Git blob.
- The served PNG is losslessly recompressed by the hosting CDN; decoded RGBA
  pixels and dimensions match the local 1,400 ? 416 source exactly.
- Production tracked files are clean and maintenance mode is off.

Physical printing and interactive live PNG download were not repeated. No
signed-in browser session or operational data write was needed for this change.
