# Agriculture scope and gradual replies

Status: deployed October 25, 2026 in runtime `cdbc85c`; see [the release receipt](OPERATIONAL_RELEASE_2026_10_25.md). The owner corrected the scope to agriculture and related topics only. The proposed unrestricted General questions feature was removed before deployment.

## October 25 guidance refinement (local; not deployed)

This subsequent improvement and the Registry insights redesign were deployed on
October 26 in runtime `6cdf65a`; see [the verified release](ASSISTANT_REGISTRY_RELEASE_2026_10_26.md).
The heading retains the original local checkpoint date; verification below describes
development, with live deployment evidence in the receipt.

The local intent resolver now separates several ordinary farming questions from
software workflows:

- “How do I improve my harvest?” and “How do I increase rice production?” ask
  whether the user wants soil health, crop nutrition or irrigation guidance.
- “Why is my rice yellow?” retrieves reviewed pest-management guidance, which
  explicitly says yellow leaves alone do not diagnose a pest, disease or nutrient
  problem. “How many times should I water rice?” retrieves general irrigation
  principles; it does not invent a watering schedule.
- Multiple recognized farming topics ask which to explain first. Specific dose,
  diagnosis or live-price questions retain their limitation and offer the related
  general topic instead of a generic system-help dead end.
- “Summarize our farmers in our LGU” keeps the authenticated account's scope.
  Named location suffixes still clarify. “How many irrigation assistance releases
  are recorded?” clarifies the unsupported input filter instead of returning all
  assistance releases.

`help_topics` contains at most three unique canonical agriculture keys. It never
stores the question, farmer references or new conversation authority. The
application constructs and role-filters suggested prompts; unrelated/override
requests cannot use this mechanism to create a provider question. Selecting a
suggestion fills an editable draft and does not submit it. The next request is
authorized normally, and previous private context is cleared on these agriculture
clarifications.

The chat welcome includes a collapsed **Explore agriculture topics** disclosure.
Its ten reviewed topics include soil, planting, irrigation, pests and fish farming;
veterinary users receive livestock guidance only. Help replies now distinguish
**Agriculture guide** from **System guide**. A delivery label identifies generated,
cached, reviewed-text or unavailable guidance. These labels describe delivery,
not factual certification. Scope, sources, gradual reveal and read-only local
record reporting are unchanged.

Verification:

- Final complete Assistant PHP run: **1,043 tests / 8,063 assertions passed**.
  Coverage includes new prompt routing, mixed topics, every rendered topic
  shortcut, canonical-only provider inputs, cleared private references, role and
  municipality isolation, unsupported filters, budgets, source checks and failure
  paths. All provider calls were faked or prevented.
- **62 chat JavaScript tests passed**, including safe delivery labels, role-specific
  topic controls, editable drafts, typing, stale replies, retry and source rendering.
- Separate understanding/architecture/composition run: **104 tests / 2,635
  assertions passed**. These overlap the complete Assistant run above.
- Changed PHP passed Pint and syntax checks; the existing POST route was checked,
  Blade compiled in private isolated storage, and JavaScript syntax/whitespace
  checks passed.
- A normal signed-in browser session on the isolated synthetic MariaDB application
  verified keyboard topic expansion, draft preservation, three harvest-improvement
  choices, semantic numbered replies and the correct two-farmer account total.
  Dialog/content/follow-up rows did not overflow a measured 389px viewport.
  Screenshots were unavailable, so this is DOM/keyboard/layout-measurement evidence,
  not complete visual, physical-device or screen-reader acceptance. Fake help
  responses do not establish live-provider answer accuracy.

Release the changed Assistant application classes, shared Assistant partial and
both public CSS/JavaScript assets together. Hostinger's separate `public_html`
needs the same public assets; refresh optimized autoloading and normal view caches.
There is no migration, training upload, dependency/model change, provider-budget
change or operational data write. Do not clear the general cache or alter the
separately pinned demo. Production deployment requires the owner's instruction.

## Scope and privacy

