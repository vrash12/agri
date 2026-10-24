# Office Assistant

October 24 local follow-up (not deployed): [LGU record retrieval and UX](ASSISTANT_LGU_RECORDS.md) extends local records to seven additional task IDs and eight-source overviews, adds scope/period/time/source evidence, closes unsupported-filter routing gaps and improves the shared chat. No private record ingestion into OpenAI is introduced. Help now has 44 passages / 41 topics and requires primary-source citation; earlier counts below describe historical releases.

October 21 local refinement (not deployed): [agriculture scope and typing](ASSISTANT_AGRICULTURE_TYPING.md) retains agriculture-only questions and AgriLGU help, removes the proposed unrestricted mode, honors initial numbered-step requests and reveals replies gradually.

October 20 Assistant update (deployed in runtime `80166f4`): reviewed general agriculture
questions now use ten canonical topics alongside existing system help, with
local unrelated-question refusal. Raw chat and records stay local. General
livestock-care guidance is available to veterinary roles without additional
operational access. Numbered replies use safe semantic lists, including legacy
inline sequences. Corpus: 42 passages / 39 topics, bounded at 48 entries with
unchanged byte/retrieval limits. See [agriculture help](ASSISTANT_AGRICULTURE.md)
for source provenance, limitations, tests and deployment requirements.

## Natural questions and current feature help — October 19, 2026

Deployed on Hostinger in runtime `2a9ab66`. The bounded local router
now accepts “Please find farmer Juan Dela Cruz”, “Who is Juan Dela Cruz?”,
“Sino si Juan Dela Cruz?” and “Show me farmer Juan Dela Cruz”. Lookups return
only permitted matches; choose a matching record before asking for its crops,
plots, assistance or harvests. Names with accents, initials, apostrophes and
hyphens remain literal. The existing explicit name/ID + reporting-year lookup
continues to work. A mixed person lookup, unsupported location filter or private
field request clarifies and clears pending references instead of showing broader
office totals. Polite mutation requests such as “Can you delete this farmer?”
explain the read-only limit without reading an unrelated report.

Catalog guide names work in greetings and as addressees. “Who is Kiko?” describes
Kiko even if Ka Berto is selected. “Find farmer Kiko” remains a record lookup.
Named-guide explanations preserve validated help/report references. “Can you
make it simpler?”, “Can you explain it more?” and “More” reuse only an approved
help topic; private record replies never become model history or translation
input. These finite rules improve supported phrasing, not unrestricted language
understanding or autonomous actions.

NDMI, SAVI and EVI each have a canonical help question; “Compare NDMI and NDVI”
explains index purposes rather than requesting an unsupported year comparison.
El Niño Readiness help explains office priorities, missing/expired assessments,
zero counts, dated source evidence and authorized assessment entry. The climate
page supplies only its allowlisted module name, not filters or assessment fields.
The guide cannot fetch fresh climate/satellite observations or diagnose a parcel.
Veterinary accounts retain Animal Health-only help and records.

The reviewed corpus now has 32 passages and 29 canonical topics. Evidence-size
limits, canonical-only provider inputs, accepted-help caching, budgets, five-per-
minute chat throttle, encrypted context lifetime and all policies remain in force.
No new service, model, dependency, schema or paid operation is introduced.
Release the Assistant PHP, curated climate passage and shared view together;
regenerate optimized autoloading and config/route/view caches without a general
cache clear. See [the deployment receipt](GITHUB_DEPLOYMENT.md) for runtime status.

## Farm guide characters — October 19, 2026

Deployed on Hostinger in runtime `11d1a1f` through GitHub main and a fast-forward
pull. The release preserved all 24 tables / 9,946 existing rows, private settings
and provider allowances; no schema or operational record write ran.

The shared office chat now offers **Ka Berto** (default cow), **Ka Kaloy**
(carabao), **Aling Maya** and **Kiko**. Open the portrait in the chat header to
switch. The launcher, header, welcome image, composer and new reply labels use
the selected guide. Existing replies keep their original guide, and Retry
keeps the appearance captured for that request. Switching preserves the chat,
draft and opaque reference and never calls a provider. New chat still clears
the conversation. In-flight requests and expired sessions block switching.

