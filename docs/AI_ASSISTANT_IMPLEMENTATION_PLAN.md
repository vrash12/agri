# AgriLGU Assistant — unified chat implementation plan

Current follow-up: the October 11 context-aware, AgriLGU-only extension is local
and not yet deployed; local integrated verification has passed. It adds local
identity/social/technology/capability replies, 31 reviewed passages for 24
canonical topics and controlled topic-only explanation follow-ups. The owner
clarified that the scope is all documented AgriLGU features, not unrelated
generic AI or autonomous browsing/filesystem tools. No migration, new dependency,
environment setting or account operation is required. The historical deployed
baseline remains `7d36fff` until a separately verified release installs this work.

The owner's subsequent RAG request extends this implemented plan with reviewed
local workflow retrieval, strict structured replies, source validation, a
versioned general-help cache and saved-guide fallbacks. See
[ASSISTANT_RAG.md](ASSISTANT_RAG.md) for the current retrieval contract. Records
stay local and uncached; raw chat never becomes a provider query. The initial
verification stage below predates that follow-up. Hostinger deployed runtime
`7d36fff` on October 11; see [GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md).

Baseline status: unified conversation **deployed and verified on October 11, 2026** under
the owner's release request, through GitHub push and Hostinger fast-forward pull.
The earlier documentation-only plan is now the architecture contract for future
changes. Current runtime and preservation evidence are in the deployment receipt.

The Assistant now uses the signed-in office layout and authenticated endpoint
with one conversation and no help/report or year controls. Normal sign-in, CSRF
and MySQL checks used an isolated synthetic schema. The old localhost sample
preview is a test adapter, not the current integration path or a deployment. See
[OFFICE_ASSISTANT.md](OFFICE_ASSISTANT.md) for the implemented behavior and checks.

## 1. Product outcome and scope

Deliver one conversation inside the real signed-in office system. Staff ask a
question, receive an answer and ask a follow-up in the same thread. They must
not select a help/report mode, module tab, reporting section or year field.
Keep the small Assistant launcher and the current office page accessible when
the conversation closes. Use AgriLGU's own identity and design tokens.

The first release covers existing software help, current farmer/mapping totals,
bounded farmer lookup and summaries, yearly assistance/harvest reports, and
Animal Health summaries. Preserve the existing role and geographic permissions.
This is read-only assistance: no record edits, account issuance, file uploads,
automated exports, diagnoses, prescriptions or official decisions.

The local follow-up extends reviewed feature guidance across dashboard, weather,
cooperatives, machinery, backups, audit, geofences, system overview, farmer
registry/data quality, AI/privacy and assistance management/coverage, preserving
the original ten topics. `AssistantIdentity` handles English/Filipino greetings,
thanks, identity, capabilities and technology from local application text without
a provider call, budget reservation or operational query. Feature explanations
do not imply live data access to every module. Veterinary identity/AI/privacy
help leaves operational access limited to Animal Health.

A continuous conversation does not grant unrestricted database access or consent
to disclose records to an external model. Operational answers must still be
produced inside AgriLGU. General workflow explanations may use the existing
Laravel AI SDK/OpenAI integration with the restricted context below.

## 2. Senior AI and engineering responsibilities

Treat this as a government operations feature, not a demo chatbot. The developer
owns its architecture, AI behavior, privacy, code quality, query efficiency,
concurrency, infrastructure compatibility, tests and release evidence.

- Trace the existing endpoint, policies, report queries and UI before editing.
  Reuse `MunicipalityAccess`, module policies, `AuditTrail`, `AssistantRecords`
  and `AssistantRequestBudget`; do not create parallel ownership rules.
- Keep HTTP validation in the request/controller, routing in a small application
  service, and report calculations in domain support. Add typed intent/context/
  result values only when they establish a useful boundary; avoid a framework
  inside the framework, duplicated report logic or catch-all agent classes.
- Treat model output, user text, browser history and record labels as untrusted
  data. Application code, not a prompt or model confidence, decides permissions,
  queries, provider disclosure and action links.
- Make decisions measurable. Record latency, database queries, payload sizes,
  token usage and failure rates without retaining questions, replies or identities
  in diagnostics. Distinguish proposed targets from measured results.
