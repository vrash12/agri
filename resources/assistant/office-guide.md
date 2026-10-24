# AgriLGU Assistant knowledge curation

The runtime help path retrieves reviewed passages from `knowledge/*.md` through
the explicit `AssistantKnowledge` manifest. This index is not provider context.
The previous whole-guide prompt was replaced by local RAG on October 11, 2026.

The explicit manifest contains 44 reviewed passages for 41 supported help topics, with a 48-entry ceiling. The October 24 local follow-up adds Outside LGU/Delisted references and LGU profile/website guidance. Generated help must cite the primary retrieved passage, including when a cached answer is reused.
Ten agriculture topics add farming basics, soil, rotation, nutrients, seeds,
planting preparation, irrigation, pest management, livestock and aquaculture.
See [agriculture sources and limits](../../docs/ASSISTANT_AGRICULTURE.md).
Coverage includes farmer profiles/workbook imports and incomplete-data review;
parcel boundaries and planning-reference limits; planted crop sections and saving
rules; agriculture/fisheries release entry, rice distribution sheets, assistance
coverage and rice workbook imports; harvest entry; satellite field checks and
interpretation limits; multiple-animal recording; office/account access; farmer
portal access and registry cards; dashboard figures/provider usage; municipality
weather/PAGASA links; cooperatives; machinery; protected Backup Folder files;
audits; municipality geofences; system overview; and AI technology/privacy.
Veterinary operational help uses separate Animal Health passages. Shared
Assistant identity/technology guidance does not expose other operational modules.

Keep each passage complete, concise and verified against the current application.
Preserve exact control labels, role limits, maximums and useful failure guidance.
Never add private records, credentials, personal contacts, precise coordinates,
internal deployment paths or unimplemented workflows. Do not automatically ingest
uploads or arbitrary repository files. New sources require a reviewed manifest
entry and retrieval/permission tests; changing the content changes its digest.

`OfficeAssistant` supplies only canonical help questions and approved passages.
Raw chat and records never enter model context. Operational summaries are fresh,
authorized reads through `AssistantRecords`, not generated or cached answers.
Live coverage includes retained workbook sections, cooperative memberships,
machinery availability/maintenance, seasonal crop counts and LGU profile setup.
Each record reply shows source registers, scope, period, query time and limits.
It does not inspect every private field, attachment, account or climate reading.
Identity/greetings can be answered locally. Current-page questions and bounded
follow-ups reuse a reviewed topic or an independently authorized record reference,
never the full visible thread. Unrelated general chat is outside this scope.
Help references retain only an approved English/Filipino language and style;
language switches regenerate canonical help, never translate private replies.
Recognized unsupported report subsets clarify before queries and clear pending
references rather than silently returning broader totals. Generic seeds/binhi
aliases still name the all-category assistance summary, not a seed-only filter.
The Assistant cannot mutate data, issue credentials, import files, diagnose crops
or animals, prescribe treatment or make official decisions.

Follow-up chips contain application-owned prompts, never model output or private
record values. They fill editable drafts for explicit Send and preserve unfinished
text. Scope/year clarifications retain only an approved pending report reference.
Selected reads reauthorize ownership; whole-office reports clear farmer selection.
Veterinary suggestions remain Animal Health-only. No raw conversation is stored
or added to provider context.

See [the RAG architecture](../../docs/ASSISTANT_RAG.md) and
[the unified Assistant contract](../../docs/OFFICE_ASSISTANT.md).