Only the allowlisted appearance ID is stored at
`eagrilgu.assistant-guide.v1` in optional browser local storage. No message,
record, account ID or session reference is stored there. Missing/blocked
storage works in memory; unknown values fall back to Ka Berto. The optional
`guide` request field uses `AssistantGuide::ids()` validation; older clients
default to Ka Berto. This field affects only local identity text, not intent,
authorization, report scope, provider context, budgets or encrypted references.
The four characters are appearances of the same helper, not separate agents
or real staff. About your guide and local introductions retain truthful
software/AI, privacy and official-decision limits. Reviewed help uses a shared
generic farm-guide voice; no appearance preference is sent to OpenAI.

All eight 160px/384px WebP files in `public/images/mascots` are local transparent
delivery images from the four approved concepts. Release the catalog, request,
controller, identity/conversation, reviewed guidance, agent, view, CSS/JS and
images together. Regenerate optimized autoloading and Blade; preserve existing
general cache and provider allowances. No migration, dependency, account,
environment change or additional service is required. Runtime release status
is recorded in [the deployment receipt](GITHUB_DEPLOYMENT.md).

October 16 local architecture follow-up: conversation/help services now live in
`App\Modules\Assistant`, while local record reads live in
`App\Modules\Reporting\Application\AssistantRecords`. The conversation depends
on `OfficeRecordReader`; ModuleServiceProvider binds it and AuthServiceProvider
applies the existing OfficeAssistantPolicy to the contract. The corpus, provider
disclosure, budgets, encrypted context format and routes retain their behavior.
See [Modular monolith](MODULAR_MONOLITH.md) for the complete local refactor and
atomic release requirements. Earlier Support names below are historical.

## Verified context-awareness release — October 12, 2026 — runtime `d89aa10`

The unified conversation now answers bounded questions about the Assistant and
the signed-in AgriLGU workspace locally. “Do you know AgriLGU?” and “Are you
self-aware?” explain that this is a software helper using reviewed guidance,
not a conscious person. “What page am I on?” and “Where am I?” use the
allowlisted module hint supplied by the current office page. “What is my role?”
and “What is my office scope?” use the authenticated account assignment.

The page label is relevance context only: it does not reveal active filters or
unsaved fields, grant access or replace policies. Awareness replies do not query
farmer/parcel/assistance tables, reserve provider allowance or call OpenAI.
They retain validated opaque conversation references for polite follow-ups, and
veterinary accounts remain Animal Health-only for operational work. Unknown or
mixed/private questions continue to clarify locally. Do not describe this as
consciousness or unrestricted system/file awareness. The change is deployed in
runtime `d89aa10` through GitHub main and Hostinger fast-forward pull. Unit and
feature coverage passed the focused Assistant tests with the existing file-cache
warnings; no schema, dependency version, environment, account or operational
data change was made.

## Local response-state follow-up — October 12, 2026 (not deployed)

The unified conversation keeps an assistant bubble visible while a request is
in flight. It uses the plain “Thinking…” status with a small animated dot cue,
an accessible live status and `aria-busy` on the pending message. A verified
answer, bounded error/retry state or cancellation replaces the indicator. The
animation is disabled when the user prefers reduced motion, and the cue never
represents or reveals hidden chain-of-thought, provider details or private
records. This is a presentation-only change: routes, authorization, payloads,
provider prompts, records, schema, dependencies and account data are unchanged.
The JavaScript Assistant suite passed 40 tests. Release the stylesheet,
partial, script and regression tests together after owner authorization;
production remains on the previously recorded runtime.

## Local data-quality review follow-up — October 12, 2026 (not deployed)

The unified conversation now recognizes “What needs attention in our registry?”
and related data-quality phrases as a local, read-only review. After normal
authentication, policy checks and `MunicipalityAccess` scoping, it returns
current aggregate counts for registered farmers, missing FFRS references,
missing farm locations, farmers without saved parcels and saved plots without
boundary geometry. It provides links to the existing farmer registry review
filters and explains that these are review queues, not fraud findings.