- Use the installed Laravel AI SDK and current Blade/JavaScript stack. Do not
  add vector storage, another agent framework, WebSockets or a persistent
  worker unless a demonstrated requirement justifies it on the target host.
  The requested first RAG stage uses bounded local workflow retrieval only.
- Maintain synthetic regression and AI evaluation cases. A pleasing reply is
  insufficient evidence of correct totals, isolation or disclosure boundaries.
- Preserve pending farmer-card work, office/farmer guard separation, existing
  session/cache namespaces and provider allowance counters.

## 3. Conversation experience

Use a compact header with the AgriLGU Assistant name, New chat and Close; one
scrollable message stream; and one labeled multiline composer with Send.
There are no persistent mode selectors, report panels, year inputs or module
sections. Optional starting prompts live in the empty conversation and disappear
after the first turn. Small farmer choices or register links belong to the
relevant reply, rather than a separate workspace.

Examples of the intended conversation:

| Staff message | Expected response behavior |
| --- | --- |
| How many farmers do we have? | Current authorized registry/mapping totals; state office scope and that these are current records |
| Show assistance for 2025 | Assistance totals grouped by input/unit, with missing/undated notices and a protected register link |
| What about harvests for the same year? | Reuse the resolved year; show harvest quantities separately by commodity/unit |
| Find farmer Juan | Up to six permitted matches; ask which farmer if ambiguous |
| Show his parcels and seeds received | Reauthorize the selected farmer; summarize saved parcels and that farmer's assistance for the stated year |
| How do I draw a corn area? | Explain the documented crop editor without including the preceding farmer conversation in an AI prompt |
| Show farmers in another province | Check requested scope against current permissions; never widen access based on wording |

Use a year stated in the message first, then an unambiguous prior report year,
then the current year from the application's display-time convention. State the
chosen year in the reply. Registry totals remain current, regardless of year.
Unsupported year comparisons and unclear references receive one short in-chat
clarification. Choosing one person must not imply that every subsequent report
is farmer-specific: distinguish “his assistance” from “our assistance,” and ask
when the referent is unclear. Resolve new topics before reusing an old farmer.

Show scope, period, units and source beside the answer in ordinary language.
Use concise sentences, short lists or a small table when they help; do not render
answers as a dashboard of cards or mandatory report sections. Never invent a
value, silently truncate a breakdown or represent missing data as recorded zero.

Keep loading, cancellation, denied access, empty results, expired session,
validation and provider failure understandable inside the conversation. Preserve
an unsuccessful question for retry; prevent duplicate sends and ignore stale
responses after cancellation/reset. A failed help provider must not block local
record reports. Browser cancellation does not guarantee cancellation upstream.

Retain keyboard focus, Escape/Close focus return, 44px targets, readable contrast,
an accessible message log and reduced motion. Send uses Enter, Shift+Enter inserts
a newline, and Enter during IME composition must not submit. Do not force-scroll
someone reading earlier replies; offer a simple jump to new messages. Confirm
desktop, 389px phone/short-screen and 200% zoom behavior before release.

If a question requests a narrower province/municipality, resolve it to an active
numeric scope and intersect it with `MunicipalityAccess` before every aggregate
or search. State that resolved area in the reply. The current `AssistantRecords`
interface has no explicit scope-narrowing argument: either extend its scoped
query contract deliberately with isolation tests, or clarify that the requested
subset is unavailable. Never silently answer a specific-area question with
totals for the user's entire permitted area. Carry only a validated numeric
scope hint in follow-up context and recheck it against current permissions.

## 4. Server architecture and request contract

Keep `POST /assistant/message` and its office authentication, idle/account-scope
checks, CSRF, policy and five-per-minute throttle. Replace client-selected routing
with server routing; deleting the selectors alone is not a valid implementation.

```text
Office chat -> AssistantMessageRequest -> AssistantController
    -> conversation orchestration service
        -> bounded local intent/year/reference resolution
        -> module policy + MunicipalityAccess + related-record authorization
        -> AssistantRecords -> local report reply
        OR approved static help topic -> OfficeAssistant -> AI reply
    -> bounded response + protected links + safe audit metadata
```

