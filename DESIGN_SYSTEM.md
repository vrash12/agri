# Agriculture Information System — Interface Design System

**Purpose:** Keep every module recognizable, readable, and efficient for provincial and municipal staff doing daily management work.

**Applies to:** Dashboards, directories, create/edit forms, record pages, maps, imports, exports, dialogs, authentication, and administrative screens.

**Status:** Design standard for new and revised interfaces. This document does not mean all existing screens already meet it. Adopt it incrementally during authorized interface work; changing this document alone does not change application styles or behavior.

**Related guides:** [Green/yellow theme](GREEN_YELLOW_THEME.md) · [Developer guide](AGENTS.md) · [Implemented features](SYSTEM_FEATURES.md)

## 1. Design direction

Build a practical agriculture management system: a stable navigation shell, clear municipality context, readable records, predictable forms, and obvious next actions.

- Use forest green for identity and ordinary primary actions, yellow for restrained accents, white for work surfaces, and an almost-white background with faint green/yellow corner tints. Follow [GREEN_YELLOW_THEME.md](GREEN_YELLOW_THEME.md) for component-specific color treatment.
- Give records and tasks most of the space. Keep decoration secondary.
- Use the same wording, spacing, field treatment, and action placement across modules.
- Keep forms calm: neutral section headers, visible labels, and short instructions.
- Reserve the dark green overview treatment for dashboards or a genuine workspace summary. Ordinary entry forms do not need large banners.
- Use a compact but readable density. Do not shrink text to fit more columns.
- Explain missing information, blocked actions, and failures with an actionable message.
- Show actual system state. Do not invent live indicators, trends, completion percentages, or official verification claims.

Avoid glass effects, neon colors, large decorative gradients, excessive shadows, oversized rounded cards, emoji navigation, and a different visual identity for each module.

## 2. Existing implementation and adoption

Inspect these references before changing an interface:

| Reference | Responsibility |
| --- | --- |
| [Application layout](resources/views/layouts/app.blade.php) | Navigation, Roboto loading, application container, flash messages, session behavior |
| [Shared operations styles](resources/views/partials/operations-ui-styles.blade.php) | Existing `module-*` buttons, panels, forms, filters, tables, and state styles |
| [Dashboard](resources/views/dashboard.blade.php) | Office overview, metric hierarchy, monthly summaries, attention links, and accessible chart figures |
| [Dashboard styles](resources/views/dashboard/partials/styles.blade.php) | Green/yellow palette and responsive dashboard treatment |
| [Machinery form](resources/views/agricultural_machineries/_form.blade.php) | Grouped entry workflow, related-record selection, and maintenance guidance |
| [Record-version partial](resources/views/partials/record-version.blade.php) | Existing optimistic edit-version integration |

The shared module and dashboard styles now use canonical tokens from `partials.design-tokens`. Some legacy module-specific styles remain. Treat the values below as the common contract, not an assertion about every existing screen.

Implementation rules:

1. Reuse existing classes and shared partials before adding components.
2. When a reusable component changes, update its shared definition and check its consumers. Do not copy a new variant into every page.
3. Consolidate semantic tokens in one shared stylesheet during a planned styling change. Avoid introducing a permanent third theme alongside `--module-*` and `--ops-*`.
4. Keep page-specific geometry, map, or layout rules scoped to that module.
5. Do not globally restyle every `input`, `button`, `table`, or `aside` from a page partial.
6. Preserve route names, form field names, JavaScript hooks, policies, CSRF protection, and record-version fields.
7. Adding this guide does not authorize a frontend framework migration or changes to permissions and workflow.

## 3. Color system

Use colors by purpose. Similar-looking colors must not acquire different meanings across modules.

| Purpose | Color | Use |
| --- | --- | --- |
| Page background | `#F8FAF8` | Almost-white workspace; shared 3.5% green / 4.5% yellow corner tints stay behind solid work panels |
| Surface | `#FFFFFF` | Forms, tables, cards, dialogs |
| Subtle surface | `#F5F8F5` | Grouping, read-only areas, table headings |
| Primary text | `#20362C` | Titles, labels, values |
| Secondary text | `#607269` | Instructions, metadata, descriptions |
| Primary green | `#236344` | Main buttons, links, selected states |
| Primary hover | `#18492F` | Hovered primary button |
| Overview background | `#173E2D` | Dashboard overview only |
| Success text / surface | `#236344` / `#EAF4ED` | Saved, available, completed |
| Information text / surface | `#346A8A` / `#EDF4F8` | Neutral assistance and information |
| Warning text / surface | `#906019` / `#FBF2DF` | Pending review, maintenance, incomplete information |
| Danger text / surface | `#A6504A` / `#FAEFED` | Invalid input, destructive actions, failures |
| Panel divider | `#DFE7E1` | Subtle structural separators |
| Control border | `#7A9182` | Visible input boundaries on white surfaces |
| Keyboard focus | `#236344` | Focus outline on light surfaces |
| Inverse focus | `#FACC15` | Focus outline on dark green surfaces |
| Yellow accent | `#FACC15` | Overview primary action, section-number chips, thin brand strips |
| Yellow hover | `#EAB308` | Hovered yellow overview action |
| Soft yellow | `#FFF8D6` | Active navigation with green text and a green indicator |
| Text on yellow | `#20362C` | Readable labels and icons on yellow accents |

Color rules:

