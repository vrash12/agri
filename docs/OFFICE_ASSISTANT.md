# Office Assistant

Status: unified conversation implemented and verified locally on October 11,
2026. Not committed, pushed or deployed. Production remains on recorded runtime
`c322a5e`. This feature depends on the pending Laravel 12 / PHP 8.3+ upgrade.
No production account, record, environment or schema operation ran.

The subsequent [RAG follow-up](ASSISTANT_RAG.md) adds reviewed role/topic-filtered
workflow retrieval, strict structured source checks, ten-minute general-help
caching and saved-guide fallbacks. The verification section below records the
earlier unified stage; current RAG checks are in that companion receipt. Record
reports remain fresh local queries; the one-conversation interface is unchanged.

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
seven scoped office roles can use it; veterinary accounts receive Animal Health
help/reports only. Visitors, GIS evaluators, guests and farmer-guard sessions are
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
reference. It never retains the original question.

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
receives a canonical general question and static guide/module/role/language.
Help now retrieves at most three reviewed passages rather than the entire static
guide. Strict structured output and server validation check returned citation IDs;
compact application-owned source titles appear in the conversation. Accepted
general help is cached by current evidence/prompt/model/role/topic/language for
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

## Unified verification

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

These checks do not establish live deployment or multi-user/load readiness. The
remaining legacy-schema dependency integration in
[LARAVEL_12_AI_UPGRADE.md](LARAVEL_12_AI_UPGRADE.md) still applies.

## Configuration and deployment

The example remains disabled. Enable on the intended environment only after
its reviewed PHP 8.3+/Laravel 12 dependency installation and acceptance:

```dotenv
ASSISTANT_ENABLED=true
ASSISTANT_MODEL=gpt-4.1-mini-2025-04-14
ASSISTANT_DAILY_REQUESTS=100
ASSISTANT_MONTHLY_REQUESTS=1000
ASSISTANT_USER_DAILY_REQUESTS=20
OPENAI_STORE=false
```

On explicit release instruction, use [GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md):
push reviewed scope, Hostinger fast-forward pull, then install the exact Composer
lockfile. Release conversation/context/resolver, request/controller, provider/
agent, policies/config, audit/veterinary middleware, session/CSRF middleware/kernel,
route-specific concurrency, reviewed knowledge/retrieval/grounding, layout/dialog and both public assets
together. Mirror CSS/JavaScript to both public directories with 0644 permissions.
Stale clients must receive reload guidance instead of the retired raw-help path.

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