Implemented contract: accept a message (existing 800-character limit), an allowlisted
page-module hint and optional opaque conversation context or reply-choice token.
No client `mode` decides disclosure. Do not accept the visible transcript,
arbitrary SQL, tool descriptions, system messages or user-supplied output links.
Validate all optional fields, lengths, enums and token formats. Page hints help
interpret a question; they never supply authority or silently select a farmer.

Return a bounded answer, server-generated source/scope/period metadata, at most
six reply choices, approved links and the next opaque context. Retain the current
6,000-character local answer ceiling and 3,000-character/600-output-token provider
ceiling. Reject oversized responses safely. Source badges are application-owned;
a browser or model cannot relabel an external answer as a database report.

Start with deterministic, tested English/Filipino intent and reference parsing
for the supported tasks. Resolve workflow questions (“how do I import seeds?”)
before interpreting crop/seed keywords as report requests. Unknown, mixed or
ambiguous requests clarify locally, never fall through to an external classifier.
One bounded task runs per turn; the existing workspace summary is an explicitly
supported composite. Do not implement a general model-to-SQL agent.

The retired two-mode fields now return 422 with explicit reload guidance. Release
backend and both versioned public assets together so an old client cannot bypass
the new provider boundary. No raw-question help path remains in the runtime.

## 5. Follow-up context and privacy boundary

The visible thread remains in browser memory. No database conversation table,
localStorage, sessionStorage or raw transcript in Laravel session/cache/logs is
introduced. New chat, logout, page exit and session expiry clear the thread,
pending requests and context. Close may retain the in-page thread, as today.

For follow-ups, prefer a small versioned, encrypted and authenticated Laravel
context token returned to the browser. Proposed contents are last resolved task,
report year, selected farmer's numeric ID when applicable, limited choice IDs,
issue/expiry times, and bindings to the authenticated office user, current session
and effective scope. It contains no names, totals, record text or provider history.
Use a 15-minute expiry, bounded token size (initial cap 4 KiB), and short-lived
choice tokens. Do not expose session identifiers or the application encryption key.

The local follow-up retains version 1 and the 4 KiB bound. Optional `help_topic`
and `help_style` carry only a canonical topic and `plain`, `brief`, `steps`,
`example` or `more`. Existing tokens stay valid but cannot resolve a generic help
follow-up until a fresh help turn supplies an approved topic. Unknown metadata
never becomes provider history. A request such as “Explain more” after a records
reply clarifies; it does not submit that reply or farmer text to a model.

Tokens are reference hints, never authorization. On every turn verify the binding
and expiry, reload active account scope, authorize the task/selected farmer and
apply scope before querying. Scope changes, tampering and expired context yield
safe clarification/reset; replay by another account/session yields no data.
Do not serialize all allowed municipality IDs into the browser. Recalculate
permissions server-side, including separate city scopes. If safe session binding
cannot be established, ask the user to restate the reference instead of weakening
the context boundary.

The first unified release must not forward raw user text or mixed transcript to
OpenAI. Resolve an allowlisted AgriLGU-help topic locally and build a canonical
question from application-owned topic text plus approved retrieved passages/module/role
labels and controlled language/style instructions. Use no record-derived text or free-form prior messages. An unknown topic
receives local help/clarification. This makes the privacy boundary enforceable
without claiming that pattern matching anonymizes names or personal information.

Keep provider context separate even when help follows a farmer report. Phrases
such as “explain his address,” supplied secrets, unrecognized copied reports and
instructions embedded in farmer names must not become prompts. Additional
provider-assisted analysis of operational data would require a separate reviewed
disclosure design and owner decision; it is outside this first release.

Authorize before every farmer/parcel/crop/release/harvest read. Preserve the current
exclusion of contact, birth, eligibility, credentials, free-text notes and
coordinates. Foreign and nonexistent farmer references must fail identically.
Veterinary accounts receive only Animal Health help/reports; visitors, GIS
evaluators, guests and farmer sessions remain denied. All register links enforce
their existing policies independently.
The later local extension also permits veterinary local identity and AI/privacy
explanations. This is help about the Assistant, not access to another operational
module. Explicit “this page” requests may use an allowlisted module relevance
hint. Existing assistance-page generic Excel/upload aliases retain their reviewed
seed-import disambiguation, with explicit topic words taking precedence. Hints
never grant permissions or silently choose a record.