The Assistant explains agriculture, crops, soil, irrigation, pests, livestock and aquaculture through existing reviewed topics. AgriLGU workflows and authorized local office reports remain supported. Unrelated questions are declined locally; unsupported or ambiguous farming questions may need clarification or office advice. No arbitrary general-writing, math, coding or everyday-topic provider path is exposed. Unsupported report subsets still clarify rather than returning broader totals.

There is one conversation, with no General questions selector. The server rejects a mode field, including attempts to submit mode=general directly. GeneralAssistant and GeneralHelpAgent are removed. No raw question, previous reply, farmer record or user transcript reaches OpenAI. Only canonical application-owned help questions and role-eligible reviewed passages enter the existing provider path. Reports remain authorized local reads, without provider history or a model-to-database tool.

Existing authentication, role/scope checks, CSRF, five-per-minute throttle, atomic provider allowances, response storage off, context expiry, audit privacy and no-general-cache-clear rules remain unchanged. Veterinary operational help remains Animal Health-only; visitors, GIS evaluators and farmer sessions remain denied. A refusal or unsupported question does not consume provider allowance. Anchored instruction-override checks run before report or farmer routing without treating literal names as instructions. Explicit mixed off-topic requests and unrelated device/backup questions clarify locally and clear old report/help references. Unsupported physical machinery repairs receive technician guidance instead of software inventory instructions. Keyword routing is finite, not a semantic classification guarantee; evidence-only generation prevents arbitrary-topic inference.

## Answer and typing improvements

Initial workflow questions now honor steps/numbered wording without needing a second turn. Reviewed-help instructions favor direct, simple answers and a focused clarification when evidence is missing. The same evidence/source rules and bounded output remain. This is prompt and response tuning, not model training: there is no trained model, training upload or new provider/model.

Successful replies reveal safe paragraph/list text gradually after the complete validated response arrives. The effect takes roughly 0.9–3.8 seconds and preserves semantic numbered lists, Unicode graphemes and text-only rendering. Show full reply skips the effect. Reduced-motion users and background tabs receive full text immediately; changing the preference completes the reveal. The user's reading position is respected and partial text stays out of live announcements. Reset/navigation cancels timers; closing completes a received reply and cancels a pending browser fetch. This is a client typing effect, not streamed provider tokens.

## Validation and release

Tests cover agriculture acceptance, unrelated/mixed-question refusal, direct general-mode rejection, no provider calls or operational reads on declined questions, first-turn style and unchanged permissions/privacy. JavaScript tests cover typing, skip, reduced motion, Unicode, cancellation, lists and escaping. Browser visual verification remains unavailable because local browser use is disabled in Work settings; no visual acceptance is claimed.

Release changed Assistant intent/provider instructions and both public JS/CSS assets together, and mirror assets to Hostinger public_html. Rebuild optimized autoloading/configuration/routes/views as usual, preserving provider counters and avoiding general-cache clears. No migration, dependency, model, credential, limit or account change is required. The separately pinned demo requires its own reviewed source update while preserving the demo banner, isolation bootstrap and private environment/storage. Deployment needs the owner's explicit instruction.

The three earlier private candidate smoke tests exercised the now-withdrawn General mode and are not validation of this final scope. Candidate code outside the running application was not deployed. Final acceptance counts are recorded after regression completion.

## Completed local checks

- Assistant and module architecture/composition regression: 879 PHP tests, 7,692 assertions, all passed.
- Full JavaScript suite: 337 tests passed, including 52 Assistant tests.
- Declined override/mixed/unrelated questions were checked against prior help, office-report and selected-farmer contexts with no provider request or operational SELECT and cleared context.
- Direct mode=general submissions return validation failure; no General selector or arbitrary-question provider service remains.
- Laravel Pint, changed PHP syntax, Blade compilation, Assistant route listing, JavaScript syntax and whitespace checks passed.
- Browser visual acceptance remains unverified because local browser use is disabled. No production/demo runtime deployment, migration, credential or provider-limit change ran.
