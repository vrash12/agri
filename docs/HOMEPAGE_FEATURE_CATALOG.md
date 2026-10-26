# Homepage feature catalog

## October 27 sample showcase

The homepage retains its AgriLGU identity, farmer services, official resource
links and seven workflow groups while adding a revised introduction and an
original SVG preview for each of the **28 feature areas**. The previews are
illustrated interfaces with explicitly fictional sample records and figures,
not production screenshots, live statistics or newly inserted operational data.
The public page continues to require **no database or provider requests**.

`scripts/build-homepage-previews.mjs` generates the static images in
`public/images/showcase/` using only its application-owned sample content:

```sh
node scripts/build-homepage-previews.mjs
```

Keep visible sample labels in the page and artwork. Previewed IDs, maps, records,
reports and controls explain the feature; they do not issue cards, disclose real
records or perform office actions. Existing authorization and role destinations
remain unchanged.

`public/js/welcome-showcase.js` enhances same-origin preview links with a native
dialog, Previous/Next controls, Escape dismissal and focus return to the opener.
Loading and failure messages identify the selected image; late image responses
cannot replace the current preview. Direct image links work when JavaScript or
native-dialog support is unavailable. Existing native feature disclosures and
category anchors remain available.

Verification so far: **eight focused PHP tests / 469 assertions** and **24 related
JavaScript tests passed**, including the query-free public-page contract and
viewer behavior. Browser checks loaded all 28 full-size previews, verified the
last-item navigation boundary, keyboard opening, Escape and return of focus to
the initiating link. Native disclosures and page widths passed at 320, 390,
768 and 1024 pixel iframe widths (including the browser scrollbar); the 1440
pixel desktop layout passed too. The narrow dialog fits without horizontal
scrolling. These checks do not establish physical-device, screen-reader or staff
acceptance. Runtime `a095aaa` is deployed; older receipts below describe their
original releases.

Deploy `resources/views/welcome.blade.php`,
`resources/views/partials/welcome-features.blade.php`,
`public/css/welcome-showcase.css`, `public/js/welcome-showcase.js` and all 28 SVG
files together. Mirror public assets into the served `public_html` directories
and rebuild compiled views. No migration, operational seed, dependency,
configuration, provider or permission change is required. Preserve general cache
and provider counters.

### October 27 production receipt

Runtime `a095aaaa78003700a89789ac8b396e65c45c4f1c` was pushed to GitHub main
and installed on Hostinger by an exact-commit fast-forward from `9f9de81`.
The central site is `https://agri-lgu.online/`. All 30 new served assets matched
the reviewed source bytes over HTTPS, and the public page shows 28 previews.

The verified private database backup covers **27 tables / 10,039 rows**, with
3,414,973 compressed bytes and SHA-256
`f6bdb38d44c744bab40a962835da86757fce3ebd66cb4021601c3bf7b872a40c`.
Recovery assets and receipts are outside the document root at
`/home/u330835917/.private/homepage-showcase-20261027`. Every schema and row
fingerprint matched before and after installation. Environment bytes, cached
configuration/routes, autoload files, private web rules and provider counters
also matched. Only compiled views were rebuilt; no migrations, seeds, account
changes, dependencies or general-cache clear ran.

The PHP 8.3.36 live matched-route check rendered the guest homepage with zero
database queries, external requests or started sessions. Live browser checks
verified the fictional-data notice, map preview, paging, Escape, focus return
and workflow navigation. Office sign-in, farmer sign-in, the LGU directory,
the existing Mayantoc site and isolated demo returned HTTP 200. The old central
domain still redirects to the new domain while preserving query parameters.
Maintenance is off. The sample SVGs total 121,446 bytes and remain static;
they add no operational records or real public statistics.

## Original catalog release

Status: deployed and verified on October 20, 2026 in runtime
`95e831a661370f223d17c059d2f5d025370a5030` through GitHub main and Hostinger
fast-forward pull from `4623873`.

The public homepage retains its farmer-services hero, photograph collages,
service guidance and official resources. System features replaces the older
six-tool overview with 28 feature descriptions in seven workflow groups.
Content lives in `resources/views/partials/welcome-features.blade.php` and is
rendered as static text without database or provider requests.

