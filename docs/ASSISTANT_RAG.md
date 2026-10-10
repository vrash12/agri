# Assistant retrieval and grounded help

Status: October 11, 2026 local follow-up to the unified Assistant. Implemented
under the owner's request for RAG and more efficient, useful AI. Not committed,
pushed or deployed. No production operation, schema change, extra dependency,
vector service, uploaded-document ingestion or account change is required.

## What changed

The help path now retrieves reviewed workflow passages before generating a
reply. It no longer sends the whole help guide for every question. The single
conversation also accepts more English/Filipino workflow phrasing and clarifies
separate actionable tasks. Farmer questions and reports continue to use fresh,
authorized database queries inside AgriLGU, without an AI call or cached totals.

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

`AssistantKnowledge` uses an explicit code manifest of 17 curated Markdown
passages under `resources/assistant/knowledge`. It never scans `AGENTS.md`, private
deployment receipts, environment files, uploads or farmer records. The manifest
defines each source's stable ID, title, topics, primary workflow and audience.
Veterinary help/access sources are separate from agriculture-office sources.
Role/topic eligibility is applied before ranking and its term statistics.

The query is an exact application-owned canonical topic, not arbitrary user text.
Raw messages, selected farmers, context tokens, office identities and transcripts
remain absent from provider context. No embeddings, network lookup, external
classifier, arbitrary SQL or autonomous tools are introduced. BM25 and primary
topic metadata suit this small, deliberately reviewed workflow corpus; this is
lexical RAG, not semantic vector search or farmer-data RAG.

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
the pending dependency/Assistant release. Mirror public JavaScript in both served
Hostinger public directories with 0644 permissions. Verify source labels, fresh
records, a generation miss and a cache hit using synthetic data. Keep existing
session/cache namespaces and provider budgets; never clear the general cache.
See [OFFICE_ASSISTANT.md](OFFICE_ASSISTANT.md) and
[GITHUB_DEPLOYMENT.md](GITHUB_DEPLOYMENT.md) for the full release boundary.

## References

The implementation uses the SDK already installed in this repository; the
[Laravel AI SDK structured-output documentation](https://laravel.com/docs/12.x/ai-sdk#structured-output)
describes its schema contract. The combination of retrieval, constrained outputs
and regression evaluations follows the
[OpenAI application-development guidance](https://developers.openai.com/tracks/ai-application-development#phase-1-foundations).
Those sources do not establish this application's accuracy or performance;
local evidence below does.

## Verification

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
accuracy or live deployment. Two real help workflows are a smoke check, not an
accuracy benchmark across all questions. Remaining framework legacy-schema
staging and true mobile-keyboard/200% browser-zoom checks are unchanged.
