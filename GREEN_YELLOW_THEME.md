# Green and yellow interface theme

**Purpose:** Give the Agriculture Information System a recognizable green and yellow identity while keeping daily office work clear, calm, and efficient.

This is the color and visual-treatment companion to [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md). Use the design system for layouts, typography, forms, interactions, and accessibility targets. This guide defines the green/yellow treatment for those components. [DESIGN_IMPLEMENTATION.md](DESIGN_IMPLEMENTATION.md) records implementation evidence and outstanding verification; this document does not certify every existing screen or replace staff usability testing.

## 1. Visual direction

Use forest green for identity and ordinary primary actions, warm yellow for deliberate accents, and white for the records and forms where staff spend most of their time. The result should feel like one modern management system across all modules.

As rough visual guidance, let neutral surfaces occupy about 80% of a typical page, green about 15%, and yellow about 5%. These are balance cues, not mandatory layout ratios. A dashboard overview can contain more green; a long form should remain mostly white.

- Use flat surfaces, clear borders, consistent alignment, and comfortable spacing.
- Keep the page background almost white, with a faint green tint at the upper left and yellow tint at the upper right. Use the shared background token at 3.5% green and 4.5% yellow opacity; fade the tint near the top and leave forms, tables, and dialogs solid white.
- Keep the page title, municipality, and main task easy to find.
- Limit yellow to a few purposeful locations. Do not make every panel compete for attention.
- Keep text and data more prominent than decoration. Avoid glass effects, neon tones, large gradients, and repeated heavy shadows.
- Retain the same colors and interactions across roles; permissions determine available work.

## 2. Shared palette

Define these values once in [the shared design tokens](resources/views/partials/design-tokens.blade.php). Components consume tokens rather than repeating hex values in each view.

| Token | Value | Purpose |
| --- | --- | --- |
| `--ui-primary` | `#236344` | Ordinary primary buttons, links, selected-state indicators |
| `--ui-primary-hover` | `#18492F` | Primary button hover |
| `--ui-primary-soft` | `#EAF4ED` | Success surfaces and quiet green emphasis |
| `--ui-brand-dark` | `#173E2D` | Dashboard or genuine workspace overview |
| `--ui-accent` | `#FACC15` | Yellow brand accents and overview primary action |
| `--ui-accent-hover` | `#EAB308` | Yellow action hover |
| `--ui-accent-soft` | `#FFF8D6` | Active navigation and subtle accent surfaces |
| `--ui-on-accent` | `#20362C` | Text and icons on yellow |
| `--ui-bg` | `#F8FAF8` | Almost-white page background |
| `--ui-page-background` | Green 3.5% / yellow 4.5% corner tints over `--ui-bg` | Subtle top-edge color fading into the base within roughly 300px |
| `--ui-surface` | `#FFFFFF` | Forms, tables, dialogs, work panels |
| `--ui-surface-subtle` | `#F5F8F5` | Table headings and secondary grouping |
| `--ui-text` | `#20362C` | Main text, labels, values |
| `--ui-text-muted` | `#607269` | Supporting descriptions and metadata |
| `--ui-border` | `#DFE7E1` | Panel dividers |
| `--ui-control-border` | `#7A9182` | Visible boundaries around entry controls |
| `--ui-focus` | `#236344` | Keyboard focus on light surfaces |
| `--ui-focus-inverse` | `#FACC15` | Keyboard focus against dark green |
| `--ui-brand-stripe` | Solid green/yellow segments, 78% / 22% | Thin shell, login, and public-map header strip |

Existing `--module-*` and `--ops-*` aliases should reference canonical `--ui-*` values where they serve the same purpose. Keep specialized layout rules local.

### Approved color combinations

| Foreground / background | Approximate contrast | Use |
| --- | --- | --- |
| White / primary green | 7.14:1 | Ordinary primary button label |
| Dark text / yellow | 8.44:1 | Overview action and accent-chip label |
| Dark text / yellow hover | 6.74:1 | Hovered overview action label |
| Primary green / pale yellow | 6.68:1 | Active navigation label |
| Yellow / dark green | 7.78:1 | Inverse focus outline |

These are calculated opaque color pairs, not a complete accessibility audit. Check actual rendered states and adjacent surfaces. Yellow against white is only about 1.53:1: never use yellow text on white, white text on yellow, or a yellow-only input border/focus indicator on a light surface. Give a yellow control a dark boundary if its edge is needed to identify the control.

### Preserve status meaning

| State | Text / surface | Required cue |
| --- | --- | --- |
| Success | `#236344` / `#EAF4ED` | A message such as “Changes saved” |
| Information | `#346A8A` / `#EDF4F8` | An explanatory label or message |
| Warning | `#906019` / `#FBF2DF` | The issue and next action, such as “Maintenance due” |
| Error or destructive action | `#A6504A` / `#FAEFED` | Explicit error text or a destructive action label |

