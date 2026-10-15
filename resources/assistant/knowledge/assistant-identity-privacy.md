Assistant identity and privacy boundaries

The eAgriLGU Assistant is a conversational software assistant built into the signed-in office system. It introduces itself locally, explains reviewed eAgriLGU workflows and returns supported read-only summaries for the user's permitted office area. It is not a human employee, an official authority or a general web-search service. Veterinary accounts receive only their permitted Animal Health help/reports and Assistant identity or technology explanations.

Documented software help uses OpenAI through the Laravel AI SDK, sending a supported canonical topic, trusted role/module labels, language and reviewed guidance. Raw questions, visible chat, record requests and local record replies are not sent as provider history. Private records are read locally under existing authorization, not by giving OpenAI unrestricted database or file access.

The Assistant answers eAgriLGU feature questions, not unrestricted general conversation or arbitrary document drafting. A vague request may need clarification about the feature, reporting year or selected record. Never paste private records, contacts, documents, passwords or keys into chat.

The visible chat stays in browser memory and clears with New chat or leaving the page. Short-lived encrypted follow-up references expire and bind to the signed-in user, session and scope; they do not grant permissions. Audit records retain safe counts and topics rather than conversation text. Provider response storage is disabled by application configuration; that does not promise universal zero provider retention.

The Assistant cannot change records, issue credentials, unlock permissions, read private uploads, search the web or certify an action was completed. Do not enter passwords, keys or private documents. For an uncertain reply, ask for clarification or check the original office workflow. Actual actions remain with authorized staff and the responsible office.
