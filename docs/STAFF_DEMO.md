# Isolated staff demonstration

Created October 21, 2026 at the project owner's explicit request.

## Access and scope

Open https://demo.agritarlac.online/login. The single demonstration account has the normal `municipal_staff` role in **Demo Municipality**. Its email is a sign-in identifier, not a provisioned mailbox. Credentials are held in private operator files outside this repository; do not commit them or put real personal information in this workspace.

Staff can use farmer records and cards, parcel/agronomy records, assistance, harvests, animal-health services, cooperatives, machinery, backups, dashboards and reports with their existing permissions. LGU Settings is read-only for this role. Account administration and audit access remain denied. This does not expand restricted visitor privileges in production.

## Deployment and isolation

The demo is a private copy of runtime `007adc417444935fefc8b3b7061fa6cc0cc81043`, with a conspicuous synthetic-data banner, separate database/user, application key, host-only session cookie, cache namespace and storage. The schema-only copy contains 27 tables; no production rows were imported. The demo database user was verified unable to read the production users table. Private application files and environment remain outside the document root. Both the front controller and web-server rules reject access through the production hostname's `/demo/` path. Robots indexing is disabled.

The demo has four synthetic farmers and four hypothetical parcels in its assigned municipality, sample assistance/harvest records, three animal services, a cooperative, two machinery records, a backup sample and an LGU profile/public page. One additional synthetic farmer in another municipality supports scope-denial verification and is not accessible to the demo account. Fictional registry references are not official registrations.

Following the owner’s explicit “configure it” instruction on October 21, the isolated demo now uses the existing OpenAI, Copernicus and Google Maps provider credentials, transferred privately on the same server. Provider billing and upstream quotas are shared with the existing accounts; the database, sessions, application key and storage remain separate. Secrets remain outside source control and the public root.

The demo enforces these local provider reservation ceilings:

| Service | Daily requests | Monthly requests |
| --- | ---: | ---: |
| AI Assistant | 20 | 200 |
| Satellite statistics and images combined | 10 | 100 |

Assistant user allowance is also 20/day. Automatic climate satellite refresh is disabled. Google map loads and exports retain the application’s existing controls and provider restrictions; they have no new daily billing cap. These request ceilings are not dollar spending caps. Server-side OpenAI uses the existing model, official endpoint and `store=false`; no model migration was performed. Fail-closed bootstrap checks preserve database/session isolation and reject expanded AI/satellite ceilings or enabled automatic satellite refresh. Configuration backup and rebuild affected only the demo; provider counters were preserved.

Verification produced a live AI response with 1,314 input and 224 output tokens. Browser chat answered farmer-card guidance and rendered a requested follow-up as an ordered list. The interactive map reached Ready with all four parcels; the authenticated static-map endpoint returned a valid image with HTTP 200. Copernicus statistics succeeded for September 1–7, 2026 with two observations, neither containing usable unmasked pixels for the sample parcel. A separate true-color image request returned a valid 11×12 PNG. Cloud/coverage gaps are preserved and are not converted into invented vegetation readings. Private provider verification receipts are retained outside the repository.

Production runtime and environment were preserved. A private verified production backup preceded setup, and a separate verified demo backup followed verification. This private deployment does not add a production migration or change the main application's role logic. The demo is a pinned copy and needs its own reviewed update when the main application changes.

## Verification

HTTPS login succeeded in the browser and server HTTP checks. Twenty-eight staff pages returned 200. Additional HTTP checks verified farmer creation, persistence, update and deletion; foreign municipality assignments were rejected; foreign farmer, account administration and audit access were denied; a stale edit returned validation failure without overwriting newer data. Temporary verification records were removed. Final dashboard returned 200 with the demo notice. Repeated broad verification requests hit existing throttles; a bounded final check passed after the limit window, without changing rate limits.

The public root contains no environment file. Production environment checksum remained unchanged. The demo backup verified all 27 tables with one user and five synthetic farmers. Private setup, verification and backup receipts are retained outside the web root and repository. Credentials and receipt paths should not be published.

## Operations

Do not grant this account production access or add real data. Keep the separate database, hostname guards, cookie scope and storage when updating. Review updates against the main repository, retain the demo banner, and back up the demo before changes. The demo does not automatically reset edits; coordinate sample restoration with the owner. Disable the demo account and remove hosting access when the demonstration is no longer needed.
