# Assistant agriculture release — October 20, 2026

Runtime `80166f41804b58ca3823878ebbb6841804801f45` was pushed to GitHub main
and deployed to agritarlac.online through Hostinger fast-forward pull from
`b3c16a928fa2d34ec870036e27841dcbb3043371`, with explicit owner authorization.

The release adds ten reviewed agriculture topics, local unrelated-question
refusals, preserved software/record routing and semantic numbered replies.
See [the feature contract](ASSISTANT_AGRICULTURE.md) for scope and limitations.

## Preservation and recovery

A verified private database backup covers all **27 tables / 9,987 rows**:
3,412,302 compressed bytes, SHA-256
`5b236a60607855c46369a5e490c475e31e3e8c4f369e8e9c8d292f7e4ec55781`.
The original environment, prior tracked source, affected served assets and
verification receipts are retained outside the document root in the private
`assistant-agriculture-20261020` recovery directory. No private contents are
included in this receipt.

Every original schema and full row fingerprint matched before and after release
and read-only acceptance. Environment bytes, session/cache namespaces and provider
counters were preserved. No migration, dependency install, account or operational
data change ran. The general cache was not cleared. PHP remains 8.3.35.

Both assistant public assets were mirrored to public_html. Optimized production
autoloading and configuration, route and Blade caches were rebuilt. Production
returned online with a clean tracked working tree. A separately reviewed rollback
can restore prior source and these two served assets without a database rollback.

## Verification

- Local: 833 Assistant PHP tests / 5,837 assertions; 8 module tests / 1,130
  assertions; all 332 JavaScript tests; Pint, syntax, Blade, route and diff checks.
- Live read-only CLI: all ten agriculture knowledge topics, five natural-language
  agriculture routes, three unrelated/mixed refusals, unchanged crop-drawing
  routing, updated chat disclosure render, guest denial and veterinary
  husbandry/crop restrictions passed. No provider request was made.
- HTTPS: homepage, office login, farmer login and LGU directory returned 200.
- Served JavaScript and CSS matched committed bytes over HTTPS:
  - JS: `34584b67daea1c442117835f31cf96b0221e80cba096b941505726aaed8839fd`
  - CSS: `cd6bdb2dc1a6b7fb8d305a88aa63eb173ed50c6bc36a18117ab18f8ad8ba1134`

Provider regression tests used fakes. This release did not run a live-model
accuracy evaluation or signed-in browser visual acceptance; HTTP and server
renders do not substitute for those checks.