- Use dark text on pale status backgrounds; use white text on a sufficiently dark primary button.
- Use dark text on yellow. Yellow text, borders, or focus outlines on white do not provide sufficient contrast; use green for focus on light surfaces.
- Never communicate status using color alone. Include a label such as “Needs maintenance.”
- Use strong control borders where boundaries identify an interactive field; subtle panel dividers are not a substitute.
- Check actual foreground/background pairs, including hover, focus, disabled, and validation states. Project targets are at least 4.5:1 for normal text and 3:1 for meaningful control boundaries and focus indicators.
- Do not claim accessibility compliance based on the palette alone.
- Match existing parcel colors and map status meanings; do not recolor stored parcel data to match the page theme.

Canonical semantic tokens are defined in [the shared design-token partial](resources/views/partials/design-tokens.blade.php). Core examples:

```css
/* Core theme tokens; extend the shared partial rather than copying this block into a page. */
:root {
  --ui-bg: #f8faf8;
  --ui-surface: #ffffff;
  --ui-surface-subtle: #f5f8f5;
  --ui-text: #20362c;
  --ui-text-muted: #607269;
  --ui-primary: #236344;
  --ui-primary-hover: #18492f;
  --ui-brand-dark: #173e2d;
  --ui-accent: #facc15;
  --ui-accent-hover: #eab308;
  --ui-accent-soft: #fff8d6;
  --ui-on-accent: #20362c;
  --ui-border: #dfe7e1;
  --ui-control-border: #7a9182;
  --ui-focus: #236344;
  --ui-focus-inverse: #facc15;
  --ui-danger: #a6504a;
  --ui-warning: #906019;
  --ui-radius-control: 8px;
  --ui-radius-panel: 12px;
}
```

When consolidating, existing `--module-*` and `--ops-*` names can temporarily reference the canonical tokens. Remove redundant literal definitions after checking affected pages.

## 4. Typography

**Primary family:** Roboto, already used by the application shell.

```css
font-family: 'Roboto', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif;
```

Use **400** for body text, **500** for labels and emphasis, and **700** for headings and primary actions. Do not use unsupported intermediate weights such as 750 or 850, or make entire tables bold. Add no new font family for a single module.

| Element | Target size | Weight | Line height |
| --- | --- | --- | --- |
| Page title | 28–32px; 24–28px on phones | 700 | 1.2 |
| Section heading | 18–20px | 700 | 1.3 |
| Panel or dialog heading | 16–18px | 700 | 1.4 |
| Body and instructions | 14px | 400 | 1.5–1.6 |
| Field label | 14px | 500 | 1.4 |
| Input/select/textarea value | 16px | 400 | 1.5 |
| Button label | 14px | 500 or 700 | 1.4 |
| Table body | 14px; 13px for reviewed dense views | 400 or 500 | 1.5 |
| Table heading | 12–13px | 500 | 1.4 |
| Helper, error, metadata, badge | 12px minimum | 400 or 500 | 1.5 |
| Metric value | 28–36px | 500 | 1.15 |
| Optional section eyebrow | 12px | 700 | 1.4 |

Additional rules:

- Use sentence case: “Farm location,” “Record assistance,” “Save changes.”
- Keep abbreviations such as FFRS, RSBSA, and LGU uppercase.
- Reserve uppercase styling for short optional eyebrows. Do not uppercase form labels or paragraphs.
- Use tabular numerals for quantities, money, chart figures, and metrics.
- Use a monospace stack only for identifiers or technical reference values when it improves scanning.
- Preserve complete names in detail views. Truncated table text needs a keyboard-accessible way to read the full value.
- Preserve a usable system-font fallback if the external font cannot load. Font delivery can be consolidated separately.

## 5. Spacing, shape, and density

Use a **4px spacing scale**: `4, 8, 12, 16, 24, 32, 48`.

| Relationship | Default |
| --- | --- |
| Label to input | 8px |
| Input to helper/error | 4–8px |
| Adjacent form fields | 16px |
| Related button gap | 8px |
| Form section separation | 24px |
| Panel padding | 24px desktop; 16px phone |
| Page side padding | 24px desktop; 12–16px phone |
| Input, button, select corner | 8px radius |
| Panel or form section corner | 12px radius |
| Dashboard overview corner | 16px radius |
| Standard control height | 44px minimum |
| Textarea | 112px minimum height; vertically resizable |

Use a one-pixel panel border and little or no shadow. Elevated menus and dialogs may use `0 8px 24px rgba(20, 40, 27, .10)`. Avoid nested cards where a heading and divider would group the information more clearly.

## 6. Page layouts

### Record directory

```text
Page title + one-line description                 Add record
Municipality scope / workspace
Relevant summary totals, with time period stated
Search + common filters                 Advanced filters
Active filter summary + clear filters
Records table or mobile record list
Result count + pagination
```

Place filters immediately above the records they affect. Label separately scoped map and registry controls. Use one primary action; import/export and other tools are secondary.

### Create or edit form

```text
Back to [module]
Create/Edit [record] + short purpose
Municipality context and required-field explanation

1. Ownership and related records
2. Main record details
3. Service, quantity, or operational details
4. Additional notes and attachments, if applicable

Cancel                                           Save [record]
```

- Use a centered form content width of approximately 960–1120px, with a main entry column normally no wider than 800px.
- If useful, add a 280–320px summary/help column on sufficiently wide screens. It must reflect actual field values.
- Default to two field columns in a wide main column and one on phones. Use three only for short related values with readable labels.
- Give names, addresses, notes, file uploads, and lookup results enough width.
- Keep the main form immediately reachable. Avoid decorative headers taller than the useful entry area.
- Use numbered sections only for a genuine sequence. A short form needs simple headings.
- Use one form partial for matching create and edit screens. Preserve differences in actions and permissions.
- A sticky action bar is optional for long forms; it must not obscure fields, messages, or the mobile keyboard.