Render answers as escaped text using the existing safe DOM approach. Generate
links from allowlisted named routes, never provider output. No rich-HTML or remote
image rendering is needed; any future Markdown renderer needs independent XSS
and URL-policy review. Private responses retain security headers and no-store.

## 6. Report correctness and efficiency

Reuse current query rules: scope first, parameterized literal name searches,
numeric ID lookup, at most six matches and ten quantity groups with a partial
notice. Do not load registers or geometry to construct summaries. Related rows
must match the farmer's municipality, even for an owner with broader access.

Assistance uses receipt dates and valid same-municipality beneficiary links;
harvests use the recorded harvest year; animal services use the service year.
Keep units separate, distinguish unique beneficiaries from releases and repeat
services from unique animals, and disclose missing fields. Do not infer yield,
historical registry snapshots or an unimplemented crop-to-harvest relationship.
Counts from several queries are a current reading of changing records, not a
guaranteed transactional snapshot. Label this honestly rather than claiming
point-in-time consistency the implementation does not provide.

Measure scoped queries on disposable synthetic MySQL/MariaDB datasets, including
large owner scope and zero/invalid legacy rows. Use indexed date ranges, purpose-
selected fields and database aggregates. Query count must remain bounded as
matching rows grow; avoid N+1 work during farmer disambiguation and profile reads.
Review query plans before proposing indexes or migrations.

Initial staging targets, not measured achievements: local report p95 at or below
1.5 seconds on documented representative hardware/data, response payload at or
below 32 KiB, and no provider request for any operational report or clarification.
Record actual p50/p95, sample sizes, query count and concurrency. Adjust a target
with evidence; do not hide a slow path behind animation or a longer timeout.

Do not cache identifiable chat answers in shared caches. Cache a proven expensive
aggregate only if its complete authorization scope, report/year and invalidation
or explicit short freshness window are included; return its freshness to users.
Authorization still runs before serving a cached result.

## 7. Concurrency, provider cost and Hostinger infrastructure

The earlier Assistant route sat in `SynchronizeMutatingRequests`. That middleware
treats POST as a mutation, holds a per-account write lock through the response,
and can acquire a farmer or municipality request lock. A slow read-only help call
could block ordinary operational work. Only the exact chat endpoint now excludes
it and uses `SynchronizeAssistantRequests`; operational synchronization is intact.

`StartApplicationSession` preserves the normal session lifecycle for other routes.
An acquired chat commits activity before slow work and skips the late save/cookie
refresh, preventing stale replies from restoring concurrent logout or overwriting
authentication/CSRF/flash updates. Keep this boundary covered by encrypted file-
session regressions. Do not add session mutations inside chat services.

Give this exact read-only endpoint a reviewed Assistant-specific concurrency
boundary: short acquisition, one in-flight Assistant request per office user,
bounded expiry beyond the provider timeout, immediate understandable busy result,
and guaranteed release. Preserve CSRF, sessions, active scope, policy checks,
throttles and audit. Keep operational record mutation locks and database
transactions out of provider waits; do not change the locking used by writes.
Test simultaneous chat and normal record editing for the same and different
users, plus crash/timeout lock expiry and late response rejection.

Keep provider budget reservations atomic and brief, before the outbound request;
never hold their reservation lock while awaiting OpenAI. Retain current UTC
daily/monthly/per-user allowances, 20-second provider timeout and fail-closed cache
behavior. Local reports and clarifications consume no provider allowance. Do not
retry uncertain inference automatically or refund reservations on unknown outcomes.
Request/token counts are usage controls, not provider billing or a currency cap.

The initial transport is the existing bounded JSON POST. Do not require streaming,
SSE, WebSockets or a persistent queue worker on shared hosting. Add streaming only
after validating proxy buffering, worker capacity, disconnect handling, throttling,
allowance accounting and security headers on the actual host. Queue infrastructure
is unnecessary for these short synchronous reads.