This task does not accept a historical reporting year; it clarifies locally and
resets context instead of returning an unscoped or misleading total. It never
selects names, identifiers, addresses, contacts, notes, coordinates or geometry
values for the response and never calls OpenAI. Veterinary and other existing
role boundaries remain in force. No migration, dependency, environment,
configuration or account change is needed. The local focused suite passed 177
tests and 2,188 assertions with 128 pre-existing warnings; the warnings are
test-file `file_get_contents` notices, not failures. Production remains on the
previously recorded runtime until an owner-authorized release.

## Local workflow follow-up — October 11, 2026 (not deployed)

`AssistantNextSteps` provides up to three application-owned next messages after
help, identity, reports or supported clarifications. It uses reply types and
approved references without querying records, calling AI or reading generated
text. Prompts never contain private names, IDs or record values. Farmer searches
keep their separate signed choices; veterinary suggestions remain Animal Health
and shared help only.

Click a chip to fill an editable draft, then press Send. Unfinished drafts are
never overwritten. Suggestions capture their reply's opaque reference and retire
on a newer reply; editing releases the captured reference and uses the current
conversation. Busy, session and rate-limit states apply. New chat/session expiry
and page exit clear staged drafts. There is no automatic provider call, stored
chat, mode selector or wider disclosure.

After selecting a farmer, “Show assistance for 2025” asks “Selected farmer” or
“Whole office” and retains 2025. Optional version-1 `pending_scope` permits only
assistance/harvests, task `farmer`, a positive selected reference, one year and no
choices. English scope replies and “Napiling magsasaka” / “Buong opisina” resolve
that task. A bare year updates the period but still asks for scope. Multiple years
require one year before scope; scope-only replies without a pending task cannot
infer a report. Successful new help/reports replace hints, and rejected subsets
reset them. Every selected read reauthorizes the farmer; whole-office results
clear selection. Old tokens, 4 KiB bounds, 15-minute expiry and user/session/scope
binding remain compatible.

Implementation sequence: retain only approved pending references; resolve scope
and year locally; select deterministic role-aware prompts; stage editable messages;
verify isolation, stale references, drafts and failure paths. Release the new
support class, conversation/context/resolver, reviewed guidance and both assets
with the earlier local reliability changes. No migration, dependency, environment
or account operation. Never clear the general cache. Production remains `82a24c4`.

Verification passed **552 PHP tests / 3,578 assertions** and **216 JavaScript
tests**, including 39 Assistant UI cases. Pint, changed-PHP syntax, Blade
compilation, route and whitespace checks passed; all 78 checked local Markdown
links resolve. Independent review and tests cover scope/year ambiguity, farmer
reauthorization, expired/changed references, veterinary suggestions and bounded
payloads. A normal synthetic local office browser verified farmer search/choice,
2025 scope clarification, preserved unfinished drafts, draft-only suggestion
selection and explicit Send returning the selected farmer's 2025 report.
Rate-limit/stale-chip/focus failures are automated checks, not new live browser
acceptance. No production acceptance, load benchmark or deployment ran. The
pending farmer-card patch passed a reverse-apply check without being changed.

## Local reliability follow-up — October 11, 2026 (not deployed)

Help references retain optional `help_language` (`en`/`fil`) alongside topic/style.
After Filipino guidance, “Make it shorter” stays Filipino. “In English”, “In
Filipino” or “Tagalog po” repeats canonical help in that language and preserves
the format. A help question may end with “in English” or “in Filipino”. Old
version-1 tokens still work; 4 KiB limits, binding and 15-minute expiry remain.
Language requests after private reports clarify locally and preserve the valid
reporting reference. No previous answer, raw translation text or history enters
OpenAI.