### Record details

Show identity and municipality first, followed by grouped label/value pairs, related records, and authorized actions. Present read-only data as text rather than a page of disabled inputs. Keep sensitive actions visually separated from normal work.

### Dashboard

Use the current dashboard as a visual reference, then apply the simpler default in Section 15: office context, a few role-relevant actions and metrics, attention items, and recent activity. Make detailed charts and comparisons available through clearly labeled disclosures or report links. Super Admin receives a clear entry point to municipality comparison. Do not mix all-time counts into a monthly summary or imply live data when the page only refreshes on load.

## 7. Form controls and validation

### Field anatomy

Every field follows this order:

1. Persistent visible label associated with the input.
2. Input with the correct type and preserved value.
3. Short helper text when needed.
4. Specific error message when invalid.

Use placeholders for examples only. Explain that `*` means required once near the form heading, and expose required status through native semantics. Mark optional fields when that distinction reduces uncertainty.

| Control | Rules |
| --- | --- |
| Name/address | Text input; preserve punctuation and user-entered spelling |
| FFRS/RSBSA/asset identifier | Text, not number; preserve leading zeros |
| Phone | `type="tel"`; do not treat a phone number as a quantity |
| Quantity/area | Visible unit; decimal precision, min/max, and step matching server validation |
| Date | Labeled date input; display historical timestamps through the existing local-time helper |
| Small choice set | Native select or labeled radio group |
| Large farmer/holder lookup | Existing searchable control with bounded results and municipality scope |
| Boolean flags | Labeled checkboxes grouped in a fieldset with a legend |
| Notes | Textarea with useful length guidance; never a one-line input |
| File | Native chooser plus optional drop zone; show supported formats and actual limits |

When municipality or holder type changes, clear incompatible dependent selections, explain the reset, and show loading/empty/failure states for the new lookup. Do not allow an old hidden selection to appear valid for the new municipality.

### State behavior

| State | Required presentation |
| --- | --- |
| Default | Readable label and value, visible boundary |
| Focus | Distinct outline; no layout jump |
| Invalid | Error border, specific text, and `aria-invalid` |
| Read-only | Readable value with reason when relevant |
| Disabled | Visually subdued, clear reason nearby; never the only source of important information |
| Lookup loading | “Loading farmers…” or similarly specific status |
| Lookup empty | “No farmers found in this municipality” plus an authorized next step |
| Lookup failure | Explain failure and provide Retry; preserve unrelated entries |
| Submitting | “Saving…”; prevent duplicate submission and announce status |
| Success | Confirm what was saved after the server confirms persistence |
| Stale edit | Explain that another staff member changed the record; offer review/reload without silently overwriting |

On server validation failure, retain submitted values using `old()`. Show an error summary near the top with links to invalid fields; focus the summary or first invalid field. Link field errors and helper text with `aria-describedby`.

Do not persist sensitive unfinished form data to browser storage by default. A completion meter describes required-field completion, not server acceptance. Do not disable Save solely because a speculative client-side completeness check says a field is missing.

### Blade field example

This is a presentation pattern for the existing form classes. The controller/Form Request remains responsible for validation, and the surrounding form remains responsible for its action and CSRF token.

```blade
<div class="module-form-field module-form-field-full">
  <label for="farm_location">Farm location</label>
  <input
    class="module-input"
    id="farm_location"
    name="farm_location"
    type="text"
    value="{{ old('farm_location', $record?->farm_location ?? '') }}"
    aria-describedby="farm_location_hint{{ $errors->has('farm_location') ? ' farm_location_error' : '' }}"
    @if($errors->has('farm_location')) aria-invalid="true" @endif
  >
  <div class="module-hint" id="farm_location_hint">
    Enter the barangay and location details used by your office.
  </div>
  @error('farm_location')
    <div class="module-hint module-required" id="farm_location_error">
      {{ $message }}
    </div>
  @enderror
</div>
```

Define `$record` as the edited record or `null` in the form partial, as existing forms do. Add required/length constraints only when they match the module's validation. Keep `@csrf`, the correct `@method(...)`, and the existing `partials.record-version` include on applicable edit forms.

## 8. Buttons, icons, and actions

| Variant | Treatment | Examples |
| --- | --- | --- |
| Primary | Green fill, white text | Save farmer, Record assistance |
| Secondary | White surface, visible border, dark text | Cancel, Import, Export |
| Tertiary | Text link with clear focus | View records, Back to farmers |
| Destructive | Red text/border; red fill in the final confirmation when needed | Delete record |
| Overview primary | Pale gold with dark text, only on dark overview | Main dashboard action |

- Give each action group one clear primary action.
- In desktop form footers, place Cancel before Save and align the group consistently to the end. On phones, full-width controls may stack; keep visual and keyboard order consistent.
- Use specific verbs. Prefer “Save machinery” over “Submit” and “Export CSV” over “Export data.”
- Use anchors for navigation and buttons for actions. Specify `type="button"` for non-submit buttons inside forms.
- Use consistent outline SVG icons, approximately 18px with a 1.5–2px stroke. Use 20px in navigation where needed.
- Decorative icons are `aria-hidden`. Icon-only controls need an accessible name and a visible tooltip on keyboard focus as well as hover.
- Use at least 44px touch targets for primary controls and mobile actions. Dense desktop actions may have smaller visuals inside adequately spaced hit areas.
- Keep a text action visible when an icon alone would be ambiguous to staff.

## 9. Tables, filters, and reporting