| Workflow | Feature areas |
| --- | --- |
| Farmers and records | Registry; digital IDs / public QR parcel pages; read-only farmer portal; record-quality review |
| Land, crops and maps | GIS parcel workspace; seasonal crops and sections; administrative references; parcel sheets / snapshots |
| Weather and field decisions | Satellite indices; El Niño readiness; crop guidance and field visits; forecast / rainfall evidence |
| Assistance and harvests | Agriculture / fisheries releases; rice distribution sheets; harvests / production; coverage maps |
| Office work | Animal Health; cooperatives; machinery; dashboards / operational reports |
| Connected offices | Assistant; LGU profiles / ID settings; public LGU websites; protected files |
| Access and data | Accounts / geographic scope; audit; privacy / shared editing; imports / exports / service usage |

Category anchors provide shortcuts. Native details/summary controls expose
explanatory bullet lists by mouse or keyboard without JavaScript. Shared tokens,
Inter body typography and the existing green/yellow homepage treatment remain.
Feature panels use green focus outlines on light surfaces; the dark introduction
uses yellow focus. Existing sign-in redirects, provider behavior, routes, role
scope and public-data rules are unchanged.

Copy was checked against the current feature catalog, route/controller behavior
and dedicated portal, satellite, agronomy, Assistant and LGU documents. In
particular, portal records are read-only, coverage colors represent municipality
releases, snapshots do not promise legends, and satellite/forecast observations
do not declare drought or prescribe planting. Protected-file editing and the
manual nature of Backup Folder are described accurately.

## Local verification

- WelcomePageTest: six tests / 64 assertions passed, including the query-free
  public page, added feature descriptions, browser headers and role redirects.
- Fifteen existing homepage/slideshow JavaScript tests passed.
- Pint, PHP syntax, Blade compilation and git diff checks passed.
- Browser preview: 28 disclosures / seven groups; category targets, keyboard
  toggling and JavaScript-disabled native disclosures worked without page errors.
- All disclosures expanded at widths 1440, 1024, 768, 390 and 320 without
  horizontal page overflow. Desktop and mobile previews were inspected.

## Release requirements

Deploy the homepage view, new partial and welcome stylesheet together. Mirror
`public/css/welcome.css` into served `public_html/css/` with 0644 permissions and
rebuild compiled views. Existing filemtime URLs version the asset. No migration,
configuration, dependency or general-cache clearing is required.

## Production release receipt - October 20, 2026

The authorized deployment preserved all 27 tables / 10,013 rows. The verified
private backup is 3,413,566 compressed bytes, SHA-256
`c8eb349baf333835801b404dbf275bd431f63d6f1c6f4e338ec5cf3dbc508113`.
Baseline SHA-256:
`ee35328fbeafc8dd35ebbf21237f096f9d3690a53bb3e015dd3d3b28cf51a34a`.
Recovery files are outside the document root at
`/home/u330835917/.private/homepage-features-20261020`, including database,
environment, source, previous served stylesheet and private verification receipts.

Every original schema and row fingerprint matched before and after read-only
verification. Environment bytes, session/cache namespaces and provider counters
matched. No migration, account/content provisioning, provider call, dependency
installation or general-cache clear ran. Compiled views were rebuilt; unrelated
untracked server files were retained.

Production PHP 8.3.35 checks confirmed 28 feature descriptions, seven groups and
seven category shortcuts. The public homepage route/view rendered with zero
database queries. Four unsaved in-memory identities retained municipal staff,
System Owner, veterinary and GIS-evaluator homepage destinations; no account or
session was written. Existing services, collage, official resources and entry
links remained present.

The mirrored welcome.css matches Git bytes over HTTPS (SHA-256
`2c0f89c62a34bc48663a1f27d848f0380db6eb4a3e687c9f9f82e8f7a3b9422a`).
The homepage, office sign-in, farmer sign-in and LGU directory succeeded over
HTTPS. Maintenance is off; tracked production files were clean after installation.

Live browser verification confirmed HTTP 200, all 28 areas, keyboard expansion,
no horizontal overflow at 1440 and 390 pixels with every disclosure open, browser
security headers and no page errors. A mobile feature-introduction screenshot
was retained privately. This release did not repeat authenticated editing,
printing or provider-backed feature execution because those workflows were not
changed.
