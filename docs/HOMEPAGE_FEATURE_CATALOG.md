# Homepage feature catalog

Status: local implementation, not pushed or deployed.

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
configuration, dependency or general-cache clearing is required. Production was
not changed for this work.