- Give every table an accessible name through a caption or heading association.
- Use semantic header cells and identify numeric columns consistently.
- Left-align names and descriptions; right-align numeric values and units.
- Use locale-aware numeric formatting and consistent precision within a column. Keep `kg`, `ha`, pieces, and currency explicit.
- Preserve valid zero values. Use “Not recorded” or “Not assigned” for missing data; do not replace zero with a dash.
- Show dates unambiguously, such as `Sep 7, 2026`, with a stated timezone where time matters.
- Keep the most important identity column first and actions last. Move rarely used details into a detail view or disclosure.
- Use subtle row hover; keyboard focus must remain visible. Do not make a whole row clickable when it contains conflicting controls.
- Make horizontal scrolling local to the table container. On small screens, consider a labeled record-card representation for frequent staff tasks.
- Keep sticky headings inside the scroll container; they must not cover focused elements.
- Show result counts, active filters, and pagination. Search, totals, and exports must use the intended server scope.
- Keep sorting visibly indicated; use `aria-sort` on sortable headings when sorting is implemented there.
- Use filter submission for costly listings; debounce bounded remote lookups. Do not cause a full request on every keystroke without a reason.
- Empty result: “No releases match these filters” with Clear filters. Empty module: “No assistance recorded yet” with an authorized create action.
- Large report jobs need truthful progress and a failure state when supported. Do not draw a fake percentage bar.

## 10. Dialogs, notices, and asynchronous states

- Use inline forms or full pages for complex entry. Use a dialog for a short, focused decision or small edit.
- Dialogs need a title, an accessible close action, contained keyboard focus, and focus restoration to the trigger.
- Use `role="dialog"`, `aria-modal="true"`, and a heading association when implementing a custom modal.
- Escape normally closes a dismissible dialog. If dismissing would lose meaningful edits, handle that explicitly.
- Destructive confirmation identifies the record and consequences, with a safe Cancel action. Do not imply that a blocked deletion can proceed.
- Use inline warnings for persistent conditions and page-level success messages for completed operations.
- Keep errors visible until resolved or dismissed. Do not use a brief toast as the only explanation of a failed save.
- Use `aria-live="polite"` or `role="status"` for asynchronous progress. Reserve urgent announcements for relevant errors.
- Offer retry only when the operation can be retried safely; after an uncertain save, verify the result before creating a duplicate.

## 11. Responsive behavior and accessibility

The shared shell currently switches to the mobile drawer at **900px**. Preserve that contract when changing individual pages. Existing modules have additional breakpoints; consolidate them deliberately rather than changing shared navigation from a page partial.

| Available viewport | Target behavior |
| --- | --- |
| Up to 600px | One-column entry forms, wrapped toolbars, local table scrolling, visible action labels |
| 601–900px | Mobile navigation; two form columns only where labels and controls remain readable |
| 901–1200px | Account for sidebar width; collapse form summary columns when the main form becomes narrow |
| Above 1200px | Main form plus optional summary, or wider management tables |

Use available content width to decide layout. A 1024px viewport with a sidebar is not a 1024px form.

- Test at 320, 390, 768, 1024, and 1440px, with navigation expanded and collapsed where supported.
- Test browser zoom at 200%; labels, errors, and actions must remain available.
- Use semantic heading order and one main page landmark. Do not nest a second `<main>` inside the application layout's main element.
- Preserve DOM order when rearranging content so keyboard and visual order agree.
- Do not remove focus outlines, block browser zoom, or rely on hover-only interaction.
- Provide appropriate autocomplete hints for identity/contact fields where their meaning is clear; avoid automatically filling unrelated owner or holder fields.
- Support reduced motion. Keep optional transitions around 150–200ms and avoid animation of primary task content.
- Charts need accessible numeric figures and an understandable library/provider failure state.
- Long names, translated labels, large totals, empty datasets, and validation messages must not break the layout.

## 12. Municipality, role, and domain rules

Design changes must preserve the system's authorization and business rules described in [AGENTS.md](AGENTS.md).

- State the active municipality near the page title or form ownership section.
- Municipal users see their assigned office as context; the server derives ownership. A hidden input is not authorization.
- Provincial users select a municipality before dependent farmer/holder choices where the workflow requires it.
- Use policies and existing role helpers for available actions. Do not infer record access from visual state.
- Super Admin retains operational read-only oversight and geofence/account/audit management; no Backup Folder access.
- Provincial Veterinary Office accounts remain restricted to Animal Health.
- Related farmer, cooperative, machinery, and assistance choices must belong to the same municipality.
- Keep public QR pages limited to their reviewed public fields. Do not reuse an internal record card without checking its data exposure.
- Retain private photo/document delivery through authorized routes.
- Agricultural language remains consistent: farmer, parcel, assistance release, machinery, animal-health service, municipality, cooperative.
- Fingerlings are counted in pieces. Never add them to kilogram totals.
- Distinguish “recorded,” “mapped,” and “verified.” A drawn parcel or reference geofence is not proof of surveyed ownership.
- Identify system weather guidance as advisory; do not style it as an official PAGASA warning.

## 13. Reusable component contract

Reuse or extend these existing component groups during implementation:

| Concern | Existing class/partial family |
| --- | --- |
| Page structure | `module-page`, `module-header`, `module-actions` |
| Buttons | `module-button`, primary/secondary treatment, danger modifier |
| Panels | `module-panel`, `module-panel-head` |
| Forms | `module-form-shell`, `module-form-section`, `module-form-grid`, `module-form-field` |
| Fields | `module-input`, `module-hint`, `module-required` |
| Feedback | `module-alert`, `module-alert-error` |
| Tables | `module-table`, `module-table-scroll`, `module-row-actions` |
| State badges | `module-badge` and semantic modifiers |
| Shared editing protection | `partials.record-version` |