Use the installed PHP 8.3+/Laravel 12 foundation and reviewed Composer lockfile.
Keep provider keys private, the approved official provider URL and response
storage disabled. This is not a universal zero-retention guarantee. Use one
atomic cache on the current host; multiple hosts require shared atomic locking
and verified session/storage configuration before activation. Never clear the
general cache or reset NDVI/Assistant counters during installation or rollback.

Record only approved counts, task/year/source, latency, status class and tokens
with existing actor/scope attribution. No prompts, replies, search terms, farmer
names/IDs, context tokens, SQL/bindings, provider bodies or credential-bearing
traces in observability. Review request-body capture in any error-monitoring or
reverse-proxy integration before enabling it.

## 8. Implementation sequence and affected files

Stages 1–7 have local implementation and focused/synthetic evidence in
`OFFICE_ASSISTANT.md`; they do not establish all production/staging integration
requirements. Stage 8 completed on explicit release instruction in runtime `7d36fff`.
The sequence
below records responsibilities for this change and later extensions.

1. Revalidate the working tree and existing tests. Trace routing/provider disclosure
   and reproduce lock contention using synthetic users. Establish query/latency
   baselines on a disposable schema; preserve unrelated card/dependency work.
2. Define typed intent/result/context contracts and the bounded local resolver.
   Reuse `AssistantRecords` for calculations. Add the encrypted reference handling
   and server-generated reply choices; reauthorize every follow-up.
3. Add the provider firewall: canonical allowlisted help only, no transcript or
   raw record-capable message passed to `OfficeAssistant`. Adjust `OfficeHelpAgent`
   and its tests without new operational tools. Update static help only when its
   instructions match implemented behavior.
4. Review the exact Assistant route's concurrency path; add dedicated bounded
   request coordination without weakening other synchronized routes.
5. Replace the dialog mode/year controls with a single message stream/composer.
   Update JavaScript request/history handling, cancellation, source labels,
   protected links, keyboard behavior and responsive CSS together.
6. Verify actual authenticated office integration with synthetic data, valid CSRF
   and a normal session. The localhost test adapter is not the acceptance path.
7. Complete tests, formatting, Blade/route checks, static review, privacy/AI evals
   and representative MySQL/MariaDB checks. Record evidence and update receipts.
8. On a later explicit release instruction, push the reviewed scope to GitHub,
   then pull on Hostinger and install locked dependencies. Verify real served
   assets/backend and rollback readiness before enabling the Assistant.

Primary integration points: `AssistantMessageRequest`, `AssistantController`,
`OfficeAssistant`, `AssistantRecords`, `OfficeHelpAgent`, `AssistantRequestBudget`,
`OfficeAssistantPolicy`, `routes/web.php`, `SynchronizeAssistantRequests`,
`StartApplicationSession`, `VerifyCsrfToken` and the HTTP kernel, shared
office layout, `partials/office-assistant.blade.php`, and both public Assistant
assets. `AssistantConversation`, `AssistantConversationContext` and
`AssistantIntentResolver` implement orchestration, opaque references and local
resolution. `AssistantKnowledge` and `AssistantGroundedAnswer` now provide bounded
RAG and source checks; `resources/assistant/knowledge` is reviewed runtime evidence.
The office guide is its curation index. Keep the passages accurate to the system.
`AssistantIdentity` and the optional help-topic/style references add local
conversation continuity. The 31-passage/24-topic corpus remains explicit and
curated; there is no runtime code-directory, repository, upload or private-file
ingestion. Controlled style/corpus identity stays in the help cache, without
resetting general-cache budgets.

No migration or account/data change is expected for the memory-only conversation
design. Any later schema/index need requires an explicit reversible plan against
the incomplete legacy baseline. Saved chat history, uploads, external analysis
and new roles are separate product/privacy decisions.

## 9. Acceptance evidence before release