`AssistantReportRequest` checks finite recognized unsupported report subsets
before scope/year resolution can keep an incorrect broad pending reference:
crop/input, season/month/date-range, gender/mapping/registry, species/service,
status/quantity and protected fields. These paths make no operational query or
provider call and clear pending context, so a bare year cannot widen the rejected
request. Literal farmer names and feature-help instructions stay separate. This
is a bounded recognizer, not exhaustive language understanding. Unqualified
annual reports remain supported; legacy generic seeds/binhi aliases still select
all-category assistance, not a seed-only filter. Simple farmer-ID/parcel/seasonal-
crop definitions now resolve to reviewed help instead of record counts.

HTTP 429 responses produce a visible memory-only retry countdown. Seconds and
HTTP-date Retry-After headers are bounded to 1–300 seconds; missing/invalid values
use 60 seconds. Send/Retry/choices wait while the draft stays editable. New chat
and Close do not bypass this page's wait. Reload has no persistent client timer
and server throttling remains authoritative. Start/end are announced without
every-second live-region chatter. After an initiating Send/choice/Retry loses
focus, the composer regains focus unless the user moved elsewhere. Plain-text
rendering and same-origin links remain.

No migration, dependency, environment or account update is required. Release
backend/context/resolver/guard, reviewed passages, partial and both public assets
together. Cache identity already includes language and corpus. Never clear the
general cache or reset provider allowances.

Verification passed **529 PHP tests / 3,252 assertions** and **206 JavaScript
tests**, including 29 Assistant UI cases. Pint, changed-PHP syntax, Blade
compilation, route verification and `git diff --check` passed. A normal local
browser session using synthetic office records verified filtered-report
clarification, Filipino guidance, a shorter Filipino follow-up and an English
switch on the same crop topic. Countdown and focus behavior were verified by
automated UI tests; this is not a production acceptance or load test. The pending
farmer-card patch passed a reverse-apply check without being modified.

## Deployed context-aware baseline

Current follow-up status: the October 11 context-aware, AgriLGU-only extension is
deployed on Hostinger in runtime `82a24c4` through GitHub main push and Hostinger
fast-forward pull. Local verification passed 386 PHP
tests / 2,493 assertions and 198 JavaScript tests; historical counts and live
checks below describe earlier stages.
No migration, dependency, environment setting or account change is required.

Deployed baseline: Hostinger installed runtime `7d36fff` on October 11, together
with the Laravel 12 / PHP 8.3+ dependency upgrade and RAG follow-up. GitHub push,
Hostinger fast-forward pull and locked Composer install completed. Existing
schema, operational records and accounts were preserved; nine approved AI
environment settings were provisioned privately. See the
[deployment receipt](GITHUB_DEPLOYMENT.md).

The subsequent [RAG follow-up](ASSISTANT_RAG.md) adds reviewed role/topic-filtered
workflow retrieval, strict structured source checks, ten-minute general-help
caching and saved-guide fallbacks. The verification section below records the
earlier unified stage; current RAG checks are in that companion receipt. Record
reports remain fresh local queries; the one-conversation interface is unchanged.

## October 11 deployed context-aware follow-up

The owner's clarified scope is help with **all documented AgriLGU features**,
not unrelated generic conversation. `AssistantIdentity` handles who the
Assistant is, greetings, thanks, capabilities and technology questions in
English/Filipino using application-owned text. Those replies need no provider
call, budget reservation or operational query. They describe a software helper,
not an official decision-maker, and do not advertise autonomous tools.

The curated manifest now contains 31 reviewed passages for 24 canonical topics.
Alongside the original crop/parcel drawing, seed import, NDVI, Animal Health,
access, farmer sign-in/ID, harvest and Assistant help, it covers dashboard,
weather, cooperatives, machinery, backups, audit, geofences, system overview,
AI technology/privacy, farmer data quality/registry and assistance
management/coverage. Help describes implemented workflows; it does not add live
queries to those modules or grant their permissions. Veterinary users can ask
local identity and AI/privacy questions, while operational access remains
Animal Health-only.

After a supported help answer, “Explain more”, “Make it shorter”, “Step by step”,
“Give an example” or “Explain simply” retains the approved help topic and chooses
one controlled style. The new provider request still contains only that
canonical topic, eligible reviewed passages and application-owned role/language/
style instructions. It never contains the earlier answer, typed follow-up,
farmer text or mixed thread. A vague explanation after a record answer clarifies
locally rather than converting records into AI context. “Explain this page” may
use the allowlisted page module for relevance. The existing assistance-page
generic Excel/upload aliases also resolve to seed-import help; explicit topic
words take precedence. Hints never supply authority or select protected records.

