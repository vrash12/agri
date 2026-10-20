# Assistant retrieval and grounded help

Local October 20 Assistant update (not deployed): reviewed general agriculture
questions now use ten canonical topics alongside existing system help, with
local unrelated-question refusal. Raw chat and records stay local. General
livestock-care guidance is available to veterinary roles without additional
operational access. Numbered replies use safe semantic lists, including legacy
inline sequences. Corpus: 42 passages / 39 topics, bounded at 48 entries with
unchanged byte/retrieval limits. See [agriculture help](ASSISTANT_AGRICULTURE.md)
for source provenance, limitations, tests and deployment requirements.

October 19 understanding follow-up (deployed in runtime `2a9ab66`): the
manifest now contains **32 reviewed passages / 29 canonical topics**. Existing
satellite passages support focused NDMI, SAVI, EVI and index-choice questions;
the new `climate-readiness` passage explains the implemented El Niño dashboard,
verified assessments, missing evidence and source limitations. This supersedes
the historical 31-passage / 24-topic counts below. All five new topics exclude
veterinary accounts. The existing 32-passage ceiling, 2,400 characters per
passage, corpus byte limits and 7,000-character retrieval bound remain unchanged.
Corpus/topic identity separates cached help without clearing general cache.
Polite normalization and person lookups run locally; raw questions, named
farmers, prior replies and page fields never become retrieval or provider text.
No vector service, autonomous tool, private-document ingestion or fresh data
fetch is added. See [Office Assistant](OFFICE_ASSISTANT.md).

Local workflow follow-up, October 11 (not deployed): deterministic next prompts
use approved reply/reference types rather than model text or record snippets.
Bounded encrypted scope/year hints complete report clarifications with selected
farmer reauthorization. Chips stage editable messages for explicit Send. Reviewed
help describes the flow; the existing corpus digest separates answer caches.
No new retrieval source, private-record RAG or provider disclosure. See
[Office Assistant](OFFICE_ASSISTANT.md).

Local reliability follow-up, October 11 (not deployed): optional `help_language`
retains only `en`/`fil` with the existing topic/style. Language switches regenerate
canonical help without translating the last reply or forwarding private text.
Cache identity already separates languages; reviewed Assistant-help passages now
describe this behavior and report limits. Recognized unsupported filtered reports
clarify before queries/pending-year context instead of returning broader totals.
No new topic, provider tool, classifier, transcript or private-record RAG.
See [Office Assistant](OFFICE_ASSISTANT.md).

Current follow-up status: the October 11 context-aware, AgriLGU-only extension is
deployed on Hostinger in runtime `82a24c4` through GitHub main push and Hostinger
fast-forward pull. Its 31 reviewed passages, 24 canonical topics, local
identity replies and topic/style follow-ups passed the local integrated checks below.
Historical tests and live acceptance below describe the earlier ten-topic release.

Deployed baseline: Hostinger installed runtime `7d36fff` on October 11, following
the owner's request for RAG and more efficient, useful AI. The unified Assistant,
reviewed knowledge and required dependency upgrade were installed through GitHub
push, Hostinger fast-forward pull and locked Composer install. Existing schema,
operational records and accounts were preserved; nine approved AI environment
settings were provisioned privately. No vector service or uploaded-document
ingestion was added. See the [deployment receipt](GITHUB_DEPLOYMENT.md).

## What changed

The help path now retrieves reviewed workflow passages before generating a
reply. It no longer sends the whole help guide for every question. The single
conversation also accepts more English/Filipino workflow phrasing and clarifies
separate actionable tasks. Farmer questions and reports continue to use fresh,
authorized database queries inside AgriLGU, without an AI call or cached totals.

The deployed follow-up adds documented help for dashboard, municipality weather,
cooperatives, machinery, backups, audit, geofences, system overview, AI/privacy,
registry/data quality and assistance management/coverage. English/Filipino
identity, greetings, thanks, capabilities and short technology replies are
application-owned local text, with no inference, allowance or operational query.
The owner's scope is all documented AgriLGU features, not unrelated generic AI.
After help, only an approved topic and format survive for “more”, “shorter”,
“steps” or “example”; a mixed conversation never becomes provider history.