Brand yellow does not mean “warning.” Warning messages keep their semantic treatment and wording. Never communicate an error, status, selection, or permission through color alone.

## 3. Component treatment

| Component | Green/yellow treatment | UX requirement |
| --- | --- | --- |
| Application shell and login | Thin green/yellow brand strip with solid color segments | Keep branding compact; the form and navigation remain the focus |
| Active navigation | Pale yellow surface, green text/icon, green edge indicator | Preserve a visible label and the programmatic current-page state |
| Dashboard overview | Dark green surface; yellow primary action with dark text | Keep at most three common actions and clearly identify the office scope |
| Ordinary page primary action | Solid green with white text | Use one clear main action, such as “Add farmer” or “Save changes” |
| Secondary action | White or subtle neutral surface with dark/green text | Keep Cancel, Back, Import, and Export distinguishable from the main action |
| Form section number | Small yellow chip with dark text | Number only a meaningful sequence; short forms need simple headings |
| Entry control | White surface, visible control border, green focus | Preserve labels, required-field cues, values, and adjacent error text |
| Table | White rows, subtle header, restrained green links | Keep rows readable; use status labels rather than coloring entire records |
| Report disclosure | Neutral panel with a clear heading and open/close affordance | Keep figures readable if charts cannot load |
| Charts | Green for a principal series; yellow as a limited contrasting series | Add labels/legends and accessible figures; additional categories need distinct cues |
| Maps and geofences | Apply brand color to surrounding panels and controls | Preserve stored parcel colors, boundary meanings, map attribution, and provider visibility |

In the municipality geofence workspace, retain saved boundary colors but add a pale `#FFF8D6` outline casing and readable label badges over satellite imagery. Keep the fill opacity independent from the outline: the map's opacity slider can clear the fill without hiding the boundary edge. Draft fill stays lighter than active fill.

Use yellow for the main button only inside the dark green overview, where that treatment is consistent and readable. Normal create/edit forms retain green Save buttons. Destructive actions retain their existing danger treatment.

## 4. Modern UI that remains easy to use

- **Typography:** Roboto with a system-font fallback; weights 400, 500, and 700. Use 14px body text and labels, 16px entry values, and at least 12px helper text. Do not shrink text to fit extra information.
- **Shape and spacing:** Follow the 4px spacing scale, 8px control corners, and 12px panel corners. Use 24px panel padding on desktop and 16px on phones. Keep shadows mainly for menus and dialogs.
- **Starting point:** Show the main task and essential figures first. Keep detailed reports, advanced filters, and optional form fields in clearly labeled disclosures.
- **Forms:** Keep required fields visible. Closing an optional section must preserve its values. Reveal sections containing validation errors and direct focus to the error summary or affected field.
- **Language:** Use concise, plain English and familiar domain labels. Keep wording consistent across modules and explain unfamiliar actions beside the relevant control.
- **Feedback:** Make loading, saved, empty, unavailable, failed, and uncertain-save states understandable. Preserve existing duplicate-submit protection and do not imply a save succeeded before confirmation.
- **Keyboard access:** Preserve logical focus order, visible focus outlines, labeled actions, and dialog focus return. A colored surface is not an interaction cue by itself.
- **Mobile:** Use a single form column on narrow screens and controls at least 44px high for primary entry/actions. Keep meaningful table scrolling inside its region. Do not make color changes increase page overflow.
- **Motion:** Use brief, restrained state transitions and respect reduced-motion preferences. Essential tasks must not depend on animation or hover.
- **Simplicity:** A new theme should not add extra clicks, banners, confirmation steps, or permanent explanatory panels to a familiar task.

## 5. Review before accepting a themed screen

- [ ] Uses shared color tokens and existing components.
- [ ] Green/yellow identity is visible without crowding records or forms.
- [ ] Ordinary primary actions are green; yellow overview actions have dark text.
- [ ] Warning/error/success states remain explicit and retain their semantic colors.
- [ ] Actual text, hover, focus, and control-boundary contrast meets the targets in the design system.
- [ ] Keyboard focus remains visible against light, yellow, and dark green surfaces.
- [ ] Required fields, validation recovery, disclosures, and entered values still behave correctly.
- [ ] Narrow layouts, long labels, empty states, and loading/failure states remain usable.
- [ ] Chart labels and readable figures remain available; saved map colors are preserved.
- [ ] Role permissions, municipality scope, record versions, and routes remain intact.

Record the screens checked and any exceptions in [DESIGN_IMPLEMENTATION.md](DESIGN_IMPLEMENTATION.md). Full accessibility, staff usability, and staging verification remain separate acceptance work; a palette change alone does not complete them.