This table describes existing building blocks, not a requirement to retain their current small font sizes. If converting a repeated pattern into a Blade component, preserve labels, slot content, validation state, classes, IDs, and form attributes. Create a component when reuse justifies it; do not add a component library for a single field.

Suggested consolidation order:

1. Shared font sizes, colors, control heights, and focus states.
2. Shared field, button, error, and form-footer treatments.
3. Create/edit forms across modules.
4. Filters, tables, pagination, and record detail views.
5. Dashboard alignment and specialized map/import surfaces.

Update the feature catalog only when implemented behavior changes. Document an intentional design exception here when it becomes a reusable rule.

## 14. UX for everyday users

Use standard management-system interactions for everyday users. Keep tasks straightforward and offer concise help when needed, without assuming users lack technical skills. Validate language and interaction choices with representative users rather than assuming everyone has the same needs.

### Familiar language and examples

- Use short, plain-English labels with familiar office terms: “Farmer records,” “Barangay,” “Assistance received,” and “Number of animals.”
- Keep labels, instructions, and errors in plain English. Do not add bilingual explanations unless separately requested. Keep each action's label consistent across modules and explain unfamiliar actions in simple terms.
- Explain unfamiliar abbreviations on first use. Use the office's approved terminology for FFRS/RSBSA and other official identifiers.
- Keep database and technical terms out of normal workflows: use “Choose a farmer” instead of “Select entity” and “Another staff member updated this record” instead of “Version conflict.”
- Show clearly fictional local examples beside difficult fields, such as “Barangay Sample,” “50 kg,” or “1.25 ha.” Keep quantity and unit together. Never use real farmer details as examples.
- Use unambiguous dates such as “Sep 7, 2026”; show the local timezone when time matters.

### Make the next step obvious

- Organize navigation around staff tasks: register a farmer, record assistance, map a parcel, or review machinery. Pair icons with visible words.
- Start each page with a short purpose and a clear main action. Keep Back, Cancel, Search, and Save in predictable places.
- Break long entry forms into a few meaningful sections in the order staff gather information. Reveal specialist options under “More details” while keeping required fields visible.
- For dependent selections, explain the sequence: “Choose a municipality first, then select a farmer.” Municipal users should see their assigned municipality without having to select it again.
- Suggest existing farmers, owners, and barangays where the workflow supports it. Let staff check identifying details before selecting a match; do not silently select a person or create a duplicate.
- Put a short example or instruction beside the control that needs it. Do not require users to remember a tutorial or discover a hover tooltip.
- Use a brief review of the farmer, municipality, item, quantity, and date before finalizing a complex release or import. Routine small edits do not need repeated confirmation dialogs.
- For parcel drawing, show numbered instructions, a visible cancel/undo option where supported, and the measured area before saving. Explain that map boundaries may require field verification.

### Reassurance and recovery

| Situation | Preferred message or behavior |
| --- | --- |
| Required farmer missing | “Choose a farmer before saving this assistance.” |
| Invalid quantity | “Enter a quantity greater than zero. Example: 50 kg.” Only use this rule where server validation requires it. |
| Saved successfully | “Assistance saved.” Offer “View record” and, where supported, “Record another assistance.” |
| Slow connection | “Still saving. Please wait before trying again.” Show this only while the request remains pending. |
| Save result uncertain | “We could not confirm whether this was saved. Check the records before submitting again.” Provide a safe way to check. |
| Record changed by another user | “Another staff member updated this record. Review the latest version before saving.” Preserve recoverable entries where supported. |
| No search matches | “No farmers match your search. Try the surname or check the selected municipality.” |

- Avoid blaming language, unexplained codes, and generic “Something went wrong” messages without a next step.
- Preserve entered values after validation errors. Do not silently clear a form, automatically navigate away, or imply that unsaved work is protected.
- Expect intermittent connectivity and ordinary office computers. Keep pages lightweight, show progress truthfully, and make essential instructions available without map or chart providers.
- Do not promise offline saving or automatic drafts unless implemented securely. Avoid storing sensitive unfinished records in browser storage by default.
- Keep the existing idle-session warning understandable and accessible; explain that signing out can lose unsaved entries. Do not extend the security timeout merely to hide a usability problem.
- Provide a short module help guide and an approved office support contact when available. Never invent contact information.

### Check with real staff

Before rolling a substantial workflow out, ask representative agriculturists and municipal staff, including less experienced users, to find a farmer, record assistance, correct an error, and return to their list. Observe whether they can finish without coaching, where they hesitate, and whether they know a save succeeded. Use synthetic records for these sessions and revise confusing labels or steps before adding more instructions.

## 15. System-wide simplicity standard

**User feedback:** The overall system feels too complex. Reduce the number of decisions and controls presented at once across every module. Better colors and spacing alone do not solve this problem.

**Design goal:** A user opening a page should understand what it is for, find the main action, complete the common task, and know the outcome without needing to understand the entire system.

These are targets for future interface changes, not a claim that the current application already implements them. Where earlier examples suggest showing more information by default, this section takes precedence on information density. Apply changes within the authorized implementation scope and preserve existing capabilities and server safeguards.

### Default to the common task

- Give each page one main purpose and one visually dominant action. Keep other actions available but secondary.
- Show essential information first. Put infrequently used controls in clearly named sections such as “More filters,” “Additional details,” or “Reports.”
- Keep required inputs, errors, active filters, municipality context, and consequential warnings visible. Simplicity must not conceal information needed to complete a task safely.
- Use recognition over memorization: visible action labels, familiar names, selectable records, and consistent placement.
- Remove repeated metrics, duplicated instructions, and multiple buttons leading to the same task within one viewport.
- Avoid nested menus and disclosures. Prefer one clearly labeled expansion to several levels of “More.”
- Keep plain English throughout. Do not add bilingual explanations or assume that users lack technical skills.
- Do not hide an unclear interface behind a long tutorial. Simplify the labels and steps first; provide brief help where needed.