Satellite/NDVI and Assistance coverage pages have distinct safe page hints.
Greeting/thanks interludes preserve the previous validated report/help reference
so an example request or reporting-year follow-up can still resume it.

No general AI mode, uploaded-document processing, live browsing, filesystem or
execution tool is introduced. Runtime evidence is limited to the explicit
reviewed manifest under `resources/assistant/knowledge`; code directories,
private files and the wider repository are not scanned or ingested.

The focused integrated suite passed 386 PHP tests / 2,493 assertions, including
local identity, all approved topics, permission/isolation, encrypted follow-ups,
legacy tokens, canonical disclosure, cache/fallback and existing local reports.
All 198 JavaScript tests passed, including plain-text conversational replies and
opaque-reference-only requests. Pint, syntax, Blade compilation, route checks
and whitespace checks passed. The actual local Laravel/MySQL application used
normal sign-in/CSRF against a synthetic database: introduction, dashboard page
guidance and a shorter same-topic follow-up succeeded with the configured real
provider. No live farmer data was used for that browser check. Live acceptance
belongs to the deployment receipt; these counts are not a production load test.

Live runtime `82a24c4` passed five local identity replies, four help follow-ups,
15 page contexts, all 24 help topics and five veterinary topics, seven office-role
policies and one excluded account, six local reports, two workspace renders and
one existing farmer-card render. Canonical dashboard guidance used 784 input /
157 output tokens and one source; its accepted cache hit used zero new provider
tokens. All 21 tables / 9,372 pre-existing rows and schemas were unchanged.
Environment values, application key, session/cache namespaces and NDVI counters
were preserved; no migration, account or environment operation ran. Pending
farmer-card changes remain local. These are live CLI/provider smoke checks, not
a load test or interactive sign-in test for every account. See
[GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md) for the full release receipt.

## One conversation in the signed-in application

The small **Assistant** launcher opens one conversation in normal office pages.
There are no help/report modes, module sections or reporting-year controls. Staff
ask a question, choose a permitted farmer when needed and ask a follow-up. New
chat clears the thread; Close/Escape returns focus. Enter sends, Shift+Enter adds
a newline and IME composition does not submit. Earlier reading positions remain
with a New messages button. Loading/errors/retry appear in the conversation.
The shared theme, 44px controls and mobile/short-screen behavior are retained.

| Message | Behavior |
| --- | --- |
| How many farmers do we have? | Current authorized registry/mapping totals |
| Show assistance for 2025 | Receipt-year releases and quantities by input/unit |
| What about harvests for the same year? | Reuses the resolved report year |
| Find farmer Juan | Up to six permitted matches with opaque reply choices |
| Show his parcels / his assistance / his harvests | Reauthorizes the selected farmer and reads the applicable bounded summary |
| How do I draw a corn area? | Canonical general crop-editor help through the AI SDK |
| Who are you? / Sino ka? | Local identity reply with scope and read-only limits; no AI call |
| Explain this page | Uses that page's allowlisted topic as a relevance hint, with normal authorization |
| Make it shorter / Give an example | Reuses only the approved prior help topic with a controlled format |
| Show assistance for 2025 and 2026 | Asks for one year; a bare year reply resumes the pending report |

Explicit year, prior unambiguous report year, then `LocalTime::now()->year`
determine the period. Last year uses the current display year; previous year
uses the prior report year. Registry totals are current, not historical snapshots.
Unknown, mixed and ambiguous tasks clarify locally. Year comparisons and narrower
province/municipality subsets are not implemented; use the register filters for
a subset. Never substitute whole-account totals for a specific-area question.
Selected-farmer vs whole-office ambiguity clarifies; “our assistance” clears
the selected target.

## Server contract and permissions