```text
Office authentication / policy / scope / CSRF / throttle
  -> local intent and canonical help question
  -> role + topic filter -> BM25-ranked reviewed passages
  -> verified general-help cache / separate generation lock
  -> atomic allowance -> strict SDK response schema
  -> application validation of answer and retrieved citation IDs
  -> plain-text chat with application-owned source titles
```

## Retrieval boundary

`AssistantKnowledge` uses an explicit code manifest of 31 curated Markdown
passages under `resources/assistant/knowledge`. It never scans `AGENTS.md`, private
deployment receipts, environment files, uploads or farmer records. The manifest
defines each source's stable ID, title, topics, primary workflow and audience.
Veterinary help/access sources are separate from agriculture-office sources.
Role/topic eligibility is applied before ranking and its term statistics.
The 24 topics use reviewed application-owned questions. Veterinary users retain
Animal Health operational limits while identity and AI/privacy explanations are
allowed; no new operational module permission follows from a help passage.

Approved evidence is curated from implemented behavior and loaded only through
the manifest. This is not runtime repository or code-directory ingestion. No
autonomous filesystem access, browsing, uploaded-document processing or general
AI mode is introduced. Unrecognized AgriLGU tasks clarify locally.

The query is an exact application-owned canonical topic, not arbitrary user text.
Raw messages, selected farmers, context tokens, office identities and transcripts
remain absent from provider context. No embeddings, network lookup, external
classifier, arbitrary SQL or autonomous tools are introduced. BM25 and primary
topic metadata suit this small, deliberately reviewed workflow corpus; this is
lexical RAG, not semantic vector search or farmer-data RAG.

The unchanged version-1 encrypted context allows optional `help_topic` and
`help_style` fields: a manifest-backed canonical topic and `plain`, `brief`,
`steps`, `example` or `more`. It remains at most 4 KiB, user/session/scope-bound
and expires after 15 minutes. Old tokens without a help topic remain valid and
require a fresh help answer before a generic follow-up. The controlled style
changes application-owned prompt instructions; the previous answer or typed
follow-up never enters the new generation request. An explicit “this page”
question can use a page-module relevance hint. Existing assistance-page generic
Excel/upload aliases retain seed-import disambiguation, with explicit topics
taking precedence. Neither use grants authorization.
Satellite/NDVI and Assistance coverage have distinct page hints. Greetings and
thanks preserve the prior validated report/help reference for later follow-ups.

At most three whole passages / 7,000 text characters are selected. Each passage
is at most 2,400 characters; the complete corpus is bounded at 80 KiB and 32
manifest entries. Invalid UTF-8, missing/empty/oversized files, symlinks and path
escapes fail closed. Unmanifested files are ignored. Primary workflow evidence
is required; an unrelated safety paragraph alone cannot support an answer.
Content/manifest SHA-256 invalidates the parsed index and answer cache when
knowledge changes. Parsing is reused within a service instance, with content
checks on each retrieval; no persistent index or database migration is needed.

## Generation, validation and failure behavior

`OfficeHelpAgent` uses the installed Laravel AI SDK `HasStructuredOutput` and
`Strict` attribute. Required fields are `answer`, `supported` and `citations`;
citation items are restricted to the retrieved IDs. Its 600-output-token limit,
pinned model, official endpoint, private key and response-storage-off setting
remain. Temperature is zero to reduce variation, without promising deterministic
or correct answers. Evidence is reference data, not instructions or permission to use tools.
Plain, brief, step-by-step, illustrative-example and fuller-detail formats are
controlled instructions within that same supported topic. Examples must stay
hypothetical and source-supported; asking for more detail does not authorize
invented workflow steps or external record analysis.

`AssistantGroundedAnswer` independently checks types, exact keys, answer length,
nonempty unique source membership and unsupported responses. HTML, URLs, common
secret patterns, invented farmer IDs and contact patterns are rejected. Only
accepted general help can enter the shared cache. Source titles are resolved by
the application; model-supplied titles, paths and links are never rendered.