### Simpler defaults across all screens

The following counts are starting limits for the initial view, not reasons to remove necessary fields or permissions.

| Area | Default experience | Secondary information and controls |
| --- | --- | --- |
| Dashboard | About four useful metrics, up to three common actions, a short attention list, and five recent records | Detailed charts, historical analysis, and municipality comparisons via labeled links/disclosures |
| Navigation | Stable, task-based module names, visible labels, current-page highlight | Group less-used administrative tools by role; avoid an undifferentiated long menu |
| Farmer directory | Search, municipality context, Add farmer when authorized, and essential record columns | Quality filters, charts, imports, and detailed classification fields |
| Create/edit forms | Required and frequently used fields, grouped in a natural entry order | Optional notes and specialist fields under meaningful section labels |
| Assistance | Choose farmer, choose assistance, enter quantity/unit/date, then save; choose municipality first where required | NRP and category-specific details appear when applicable |
| Animal Health | Municipality where required, owner/raiser, animal or group, service, count, and date | Breed, dosage, diagnosis, follow-up, and other details according to service and validation needs |
| Cooperatives | Find a cooperative, view basic details, and open member management | Full membership lists and exports in their relevant section |
| Machinery | Search, operational status, holder, and next action | Acquisition details, serial numbers, full maintenance notes, and advanced filters |
| Parcel map | Choose workspace/farmer, view parcels, and a clear map action | Import, export, styling, weather, and advanced boundary controls in labeled tools |
| Municipality boundaries | Municipality selection, active-boundary status, and review results | Drawing/import/edit tools only for authorized users; history in a separate section |
| Backup Folder | Find, upload, preview, or download a file | Hashes, MIME types, advanced search modes, and editing tools when applicable |
| Accounts | User identity, role, municipality, status, and permitted management action | Password reset and other sensitive actions in clearly labeled sections |
| Audit trail | Recent events, search, and a small set of common filters | Detailed metadata and before/after values in the event view |
| Public QR page | Farmer registry summary, parcels, and clear map controls | No internal workflows or confidential record details |

Do not move a field into an optional section merely because it is listed as secondary above. Existing validation and the selected record/service determine which fields are necessary.

### Dashboard simplification

For operational staff, start with:

1. **Common actions:** Add farmer, Record assistance, Open parcel map.
2. **Key figures:** Registered farmers, mapping coverage, assistance releases this month, machinery available. State each figure's period and unit.
3. **Needs attention:** A short list linking directly to the relevant filtered records.
4. **Recent activity:** A small record list with a View all link.
5. **Reports:** Clearly labeled access to detailed charts and comparisons.

Adjust this selection to the role. Super Admin's actions focus on municipality oversight, accounts, and audit review, not operational entry. Provincial Veterinary Office retains its Animal Health entry point and must not gain an agriculture dashboard through this design change.

Keep additional module totals reachable; do not present every available statistic simply because it exists. A zero-state should help the user start the next permitted task rather than fill the screen with empty charts.

### Forms without unnecessary steps

- Keep short forms on one page. Use guided steps only when they reduce real complexity; splitting every two fields into another screen creates extra work.
- Ask for ownership and related records before dependent information. Reuse verified existing data instead of asking users to type it again.
- Reveal category-specific fields when applicable. Changing the category must handle existing values explicitly and must not silently discard saved information.
- Automatically open a collapsed section containing a validation error. Focus the error summary or affected input and retain other entered values.
- Keep a visible summary of the selected farmer/owner and municipality while recording a service or release.
- Offer one clear Save action, a predictable Cancel/Back action, and a specific success message. Do not add routine confirmation dialogs to every save.
- Confirm destructive or consequential actions with the affected record and the consequence. Avoid unexplained “Are you sure?” dialogs.

### Tables, maps, and reports

- Aim for about five or six essential table columns in the initial directory view. Choose them by task: identity, relevant date, status or quantity, and action. Keep full details accessible.
- Show Search and only a few common filters first. Active advanced filters must remain visible in a summary even when their controls are collapsed.
- Preserve filters and the user's place when returning from a record where the workflow supports it.
- Give directory work and parcel work distinct, clearly labeled views or workspace entry points. Users should not have to operate the map to find or edit a farmer record. Preserve existing map links and workspace scope when reorganizing the interface.
- Expose map editing tools after a farmer or parcel is selected. Use a short instruction for the current mode and an obvious way to exit it.
- Expand a record's details or open a detail page instead of loading every relationship into the listing.
- Keep reports scoped, labeled, and available on demand. Do not automatically load heavy charts, map geometry, or exports just because a section is collapsed visually.

### Evaluate whether it is actually simpler

Use representative users and synthetic records to compare the existing and revised workflows. Ask users to find a record, create a routine entry, correct a validation error, review details, and return to their list.

Record task completion, time, wrong turns, repeated clicks, requests for help, and whether users recognize a successful save. A reduction in visible controls is useful only if tasks become easier to finish and important functions remain discoverable. Check both first-time use and repeated daily entry; do not trade fewer choices for excessive clicking.

## 16. Interface review checklist

Before completing an interface change:

- [ ] Page title, municipality context, and primary action are clear.
- [ ] The initial view prioritizes the common task; secondary controls are discoverable without crowding the page.
- [ ] Required fields, validation errors, active filters, and consequential warnings are not concealed by simplification.
- [ ] Duplicate metrics, redundant instructions, and unnecessary steps have been removed within the requested scope.
- [ ] Typography follows the scale; labels, errors, and table text remain readable.
- [ ] A first-time staff user can identify the next action; unfamiliar terms have short explanations and appropriate local examples.
- [ ] Save outcomes, connection failures, and correction steps are understandable; substantial workflow changes have been checked with representative staff.
- [ ] Shared semantic colors and component styles are reused.
- [ ] Spacing, corners, and control heights are consistent with adjacent modules.
- [ ] Required/optional fields and units match server validation.
- [ ] Submitted values survive validation failure; field errors have associations.
- [ ] Loading, empty, success, warning, disabled, and failure states exist where applicable.
- [ ] Concurrent-edit conflicts and duplicate-submit prevention remain intact.
- [ ] Role-specific actions and municipality scope are preserved on the server.
- [ ] Keyboard navigation, focus visibility, modal behavior, and reading order work.
- [ ] Phone, tablet, desktop, zoom, long-content, and empty-data cases have been checked.
- [ ] Charts/providers can fail without hiding essential information or blocking the page.
- [ ] Totals, dates, time periods, and measurement units are accurate and labeled.
- [ ] No new secrets, real personal data in fixtures, duplicate CSS themes, or unrelated changes were introduced.
- [ ] Changed views compile; relevant tests and `git diff --check` pass.
- [ ] PHP changes are formatted with Pint; visual checks use safe synthetic data when possible.
- [ ] Documentation describes implemented behavior separately from future design targets.

## 17. Reusable implementation brief

Use this brief when assigning interface work:

> Follow `DESIGN_SYSTEM.md`, `GREEN_YELLOW_THEME.md`, `AGENTS.md`, and the applicable section of `SYSTEM_FEATURES.md`. Build a consistent agriculture management interface using Roboto, shared green/yellow accents on neutral work surfaces, readable forms, predictable actions, and responsive records. Apply the system-wide simplicity standard: prioritize the common task, reduce initial choices, keep secondary features discoverable, and avoid unnecessary steps. Support everyday users through plain English, relevant examples, and clear save and recovery messages. Reuse the existing layout and component families. Preserve policies, municipality scope, validation, CSRF protection, record-version handling, and existing route contracts. Implement only the requested workflow, check its meaningful states and supported screen sizes, and report what was verified and any remaining limitations.

## 18. Implementation milestones

**Objective:** Apply the consistent design and simplicity standards across the system while preserving working features, municipality isolation, and role permissions.

**Implementation status — September 7, 2026:** The shared foundation and a coordinated simplification pass across the main modules are implemented locally. [DESIGN_IMPLEMENTATION.md](DESIGN_IMPLEMENTATION.md) records the changed workflows, verification evidence, remaining payload limits, and staging/staff checks. Local presentation checks do not establish production readiness or measured improvement in staff task completion. Nothing has been deployed.

### Milestone tracker

| Milestone | Outcome | Depends on | Status |
| --- | --- | --- | --- |
| M1 — Shared foundation | One reusable set of readable components and a baseline for comparison | None | In progress; components implemented, staff baseline pending |
| M2 — Navigation and dashboard | A simpler starting point for each role | M1 | Ready for review locally |
| M3 — Farmer registry and forms | Find, register, and update farmers with less visual clutter | M1, M2 | Ready for review locally |
| M4 — Assistance and Animal Health | Straightforward daily service entry | M3 | Ready for review locally |
| M5 — Cooperatives and machinery | Clear membership, assignment, and maintenance workflows | M3 | Ready for review locally |
| M6 — Maps and geofences | Focused parcel work and understandable boundary tools | M3 | In progress; UI implemented, payload and live-map checks pending |
| M7 — Files, administration, and reports | Consistent supporting workflows with advanced details available on demand | M4, M5, M6 | Ready for review locally |
| M8 — Usability verification and release readiness | Evidence that the whole system is simpler and ready for a controlled release | M1–M7 | In progress; local checks completed, staff/staging review pending |

Update status to **In progress**, **Ready for review**, or **Complete** as work advances. Mark a milestone complete only when its deliverables and acceptance checks pass. Record unresolved items explicitly; a screenshot alone is not completion evidence.

### M1 — Shared foundation

**Deliverables**

- [x] Inventory the screens, shared styles, recurring controls, and main tasks for every role; record where users encounter excessive choices.
- [ ] Capture safe before screenshots and baseline task observations using synthetic records.
- [x] Consolidate colors, Roboto sizes/weights, spacing, control heights, focus indicators, and responsive rules in shared styles.
- [x] Standardize buttons, form fields, helpers/errors, panels, badges, table controls, and form footers.
- [x] Apply the components to one representative create/edit form and directory before spreading them across modules.

**Acceptance:** The reference screens remain readable at the documented viewport sizes and 200% zoom. Labels, keyboard focus, validation errors, and save feedback work. Existing consumers of changed shared styles have no layout or interaction regressions.

### M2 — Navigation and dashboard

**Deliverables**

- [x] Group navigation into clear daily-work and administration areas appropriate to the signed-in role.
- [x] Reduce the dashboard's initial view to about four relevant metrics, up to three common actions, a short attention list, and limited recent activity.
- [x] Keep detailed charts and municipality comparisons reachable through descriptive links or disclosures.
- [x] Remove repeated totals and instructions; label scope, time periods, and units clearly.
- [x] Check the login page and existing idle-session warning for the same typography, action clarity, and error treatment.