`POST /assistant/message` retains office authentication, idle expiry, active scope,
CSRF, restricted-role middleware, role policy and five requests per minute. The
seven scoped office roles can use it. The deployed follow-up permits veterinary
identity and AI/privacy explanations plus Animal Health help/reports; it does not
grant other operational access. Visitors, GIS evaluators, guests and farmer-guard sessions are
denied. All operational access remains read-only.

The request accepts `message` (800 characters), an allowlisted page `module` hint,
and optional opaque `context` / `choice` strings (4 KiB). Retired `mode`, `history`,
`year` and `farmer_id` fields return 422 with reload guidance. Page hints never
supply authority. `AssistantConversation` resolves supported tasks locally and
uses existing policies / `MunicipalityAccess` before report queries. Farmer
authorization precedes relationships. Related crop/release/harvest rows must
match that farmer's municipality even for broad owner access. Foreign and
nonexistent farmer references fail identically.

`AssistantConversationContext` uses authenticated Laravel encryption for minimal
versioned task/year/selected numeric farmer/up-to-six choice references. Tokens
expire after 15 minutes and bind to the current user/session/numeric account
scope. They contain no names, results, totals, geometry or transcript. Tampered,
expired and changed-scope references reset; cross-account/session replay returns
no data. A token is only a hint: every follow-up checks current permissions and
record ownership. Pending year clarification keeps only a known task/year/farmer
reference. It never retains the original question. Version 1 remains compatible
with existing tokens. The follow-up adds optional `help_topic` and
`help_style`: an allowlisted topic and one of `plain`, `brief`, `steps`, `example`,
`more`. Old tokens without those fields remain valid but need a fresh help turn
before a generic topic follow-up can resolve. No new transcript or answer field
is accepted, and the 4 KiB/15-minute/user-session-scope limits are unchanged.

Replies carry application-owned source/scope/period, bounded choices and links
to protected named routes. Render with `textContent`, not HTML. The browser
rejects cross-origin, credential-bearing and non-HTTP(S) links. Responses retain
private/no-store security headers.

## Records, AI and privacy

Operational reports stay inside AgriLGU with no provider call or allowance.
Queries use database aggregates, literal parameterized name searches, numeric
ID lookup, six matches and ten quantity groups with explicit partial notices.
They never load complete registers or geometry. Selected farmer summaries show
AgriLGU ID, registry office, declared/mapped area, parcel count, classified crops,
assistance and harvests as applicable. Contacts, birth/eligibility details,
credentials, free-text notes and coordinates are excluded.

Assistance uses receipt dates and valid same-municipality beneficiary links;
blank legacy release units mean kg. Harvests use recorded harvest year and keep
commodity/unit totals separate. Missing/undated values are disclosed, not turned
into zero. Animal services use service year and the existing one-animal fallback
for missing/zero legacy counts; repeat services are not unique animals. No unit
conversion, yield or unimplemented crop-to-harvest link is inferred. Multiple
aggregate queries read changing records, not a guaranteed transactional snapshot.

`OfficeAssistant::answerTopic` accepts only application-owned `TOPICS`. The model
receives a canonical system question and reviewed passages/module/role/language/
controlled style.
Help now retrieves at most three reviewed passages rather than the entire static
guide. Strict structured output and server validation check returned citation IDs;
compact application-owned source titles appear in the conversation. Accepted
system help is cached by current evidence/prompt/style/model/role/topic/language for
ten minutes. Cached replies consume no new allowance and report zero new tokens.
Generation failures/rejected replies use the complete saved primary passage.
These checks aid grounding, but do not prove factual entailment.
Raw messages, record searches, farmer choices, reports, tokens and the visible
thread never enter OpenAI prompts/history. Unknown or ambiguous text does not
fall through to an external classifier. Help after a record reply still has no
record context. No tools, arbitrary SQL, uploads or operational writes exist.
Key-pattern checks are not anonymization; the canonical-topic boundary excludes
arbitrary text from the provider. Never paste credentials or private documents.

