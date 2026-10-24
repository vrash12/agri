# Agriculture scope and gradual replies

Status: local implementation on October 21, 2026; not pushed or deployed. The owner corrected the scope to agriculture and related topics only. The proposed unrestricted General questions feature was removed before deployment.

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