**Acceptance:** Users can identify their office scope, find a common task, and open an attention item without navigating unrelated modules. Super Admin remains read-only for ordinary operations, and provincial veterinary users still enter Animal Health directly. Chart failure does not hide essential figures.

### M3 — Farmer registry and forms

**Deliverables**

- [x] Lead the directory with search, municipality context, the authorized create action, and essential columns.
- [x] Move less-used filters and analytics into labeled secondary sections; preserve a visible summary of active filters.
- [x] Group create/edit inputs into ownership, identity, farm information, and applicable additional details.
- [x] Keep required fields visible and automatically reveal any section containing a validation error.
- [x] Clarify entry points to record history, photos, digital/printable IDs, Excel imports, and parcel work.

**Acceptance:** A user can find a farmer, create or edit a profile, correct an error, and return to the directory. Values and record versions are preserved appropriately. Private photos, public QR links, import matching, and dependency-based deletion restrictions still work within municipality scope.

### M4 — Assistance and Animal Health

**Deliverables**

- [x] Simplify assistance entry around municipality where required, farmer, assistance item, quantity/unit, and date.
- [x] Reveal NRP and category-specific fields when relevant without losing existing values.
- [x] Simplify Animal Health entry around owner/raiser, animal or group, service, count, and date while retaining applicable required details.
- [ ] Make lookups, selected-record summaries, saving, successful completion, and error recovery consistent.
- [x] Reduce directory columns and initial filters; retain detailed reporting and supported import/export workflows.

**Acceptance:** Routine releases and services can be recorded without unnecessary steps. Fingerlings remain pieces, kilogram aggregates exclude incompatible units, and existing Animal Health compatibility defaults remain intact. Wrong-municipality selections and stale edits are rejected safely; retries do not silently create duplicates.

### M5 — Cooperatives and machinery

**Deliverables**

- [x] Make cooperative identity, member management, and member export easy to find.
- [x] Simplify machinery listings around search, availability, holder, maintenance attention, and the next action.
- [x] Align create/edit forms with shared components and group less-used acquisition and maintenance details appropriately.
- [ ] Make farmer/cooperative holder selection and changes to dependent selections understandable.
- [ ] Check mobile record layouts and clear reasons for blocked actions.

**Acceptance:** Users can assign members, find available machinery, identify maintenance work, and edit an asset. Related records remain in the same municipality, asset-code uniqueness is preserved, and cooperative transfer restrictions remain enforced.

### M6 — Maps and geofences

**Deliverables**

- [x] Give directory work and map work distinct, clear entry points while preserving named routes and existing map anchors/bookmarks.
- [x] Prioritize workspace/farmer selection, parcel viewing, and the current map action.
- [x] Place import, export, styling, and weather controls in labeled tools; expose editing only in the appropriate selection and permission state.
- [ ] Provide concise drawing instructions, a clear exit from editing, measured area, and understandable boundary warnings.
- [x] Simplify geofence history and review presentation while retaining activation/replacement safeguards.
- [ ] Check the public QR page, parcel sheets, and municipality snapshots for readable, consistent presentation and their existing privacy/attribution requirements.

**Acceptance:** A user can select a farmer, inspect a parcel, and complete an authorized map operation without conflicting modes. Invalid/outside geometry remains blocked where required, provider failures are understandable, and private information stays off public pages. Hiding a panel does not count as reducing an unbounded map payload.

### M7 — Files, administration, and reports

**Deliverables**

- [x] Simplify Backup Folder around search, upload, preview, and download; move hashes and specialized controls into details.
- [x] Standardize account forms and show role, municipality, status, and permitted actions clearly.
- [x] Present audit events with common filters and move extensive metadata into the event view.
- [ ] Align imports around file requirements, municipality selection, progress, result summaries, and actionable row errors where supported.
- [ ] Keep detailed reports and exports discoverable with accurate filters, quantities, periods, and download status.

**Acceptance:** Authorized users can complete file and administrative tasks while restricted roles remain blocked. File access, export scope, audit redaction, and formula-safe exports are preserved. No interface promises background jobs, automatic backups, or offline work that the application does not implement.

### M8 — Usability verification and release readiness

**Deliverables**

- [ ] Compare the revised tasks with the M1 baseline using representative staff and synthetic records.
- [ ] Record completion, time, wrong turns, help requests, and recognition of save success; resolve recurring confusion and repeat affected checks.
- [ ] Review all changed modules at the documented screen sizes, keyboard-only operation, 200% zoom, and long/empty/error states.
- [x] Run focused automated tests, Blade compilation, PHP syntax checks, route verification, Pint for changed PHP, and `git diff --check` as applicable.
- [ ] Use an isolated disposable test database based on the approved schema for database-backed checks; never point the test suite at production.
- [x] Update implementation documentation, note any remaining limitations, and prepare staging verification and rollback instructions.
- [ ] Record owner/staff review results and the production-release decision separately from local implementation status.

**Acceptance:** Common tasks show improved completion or reduced confusion compared with the baseline without regressions in routine entry. No unresolved critical defect affects permissions, municipality isolation, saved-data integrity, or access to essential tasks. Automated and visual checks pass, remaining limitations are documented, and a concrete release is ready for review. Production deployment requires the owner's explicit instruction.

### Completion record

For each milestone, keep a short record here or link to its implementation review:

```text
Milestone:
Status:
Responsible person:
Completed deliverables:
Changed screens/files:
Automated and visual checks:
Staff feedback and task results:
Remaining issues or dependencies:
Review reference and completion date:
Deployment status:
```

Next checkpoint: complete the remaining baseline and staff observations, verify persistence and providers in an isolated staging environment, and address the M6 payload limits. Keep final acceptance separate from the completed local interface work.