Chat stays in browser memory; no conversation table, localStorage, sessionStorage
or raw transcript in Laravel session/cache is introduced. New chat/page exit/
logout/session expiry clear it; Close may preserve the in-page thread. Cancelled
and stale replies are discarded. Logs/audit retain only safe task/year/source/
match/latency/token counts and normal attribution. Never log question/reply/search,
selected farmer name/ID, context token, SQL/bindings, provider bodies, exception
messages or credential-bearing traces.

The official OpenAI URL, `OPENAI_STORE=false`, pinned
`gpt-4.1-mini-2025-04-14`, 20-second timeout, 600 output tokens and 3,000-character
provider limit remain. Response storage off is not universal zero retention;
see [OpenAI data controls](https://developers.openai.com/api/docs/guides/your-data).
Local reports permit at most 6,000 answer characters.

Default atomic UTC allowances remain 100 requests/day, 1,000/month and 20/user/day.
Reservations are brief and precede inference. Cache failures fail closed;
uncertain failed attempts keep their reservation and are not retried automatically.
These controls are not provider billing or a currency cap. Never clear the general
cache or reset NDVI/Assistant budgets. Multiple hosts need shared atomic locking.

## Concurrency and sessions

Only the exact Assistant route excludes general mutation synchronization.
`SynchronizeAssistantRequests` instead acquires a nonblocking per-user Assistant
lock with bounded expiry. An in-flight question returns 409; cache failure returns
safe 503. Provider waits hold no farmer/municipality or operational account write
lock and no database transaction. Other write synchronization is unchanged.

`StartApplicationSession` keeps normal Laravel session behavior on other routes.
An acquired chat commits idle/session activity before slow work, then skips the
late whole-session save and session-cookie refresh. `VerifyCsrfToken` still checks
the request but skips the late XSRF cookie refresh for that acquired chat too.
A delayed reply cannot recreate
a concurrently destroyed session or overwrite another request's auth/CSRF/flash.
JSON validation never flashes chat, and chat services do not mutate session state.

## Historical local unified verification

- **55 focused PHP tests / 426 assertions** and a **40-case synthetic resolver
  corpus / 109 assertions** passed on PHP 8.3.35. Coverage includes normal sign-in
  with CSRF/cookies, scopes/roles, year/farmer follow-ups, pending clarification,
  encrypted hints, canonical provider separation, stale clients, safe failures,
  query/payload growth and dedicated locks. Three encrypted file-session checks
  cover normal login persistence, concurrent destruction and replacement.
- **194 combined PHP tests / 1,486 assertions** passed, including the above and
  sign-in, province/region access, provider usage, presentation and portal
  regressions. These counts overlap with the focused group.
- **19 JavaScript tests** passed for contract/output/choice safety, retry,
  cancellation, session clearing, keyboard/IME, scroll and the unified interface.
- Actual browser checks passed at the normal viewport, 389px phone and 389 × 417
  short-screen sizes, with visible Send/Close/New chat and 44px controls. A
  scrollbar-related phone crop was corrected by sizing against the layout viewport
  instead of `100vw` and rechecked. Narrow reflow also passed; actual browser
  200% zoom and a physical mobile keyboard remain untested.
- Normal application bootstrap, office login, dashboard and chat were exercised
  on an isolated local MySQL schema with synthetic data and valid CSRF. No
  preview adapter, automatic login or CSRF bypass served the page. Office records
  were not copied. Farmer search/choice and parcel/crop/assistance/harvest replies
  passed; canonical provider help was exercised after record answers.
- Local MySQL measurements used **10,001 synthetic farmers**, ten runs/report.
  Tested domain-service p95 was **13.63–38.42 ms**, with at most **28 queries**
  and **1,213 JSON bytes**. This excludes HTTP/auth/audit/tokens and provider
  latency and is not a Hostinger/load-performance claim. The 801-row regression
  independently verifies bounded query count and full endpoint JSON below 32 KiB.

The first real-provider attempt failed safely because standalone Windows PHP
lacked CA trust configuration. An official
[curl Mozilla CA bundle](https://curl.se/docs/caextract.html) was downloaded into
the private verification runtime, checked against its published SHA-256 and
supplied through curl/OpenSSL CA settings. Certificate verification stayed on;
the models probe returned 200 and an actual canonical help reply then succeeded.
No application TLS setting was weakened. Keys remain private and absent from
source, fixtures, screenshots and this receipt.

These local checks are distinct from the subsequent live acceptance below and
do not establish multi-user/load readiness. The additional legacy-schema
dependency integration passed **146 tests / 974 assertions** on a disposable
synthetic MariaDB database; see
[LARAVEL_12_AI_UPGRADE.md](LARAVEL_12_AI_UPGRADE.md).

## October 11 live acceptance

Runtime `7d36fff` runs on verified CLI/web PHP 8.3.35, Laravel 12.69.3 and AI SDK
1.2.0. A verified private backup covered **21 tables / 9,366 rows**. Schema and
pre-existing row hashes, application key, session/cache/Redis namespaces and
NDVI counters were preserved. Only nine approved AI environment settings were
provisioned; no migration, seeder, account change or general cache clear ran.
Pending farmer-card changes remain local.

Read-only live CLI checks passed seven office-role policies, one excluded
account, ten general-help topics, three veterinary-help topics, six report
paths, two page renders and one card render. A real canonical crop-help reply
used **834 input / 173 output tokens** and returned two sources; the accepted
cache hit used zero new provider tokens. Five public HTTPS checks returned 200.
Assistant CSS/JavaScript matched source in both public directories with 0644
permissions. These are server/public-HTTP smoke checks, not a load test or
interactive sign-in verification for every account. See
[GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md).

## Configuration and deployment

The example remains disabled; the authorized Hostinger environment is now
enabled after its reviewed dependency installation and acceptance. For another
environment, enable only after the same checks:

```dotenv
ASSISTANT_ENABLED=true
ASSISTANT_MODEL=gpt-4.1-mini-2025-04-14
ASSISTANT_DAILY_REQUESTS=100
ASSISTANT_MONTHLY_REQUESTS=1000
ASSISTANT_USER_DAILY_REQUESTS=20
OPENAI_STORE=false
```

For an authorized release, use [GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md):
push reviewed scope, Hostinger fast-forward pull, then install the exact Composer
lockfile. Release conversation/context/resolver, request/controller, provider/
agent, policies/config, audit/veterinary middleware, session/CSRF middleware/kernel,
route-specific concurrency, reviewed knowledge/retrieval/grounding, layout/dialog and both public assets
together. Mirror CSS/JavaScript to both public directories with 0644 permissions.
Stale clients must receive reload guidance instead of the retired raw-help path.
For this follow-up, include `AssistantIdentity`, updated resolver/context/
conversation/provider/agent, all curated knowledge and relevant tests/docs as one
reviewed change. The deployed Laravel 12/AI SDK foundation is unchanged; no new
Composer requirement or environment variable is introduced. Runtime `82a24c4`'s
follow-up verification and live acceptance are recorded separately from
`7d36fff`'s earlier checks.

Refresh configuration/route/compiled-view caches only. No migration, seeder,
conversation table, account or operational-data change is required. Do not run
blanket migrations/seeders, Composer update or a general cache clear. To pause,
set `ASSISTANT_ENABLED=false` and refresh configuration. Roll back compatible
runtime/dependencies/assets together while preserving records/files/sessions/
allowances.

## Historical local stages

Initial help-only checks passed 15 focused PHP tests / 78 assertions, 112 broader
PHP tests / 1,025 assertions (overlapping), six JS tests and one synthetic general
OpenAI reply (1,606 input / 198 output tokens). The two-mode records stage passed
28 focused PHP tests / 175 assertions, 127 broader PHP tests / 1,126 assertions
(overlapping) and ten JS tests. Its sample browser adapter was test-only. Neither
stage was deployed; its counts do not establish unified-chat behavior.

See [AI_ASSISTANT_IMPLEMENTATION_PLAN.md](AI_ASSISTANT_IMPLEMENTATION_PLAN.md).
Saved chat history, operational-data analysis by providers, uploads, new roles,
streaming, vector retrieval and autonomous record writes remain outside this release.
Local lexical workflow RAG is now implemented in the follow-up above.