These checks constrain format and provenance selection. A valid citation does
**not** prove every sentence follows from the source. Human review and regression
evaluation remain necessary, especially when workflows change. Do not market
the feature as hallucination-free or claim a confidence probability.

If inference fails or its output is rejected, the reply shows the complete
highest-ranked saved passage with its source, without generating a replacement
or retrying automatically. It is labeled as documented guidance; Filipino
fallback explicitly identifies the English source. Missing verified evidence
returns an honest unavailable-guidance answer without consuming allowance.
Disabled/misconfigured providers and allowance/cache failures retain safe errors.
An uncertain provider attempt retains its allowance reservation.

## Efficiency and simultaneous users

Accepted help is cached for ten minutes by exact role, topic, language, model,
current corpus digest, canonical question and complete prompt/schema identity.
The controlled answer style is part of that identity. A brief reply cannot be
reused for a steps/example request merely because the topic matches.
No user identifier, scope name, chat or operational data enters that value.
Every request authorizes first, retrieves current eligible evidence and
revalidates a hit. Hits report zero new provider tokens and reserve no allowance;
they remain subject to the office endpoint's five-per-minute throttle.

A separate nonblocking lock per help-cache key prevents different users from
generating the same general answer concurrently. The cache is checked again
inside the lock. Contention serves saved guidance; cache reads/lock failures
fail closed before inference. A cache write after a successful, budgeted call
is best-effort and does not discard an already verified reply. Lock release is
in `finally`. Neither chat nor provider work takes operational write locks or
holds a database transaction. Multi-host deployment still requires shared
atomic coordination.

Audit metadata adds delivery (`ai`, `cache`, `guide`, `unavailable`), corpus digest,
retrieved/cited passage counts and evidence character count. It retains actual
available per-call token/latency counts (`provider_input_count` and
`provider_output_count` are integer token counts), never evidence text, answers, queries,
farmer identities or provider errors. These measurements are not billing.

## Maintaining and deploying knowledge

Review each passage against current routes, policies, form labels and constraints.
Use generic examples, no credentials or operational personal data. Update the
manifest for new approved topics/audiences and add retrieval/role/failure tests.
Do not ingest files automatically. `resources/assistant/office-guide.md` is now
the curation index; the allowlisted passages are runtime evidence.

Deploy the retrieval/grounding/provider/orchestration/resolver classes, the
complete knowledge directory, updated JavaScript and documentation together with
the Assistant follow-up. The Laravel 12/AI SDK foundation is already deployed;
this extension adds no dependency requirement. Mirror public JavaScript in both served
Hostinger public directories with 0644 permissions. Verify source labels, fresh
records, a generation miss and a cache hit using synthetic data. Keep existing
session/cache namespaces and provider budgets; never clear the general cache.
See [OFFICE_ASSISTANT.md](OFFICE_ASSISTANT.md) and
[GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md) for the full release boundary.
This later follow-up requires no migration, new dependency, environment setting
or account operation. Include local identity, optional context metadata,
resolver/orchestration/provider/agent changes and all 31 passages together.

## Local follow-up verification — October 11, 2026

The integrated Assistant suite passed 386 PHP tests / 2,493 assertions and all
198 JavaScript tests passed. Coverage includes 24 approved topics, English/
Filipino identity, styles, page hints, old token compatibility, veterinary
exclusions, report-to-help privacy and generation/cache/fallback behavior. Pint,
syntax, Blade compilation, route and whitespace checks passed. Actual local
Laravel/MySQL browser verification used normal synthetic sign-in/CSRF: local
introduction, real-provider dashboard guidance and a shorter same-topic reply
worked. These checks do not replace live deployment acceptance or load testing.

## Context-aware live acceptance — October 11, 2026

Runtime `82a24c4` passed five local identity replies, four help follow-ups,
15 page contexts, all 24 canonical topics / five veterinary topics, seven scoped
role policies and one excluded account, six local reports, two workspace renders
and one existing card render. A real canonical dashboard answer used 784 input /
157 output tokens and one source; its accepted repeat hit used zero new tokens.
All 21 tables / 9,372 pre-existing rows and schemas, private environment values,
application key, session/cache namespaces and NDVI counters were preserved.
No migration, account or environment operation ran; pending farmer-card changes
remain local. These are bounded live CLI/provider smoke checks, not a load test
or semantic-accuracy benchmark. Full release evidence is in
[GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md).

