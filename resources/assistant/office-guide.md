# AgriLGU Assistant knowledge curation

The runtime help path retrieves reviewed passages from `knowledge/*.md` through
the explicit `AssistantKnowledge` manifest. This index is not provider context.
The previous whole-guide prompt was replaced by local RAG on October 11, 2026.

The knowledge covers parcel boundaries and their planning-reference limits;
planted crop sections and saving rules; rice release imports and quantity units;
satellite field checks and interpretation limits; multiple-animal recording;
role access; farmer portal access; registry cards; and harvest entry. Veterinary
access/help use separate passages and only eligible Animal Health references.

Keep each passage complete, concise and verified against the current application.
Preserve exact control labels, role limits, maximums and useful failure guidance.
Never add private records, credentials, personal contacts, precise coordinates,
internal deployment paths or unimplemented workflows. Do not automatically ingest
uploads or arbitrary repository files. New sources require a reviewed manifest
entry and retrieval/permission tests; changing the content changes its digest.

`OfficeAssistant` supplies only canonical help questions and approved passages.
Raw chat and records never enter model context. Operational summaries are fresh,
authorized reads through `AssistantRecords`, not generated or cached answers.
The Assistant cannot mutate data, issue credentials, import files, diagnose crops
or animals, prescribe treatment or make official decisions.

See [the RAG architecture](../../docs/ASSISTANT_RAG.md) and
[the unified Assistant contract](../../docs/OFFICE_ASSISTANT.md).