| Area | Required checks |
| --- | --- |
| Real integration | Assistant opens on normal authenticated office pages, sends with valid CSRF, retains the office workspace and uses the production endpoint without preview fixtures or automatic test login |
| Continuous UX | No mode/module tabs, year control or separate report sections; farmer lookup, report/help transitions and follow-ups work in one thread; ambiguous references clarify |
| Authorization | Every allowed office role plus all denied guards/roles; own/foreign municipality, province/region and separate-city scope; account deactivation/scope changes during a thread |
| Context | Tampered, expired, oversized, cross-user/session and obsolete-version tokens; stale farmer choices; New chat/logout/page exit; numeric references never grant permission |
| Disclosure | Provider fake spies show zero calls for reports, ambiguity, unknown/mixed requests; report-to-help transitions send only canonical static help; secrets and farmer text never enter prompts/history/logs/audit |
| Injection/output | Prompt-like names/text, forged client source/role/module, unsafe links/HTML, literal wildcard lookup, text-only output and no arbitrary SQL/tools |
| Correctness | Year precedence/default/clarification, current registry vs year reports, mixed units, missing/zero/undated data, invalid linked ownership and repeat-animal semantics |
| Resilience/concurrency | Provider disabled/exhausted/down, safe database failure, busy/expired lock, double-send/cancel/late reply, session expiry and normal record editing while chat waits |
| Performance | Bounded queries and payloads on representative synthetic MySQL/MariaDB data; measured local-report p50/p95 and concurrent-user results; no unbounded registers/geometry or N+1 |
| Accessibility | Keyboard/IME/Enter behavior, message announcements, focus/scroll control, phone/short-screen/200% zoom, reduced motion and empty/long/error conversations |
| Engineering/release | Focused PHP/JavaScript tests, isolated AI evals, changed PHP Pint/syntax, Blade compilation, routes, dependency/platform checks and diff/secret review; public assets mirrored and versioned |

Keep a small versioned synthetic conversation corpus with expected intent,
scope/year/reference resolution, report invariants and provider-call expectation.
Evaluate English/Filipino paraphrases, workflow/report ambiguity, misspellings,
negative cases and multi-turn topic changes. Use exact expected totals and zero
disclosure/permission violations as release gates. Human review checks clarity
without pretending that a model's self-rating validates the feature. Any paid
provider evaluation requires its own authorized non-sensitive scope and budget.

The unified tests, actual office/MySQL checks and measurements are recorded in
[OFFICE_ASSISTANT.md](OFFICE_ASSISTANT.md): 194 combined PHP tests / 1,486 assertions
and 19 JavaScript tests passed. Earlier two-mode counts are historical. These
checks cover context, canonical prompts, conversation, dedicated concurrency and
session races; they are not a full legacy-schema, Hostinger or load-test pass.

The later context-aware follow-up passed 386 PHP tests / 2,493 assertions and all
198 JavaScript tests. They cover local identity/no-provider behavior, all reviewed
topics and five styles, page hints, legacy-token compatibility, veterinary
identity/privacy scope and record-to-help provider separation. Normal synthetic
Laravel/MySQL browser sign-in/CSRF also verified introduction, actual-provider
dashboard guidance and a shorter same-topic follow-up. Keep these results
distinct from historical counts and the live release receipt; this is not a
production load test.

## 10. Release and rollback boundaries

Follow [LARAVEL_12_AI_UPGRADE.md](LARAVEL_12_AI_UPGRADE.md) and
[GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md). A Git pull alone cannot install the
dependencies for a framework/SDK upgrade. That foundation is already deployed in
`7d36fff`; this context-aware follow-up adds no dependency requirement. Preserve
verified PHP CLI/web compatibility, private backups and approved provider
configuration, and repeat disposable integration gates if dependencies change.
Keep the example disabled until verification completes. Deploy backend, request
contract, layout, static help and versioned CSS/JavaScript as one reviewed release;
mirror public assets to both Hostinger public directories with 0644 permissions.

Refresh configuration/route/compiled-view caches only. Preserve all operational
rows, accounts, identifiers, private files, sessions and general-cache budgets.
Never run blanket migrations/seeders or Composer update on Hostinger.

If verification fails, disable the Assistant and refresh configuration, then
restore the recorded compatible runtime/dependencies/assets using the reviewed
rollback procedure. No operational data rollback is part of this design. Do not
claim the live system is enabled until its normal sign-in, permissions, chat
behavior, served assets and data preservation have been checked.