## References

The implementation uses the SDK already installed in this repository; the
[Laravel AI SDK structured-output documentation](https://laravel.com/docs/12.x/ai-sdk#structured-output)
describes its schema contract. The combination of retrieval, constrained outputs
and regression evaluations follows the
[OpenAI application-development guidance](https://developers.openai.com/tracks/ai-application-development#phase-1-foundations).
Those sources do not establish this application's accuracy or performance;
local evidence below does.

## Historical local verification

- **190 focused PHP tests / 1,121 assertions** passed after the final audit-count
  fix. This includes 35 RAG feature cases / 246 assertions, 34 retrieval cases /
  232 assertions, 66 local routing cases / 215 assertions, and existing office,
  conversation and record tests. Fakes cover role/privacy boundaries, source
  rejection, missing evidence, cache identity/content/TTL/revalidation, provider
  failure, limits, lock contention and numeric audit counts.
- The broader access/sign-in/provider-usage/presentation/portal regression passed
  **288 tests / 2,054 assertions** before the final integer audit-field follow-up;
  the final focused run above includes that change and its added test. These
  groups overlap and must not be added together.
- **20 JavaScript tests**, PHP syntax, Pint, Blade compilation, Assistant route
  middleware verification and whitespace checks passed. The pre-existing
  farmer-card patch still passes reverse-apply preservation checks.
- Normal office sign-in and valid-CSRF chat were exercised against the same
  isolated synthetic MySQL application, not a preview adapter. Actual OpenAI
  generation returned useful, source-referenced crop drawing and rice-release
  import guidance. Replies were manually compared with the selected passages.
- With the final code, rice-import help retrieved two passages / **2,029 text
  characters**, citing the appropriate import guide. Actual generation used
  **833 input / 154 output tokens** with **5,490 ms** controller orchestration
  latency. A repeat reused its accepted answer in **38 ms**, with **zero new
  provider tokens**. These are one local miss/hit pair, not a load test, billing
  estimate, total HTTP measurement or Hostinger performance claim.
- A separate retrieval-only benchmark used 300 calls (30 per topic, one service
  instance): **6.408 ms median / 11.166 ms p95** on local Windows PHP 8.3. Evidence
  was **1,047–2,325 characters** versus the prior **8,651-character** whole guide
  (73.1–87.9% fewer evidence characters). This excludes HTTP, database, provider
  and cold-process latency; characters are not tokens or guaranteed cost savings.

Provider fakes establish disclosure/format/cache boundaries, not semantic model
accuracy. The two local real-help workflows are smoke checks, not an accuracy
benchmark across all questions. The additional framework legacy-schema gate
subsequently passed **146 tests / 974 assertions** against a disposable synthetic
MariaDB database. True mobile-keyboard/200% browser-zoom checks remain untested.

## October 11 live acceptance

The deployed runtime `7d36fff` was verified on CLI/web PHP 8.3.35, Laravel 12.69.3
and AI SDK 1.2.0. The private verified backup covered **21 tables / 9,366 rows**;
schema, pre-existing row hashes, application key, session/cache/Redis namespaces
and existing NDVI counters were preserved. Only nine approved AI environment
settings were provisioned. No migration, seeder, account change or general cache
clear ran; pending farmer-card changes remain local.

Read-only live CLI checks passed ten general-help topics, three veterinary-help
topics, seven office-role policies, one excluded account, six report paths,
two page renders and one card render. Actual canonical crop-help generation
returned two sources with **834 input / 173 output tokens**. Its accepted cache
hit used **zero new provider tokens**. Five public HTTPS checks returned 200,
and Assistant CSS/JavaScript matched source in both public directories with
0644 permissions. These checks establish the deployed help/cache path, not a
load test, semantic accuracy benchmark or interactive sign-in test for every
account. Full release and rollback evidence is in
[GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md).
