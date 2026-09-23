# Agriculture Information System — Complete Feature Catalog

Bicol Region is live with 114 explicit planning/reference geofences: Albay 18, Camarines Norte 12, Camarines Sur 36 including Iriga City, Catanduanes 11, Masbate 21, separate Naga City 1, and Sorsogon 15. The import is atomic and idempotent, preserves existing rows and accounts, and is activated only through `BicolBoundarySeeder` plus `region-access:configure --region=region5`; it issues no accounts. Naga City is a separate scope. See `docs/BICOL_BOUNDARY_SOURCES.md` for sources and limitations.

MIMAROPA is live on Hostinger as of September 23, 2026 (runtime `33a150c`), with 73 municipality/city planning geofences across its five provinces and the separate Puerto Princesa City scope. Calapan stays under Oriental Mindoro. The verified import preserved every existing record and account. Its region membership is configured, so MIMAROPA appears in the Farmers region chooser. The small-island checks retain Kalayaan's full source shape and all 36 Cagayancillo parts in a compact reference. These outlines require LGU/NAMRIA verification before official use. See [MIMAROPA sources, checks and deployment status](docs/MIMAROPA_BOUNDARY_SOURCES.md).

Latest interface release: Hostinger installed `88d3bdc` on September 23, 2026. Farmers now selects Region → Municipality, and homepage collages use Previous/Next arrows. CALABARZON has one active Regional Head plus active provincial administrator and staff accounts for Cavite, Batangas and Laguna; each account's live sign-in and assigned municipality choices were checked.

Deployment status: the September 20, 2026 Hostinger release includes the welcome collage, dashboard graphs, assistance coverage, Ramos barangay references, Region II, Negros Island Region and Mountain Province geofences. See `docs/FULL_DEPLOYMENT_2026_09_20.md` for verified scope and limitations.

The September 21 Hostinger release adds compact dashboard charts/tables, the remaining 53 CAR geofences (77 municipality/city references total), and the office sign-in confidentiality/testing notice. Existing data and account assignments were preserved. See `docs/CAR_DASHBOARD_DEPLOYMENT_2026_09_21.md`. The pending primary farmer-ID display/search update was not included.

## 1. System overview

Regional Heads can oversee the active provinces and separate city scopes in their assigned region, view operational reports/maps, and manage provincial heads and lower accounts there. Operational editing and geofence editing remain unavailable to them. Provincial heads use the province-limited Super Admin role. The System Owner manages Regional Head assignments. See `docs/REGIONAL_SUPERVISION.md` for setup and deployment status.

The system uses the **AgriGOV** name and leaf-and-field logo across its public pages, office login, application navigation, and browser tabs. Official office seals remain on farmer registry cards; the cards identify AgriGOV as the application.

The Agriculture Information System is a Laravel and MySQL platform for multiple supervised provinces for managing agricultural records across the Provincial Agriculture Office and participating municipal agriculture offices. It combines farmer registration, GIS land mapping, agricultural and fisheries assistance, animal-health services, cooperative management, machinery monitoring, protected files, dashboards, reporting, user administration, and audit records in one municipality-aware system.

The application is designed for multiple offices using the system at the same time. Every operational record is assigned to a municipality, and users only receive the records and actions allowed by their role.

An explicit System Owner demonstration import can populate four clearly labeled sample farmers in each of three Region I municipalities, three hypothetical plots per farmer and sample rice distribution: 12 farmers, 36 plots, 12 releases and three batches (480 kg total). It preserves existing records and accounts, issues no farmer sign-ins, and rejects duplicates or changed samples. **These samples appear in dashboard totals and do not represent actual farmers, surveyed land or seed delivery.** This was deployed to Hostinger on September 24, 2026 for Bacarra, Narvacan and Bacnotan after a verified private backup. See `docs/REGION_I_SAMPLE_DATA.md` for workspaces and verification.

### Public farmer welcome page

- Visitors to the homepage can read guides to farmer registration, crop inputs, fisheries assistance, animal health, farm mapping, cooperatives, and machinery inquiries.
- The homepage presents farming/fishing photographs as a bold collage, with services before the system overview, separate farmer/office sign-in, keyboard-friendly disclosures, and manual Previous/Next collage arrows. Its layout adapts to phones and keeps decorative transitions low-motion; this visual redesign was deployed September 20, 2026.
- A clearer three-line welcome message highlights the farmer audience. Buttons, links, photo frames, and service disclosures give subtle visual feedback; section shortcuts preserve keyboard focus and mark the current section. Reduced-motion preferences remove the decorative movement.
- Official DA, RSBSA Finder, PhilRice, ATI, BFAR, and PAGASA links provide program, learning, and weather information.
- An office-visit checklist helps visitors prepare their information; the local office confirms requirements, schedules, and eligibility.
- Mobile navigation and keyboard-accessible service disclosures work alongside a separate office sign-in entry.
- Credited Philippine agriculture photographs illustrate the services, including rice farming, fisheries, livestock, and machinery.
- Twenty photographs show Philippine farming, fishing, crops and livestock in five different collages, with four photos per view and wrapping Previous/Next arrows. The first four photos work without JavaScript; later collages load when visited.
- About AgriGOV explains farmer records, parcel maps and dry/wet seasonal crops, assistance releases, animal-health services, cooperatives/machinery, and reports. The DA seal accompanies official DA resource links.
- Inside AgriGOV groups these six capabilities in an expandable office-tools panel, with short benefits and an office sign-in button. Tool descriptions open by click or keyboard without requiring JavaScript; the layout stacks on phones.
- The page displays no private records or operational totals and does not offer public applications, account registration, or equipment bookings.
- Signed-in users keep their existing dashboard or Animal Health destination.

## 2. Supported user roles

| Role | Main access |
| --- | --- |
| System Owner | Oversight of all configured provinces; province Super Admin management, geofences, and global audit/security |
| Super Administrator | Operational viewing, municipality geofences, staff management, and audit records limited to the assigned province |
| Provincial Agriculture Staff | Operational access within the assigned province, with the ability to select a municipality when creating or importing records |
| Provincial Veterinary Office | Access within the assigned province, limited to Animal Health services |
| Municipal Head Agriculturist | Full operational access for the assigned municipality and management of municipal staff accounts |
| Municipal Staff | Operational access limited to the assigned municipality |

### Role and municipality safeguards

- Municipal accounts can only view and manage records belonging to their assigned municipality.
- Provincial agriculture staff can work across municipalities but must select the correct municipality for new records and imports.
- Provincial Veterinary Office accounts can only access the Animal Health module.
- System Owners and Super Administrators have read-only oversight of ordinary operational records.
- A Benguet Super Admin sees only Benguet workspaces; a Tarlac Super Admin sees only Tarlac workspaces. The same separation applies to staff, maps, dashboards, reports, searches, and exports. Data in other provinces is retained.
- Provincial agriculture and veterinary staff require their own province assignment. Unassigned or inactive province access is blocked at login and on protected requests.
- System Owners can manage geofences across provinces; Super Administrators can manage only their province’s geofences.
- Dedicated GIS Evaluator accounts can review active municipality geofences and available read-only barangay reference layers across configured provinces. They cannot access farmers, parcels, assistance, dashboards, snapshots, exports, drafts, accounts, audit trails, or editing actions; those operational queries are not run for evaluator sessions.
- Evaluators must change their temporary password before viewing maps and have an enforced expiry date. Boundary visits and password changes are audited. NAMRIA evaluation access is issued for 30 days.
- System Owners and Super Administrators cannot access the Backup Folder.
- Municipal Heads can only manage Municipal Staff accounts from their own municipality.
- Every protected record is checked again on the server before it can be viewed, changed, deleted, downloaded, previewed, or exported.

## 3. Authentication and session security

- Secure email and password login.
- Desktop sign-in places the form beside an eight-photo Philippine agriculture slideshow that loops automatically without visible controls. On smaller screens the form comes first. Reduced-motion preferences disable automatic playback; photo credits are linked as Image sources in the sign-in footer.
- Below the office sign-in button, a confidentiality notice states that access is for authorized testing and validation, asks participants to keep nonpublic information confidential, and restricts sharing or reuse of confidential materials to reproduce AgriGOV without the owner's written permission. A required **I have read and understood** checkbox must be checked before office sign-in; both browser and server validate it. It starts unchecked and stays checked after an incorrect password so the user can retry. This is an acknowledgment, with no stored signature or separate acceptance record. The checkbox update is live on Hostinger as of September 21, 2026 (610013d). Farmer sign-in is unchanged.
- Optional “Remember me” login.
- Passwords are stored as one-way hashes and cannot be retrieved as plaintext.
- New and changed passwords must be at least 12 characters, and passwords that appear in known public data breaches are refused. A few ordinary words together satisfy this and are easier to remember than a short password with symbols. The check never sends the password itself, and account creation still works when the office has no internet connection.
- Session ID regeneration after a successful login.
- Active-account and supported-role validation.
- Municipality assignment and municipality status validation for municipal accounts.
- Last-login timestamp tracking.
- Successful, failed, and blocked login auditing.
- Sign-in attempts are limited to five per email address from one device before a five-minute lockout, which is recorded once in the audit trail. Signing in successfully clears the count, and a lockout on one account never blocks a colleague signing in from the same office.
- Secure logout with session invalidation and CSRF-token regeneration.
- Signed-in pages use non-storable responses; restoring a page with the browser's Back/Forward cache hides its old contents and reloads it to check the current sign-in session.
- Automatic logout after 15 minutes of inactivity.
- One-minute session-expiration warning.
- Activity synchronization across browser tabs.
- Server-side idle-session enforcement even when browser JavaScript is unavailable.
- Throttled activity heartbeat while a user is active.

### Shared interface behavior

- Consistent green/yellow theme with a nearly white background and faint corner tints, solid work surfaces, Roboto typography, readable form controls, and visible keyboard focus; color rules are documented in [GREEN_YELLOW_THEME.md](GREEN_YELLOW_THEME.md).
- Navigation grouped into daily work, office tools, and administration as permitted by the account role.
- Mobile menu with keyboard focus handling and a skip-to-content link.
- Optional form sections keep entered values and open when validation errors need attention.
- Error summaries link to the first field to check; the existing idle-session warning explains the risk to unsaved changes.
- Detailed assistance, Animal Health, machinery, and farmer charts open through labeled reports or insights, with readable figures available if charts fail.

Implementation status and remaining staff/staging checks are documented in [DESIGN_IMPLEMENTATION.md](DESIGN_IMPLEMENTATION.md).

## 4. Dashboard and analytics

### Municipal and provincial operations dashboard

- Annual assistance reach: distinct registered farmers with a valid same-municipality release, farmers without such a recorded release, and the share of the current registry reached.
- Releases requiring a valid farmer link and undated releases excluded from annual figures.
- Current geofence coverage across active municipalities, with missing and conflicting active versions shown separately.
- These figures appear under **Reports and office details → Program activity and equipment** and follow the account's authorized scope.

- Registered farmer totals.
- Farmer mapping coverage.
- Mapped and unmapped farmer counts.
- Total farm parcels and mapped hectares.
- Agriculture and fisheries assistance release totals.
- Kilograms of eligible farm inputs released.
- Fish fingerlings released.
- Animal-health service and animal totals.
- Cooperative totals.
- Machinery inventory, availability, and maintenance indicators.
- Current-month activity summaries.
- Monthly assistance-release trend chart.
- Leading seed varieties and assistance items.
- Recent agriculture and fisheries releases.
- Recent animal-health services.
- Recent parcel activity.
- Farmers missing FFRS numbers.
- Farmers missing farm locations.
- Farmers that still need parcel mapping.
- Backup-file summary for roles allowed to access protected files.
- Role-aware dashboard actions and links.
- A default view with four key figures, up to three common actions, attention items, and five recent assistance releases.
- Attention shortcuts open farmers missing parcels, FFRS numbers, or locations and machinery requiring maintenance.
- Reports disclosure for detailed totals, current-month activity, charts, recent services, and parcel activity; chart loading starts when opened and monthly figures remain available without charts.
- A **View graphs and reports** shortcut near the dashboard heading.
- Machinery condition and availability charts grouped by equipment type, with missing inventory classifications shown explicitly.
- Monthly animal-health graphs showing service counts by vaccination, deworming, vitamins/supplementation, and treatment.
- Fingerlings distributed by active municipality, measured in pieces separately from release counts and beneficiaries.
- A reporting-year selector for monthly animal-health services, fingerling quantities, and municipality production comparisons. Other totals retain their stated reporting periods.
- Production comparisons by municipality, commodity, and unit for accounts with multiple active municipalities in scope; no combined score or mixed-unit total.
- A commodity/unit selector for the yearly production trend, with missing harvest entries distinguished from recorded zero production.
- Figures tables behind every graph, including notices for incomplete or undated records; they remain usable when charts cannot load.
- Municipality graphs show eight offices at a time with name search and Previous/Next controls. Full figures open on request in scrollable tables, keeping large regional and national dashboards compact. This update was deployed to Hostinger September 21, 2026.

### System Owner and Super Administrator dashboards

- Separate province-wide municipality comparison with search, status filters, sorting, and scoped directory links.
- Farmer totals per municipality.
- Mapping coverage and mapped hectares per municipality.
- Distribution, animal-health, cooperative, machinery, and staffing statistics.
- Identification of municipalities without a Municipal Head.
- Identification of municipalities without farmer records.
- Identification of municipalities behind on parcel mapping.
- Monitoring of operational records that have no assigned municipality, visible only to the System Owner.
- Read-only oversight without operational data-entry controls: all provinces for the System Owner, or the assigned province for a Super Administrator.

## 5. Farmer registry

- Create, view, edit, search, filter, paginate, and delete farmer profiles.
- Farmers opens directly with farmer records and the parcel map across the account's authorized area. A System Owner can filter by region; a Regional Head can filter by province or separate city scope within the assigned region. Municipality filtering is optional, and existing municipality bookmarks still work.
- Location filters apply to records, totals, farmer search and parcel data together. Provincial and municipal permissions remain fixed. Broad map views explicitly label capped outline/parcel layers and can be narrowed by location.
- Registry totals and map data reflect the selected authorized area. This overview update is locally implemented; deployment is pending.
- Filtering by municipality, gender, mapping status, missing FFRS, and missing farm location.
- Registry-only filters that do not unexpectedly remove other municipality parcels from the shared map.
- Farmer name, identity, contact, classification, location, and declared farm-area fields.
- FFRS and RSBSA number storage.
- ARB, 4Ps, IP, PWD, Senior Citizen, and OFW classifications.
- Ecosystem and farm-location information.
- Private farmer profile-photo upload, replacement, removal, and authorized streaming.
- JPG, PNG, and WebP photo support up to 3 MB.
- System-generated farmer registry number in the format `PAIS-FRM-######`.
- Farmer assistance-release history.
- Farmer machinery assignments.
- Farmer parcel totals and mapped-area summaries.
- Date range, top assistance item, top variety, and weighted release totals.
- Farmer-level charts and historical record views.
- Safe farmer deletion rules that protect linked assistance and parcel records.
- Automatic cooperative-membership cleanup when deletion is allowed.
- Five-column directory with explicit details/history, ID, profile-edit, and map actions according to role.
- Optional contact, photo, and classification sections in farmer forms.
- Authorized farmer record pages show every retained field from the original municipality Excel source rows, including classifications, addresses, parcel references, commodities, crop areas, farm type, livestock head count, agency, ownership and original owner details. Repeated workbook rows remain separate, and original conflicting RSBSA values do not replace the canonical farmer identity.
- Registry source rows inherit municipality ownership from the linked farmer. The source workbook hash, sheet and row number make the backfill idempotent and traceable without exposing the workbook publicly.
- Delisted workbook rows are retained separately without an active farmer link; duplicate, deceased and inactive source statuses do not reactivate a profile.
- Parcel Map workspace opened separately from directory work while preserving existing map bookmarks; map startup and parcel retrieval begin on opening.

## 6. Farmer ID and digital identification

- Print-ready, two-sided farmer registry card.
- Responsive digital-ID preview.
- Front and back card switching.
- Download of the currently displayed card side.
- Farmer profile picture on the ID.
- System-generated farmer ID number.
- RSBSA and FFRS numbers.
- Municipality and barangay information.
- Farm and sector information.
- QR code embedded in the card.
- Enlarged QR view for easier scanning.
- QR code links to the farmer’s public interactive parcel page.
- The card clearly identifies itself as a local agriculture registry card rather than a Philippine national government ID.

## 7. GIS farmer and parcel workspace

- Google Maps hybrid and satellite mapping.
- Province and municipality map views.
- Municipality selector that reloads farmer records, parcels, totals, and weather for one municipality.
- Complete farmer finder for the selected municipality.
- Search by farmer name, FFRS number, or farm location.
- Farmer and parcel visibility controls.
- Draw a parcel polygon directly on the map.
- Save, rename, recolor, reshape, and delete parcel boundaries.
- Server-side parcel area calculation in hectares.
- Server-side parcel-centroid calculation.
- Fit-to-municipality, fit-to-parcel, and reset-map controls.
- Parcel hover card showing farmer, FFRS, location, parcel name, and area.
- Clicking a parcel isolates the selected farmer’s parcels.
- In-map reset restores all parcels for the selected municipality.
- Parcel boundaries are displayed without unnecessary centroid pins.
- Large 3D parcel collections draw in batches using one interactive shape per parcel. The overview uses lighter display outlines, while selection and close inspection restore full detail. Stored boundaries, measured areas, editing, and exports retain their original coordinates.
- Municipality-scoped parcel loading prevents parcels from different municipalities from being mixed.
- **Crops by season:** select a year and dry/wet season to color parcels by recorded rice/palay, corn, vegetables, root crops, fruit, legumes, mixed crops, or other crops. A crop filter and labeled legend show the classifications and counts for loaded parcels.
- Staff record and correct a parcel's seasonal crop from the **Seasonal crops** action beside that parcel. Optional notes can identify crops grown together or the source of the observation; saved seasons remain available for review.
- Super Admin and System Owner accounts may inspect seasonal crops but cannot change them. Municipality ownership, audit logging, and stale-edit protection apply to crop records.
- Missing crop records appear in gray as **Not recorded**. This does not mean fallow. Seasonal classifications do not establish current planting, planted hectares, or production; assistance and harvest records are not automatically assigned to parcels.
- Switching back to **Saved parcel colors** restores the original display. Crop styling does not alter parcel boundaries, saved colors, or exports. Loading/error states are distinct from missing crop records.
- Authorized selected-farmer KML and KMZ import.
- Server-side KML and XML bulk parcel import for one municipality.
- Import matching by parcel code, full name, surname and barangay, or a unique surname. Shared-surname matches fail closed for staff review instead of attaching a parcel to the first database row.
- Each completed bulk parcel import records one aggregate audit event with counts; farmer names and parcel coordinates are excluded from the audit metadata.
- Imported KML colors are preserved when available.
- Printable parcel information sheet.
- High-resolution parcel PNG export.
- Same-origin Google Static Maps proxy for satellite exports.

## 8. Official municipality geofencing

### Boundary management

- Google Maps geofence workspace scoped to the signed-in account.
- Municipality search and selection for provincial staff and Super Administrators. Municipal heads and staff open their assigned workspace directly, without the municipality finder or references to other workspaces.
- The sidebar office label uses the assigned workspace's municipality and province rather than a fixed province name.
- Display of active, draft, and archived boundary records.
- Saved boundary colors and subtle municipality labels inside the mapped areas.
- Faster map browsing through reusable shapes, drawing in small batches, and hiding off-screen boundaries. Municipality names appear when zoomed in or when one municipality is selected. Search waits for typing to pause and cancels superseded requests. Stored coordinates, editing precision, and snapshot exports retain full detail.
- **Geofence appearance** includes a color picker and a **Geofence color opacity** slider from 0% (clear fill) to 100% (solid fill). System Owners and authorized provincial Super Administrators use **Save color & opacity** to persist the selected boundary's appearance for both maps, or **Discard changes** to restore it. Other roles can view the saved settings. Preview changes remain unsaved until confirmed with Save; conflicts and failed saves retain the preview with an explanation.
- Both municipality geofences and the Farmers parcel map use that saved color and opacity. Reload an already open Farmers page after saving. Labels stay subtle and hide when the parcel map is zoomed far out. Outlines remain visible at 0% fill. Parcel colors and exported snapshot styling are unchanged.
- Existing boundary shapes retain their saved opacity while being edited; new drawings start at 20%. The separate appearance controls are disabled during shape editing. The saved fill is suppressed during editing and restored on cancel so two fills do not blend. Appearance can be saved for multi-island boundaries without editing their shape.
- Fit-to-boundary and reset controls; municipal users stay on their own boundary and parcels.
- Active official geofences are also displayed beneath parcels in the Farmers 3D map, with a show/hide control and scope-aware camera fitting.
- Boundary creation and modification by the System Owner or the assigned province Super Administrator.
- Draw official municipality polygons directly on the map.
- Import KML, KMZ, GeoJSON, JSON, and XML boundaries.
- Polygon and MultiPolygon support.
- Boundary preview before saving.
- Draft, active, and archived boundary lifecycle.
- Confirmation before replacing an existing active boundary.
- One active official boundary per municipality.
- Boundary area, centroid, vertex count, and bounding-box calculation.
- Invalid coordinate rejection.
- Ring-closing and geometry normalization.
- Self-intersection and invalid-hole detection.
- Precision-aware validation of short survey edges, preserving valid detailed parcel shapes while rejecting actual crossings and rings smaller than one square metre.
- Safe simplification of oversized geometry.
- Configurable maximum geometry size.
- Detection of overlapping active municipality boundaries.
- Shared municipality edges are allowed when they do not create an actual overlap.
- Changing a boundary's name or color preserves its saved shape. Vertex edits retain untouched shared-border coordinates at their original precision, preventing rounding from creating false overlap errors; genuine overlaps remain blocked.
- Save boundary shows a saving indicator, prevents repeated submissions, and keeps failed edits with a persistent explanation beside the save controls. Expired sessions are explained clearly. Name/color-only updates do not require geometry replacement confirmation; changing an active shape still does.
- Optimistic locking and municipality-level mutation locks for concurrent edits.
- Explicit, idempotent reference imports cover all 18 Tarlac workspaces (17 municipalities and Tarlac City), all 24 Bulacan workspaces (20 municipalities and the component cities of Baliwag, Malolos, Meycauayan, and San Jose del Monte; legacy workspace spelling `Baliuag` is preserved), Baguio City, and all thirteen Benguet municipalities.
- Reference files must be imported into each deployment's database before their boundaries appear on maps; deploying the code alone does not activate geofences. The Bulacan importer recognizes the legacy workspace code without renaming or replacing its existing records.
- Region III coverage totals 130 municipality/city references across seven provinces and the separate Angeles City and Olongapo City scopes. Provincial administrators cannot access either independent city through Pampanga or Zambales. The 88 additional references preserve existing Tarlac/Bulacan data; a coarse Bulacan province outline is retained as archived history when replaced by municipality boundaries. No accounts or sample records are created. See `docs/REGION_III_BOUNDARY_SOURCES.md`.
- Region I has explicit planning-reference imports for 125 cities and municipalities: Ilocos Norte (23), Ilocos Sur (34), La Union (20), and Pangasinan (48). Repeated town names are labeled with their province. Imports preserve existing data, stop on conflicts, and create no accounts or sample records. Paoay’s reference includes its lake; boundary area is not farmland area. Dagupan is in the geographic Pangasinan planning group. These approximate boundaries require LGU/NAMRIA verification before official use. See `docs/REGION_I_BOUNDARY_SOURCES.md`.
- Region II has 93 planning-reference geofences: Batanes (6), Cagayan (29), Isabela (36), Nueva Vizcaya (15), Quirino (6), and Santiago City (1). Santiago has its own access scope and is excluded from Isabela administrator access. Repeated names are province-qualified; existing records are preserved. These imports create no accounts or sample farmer records. See `docs/REGION_II_BOUNDARY_SOURCES.md`.
- The new saved-appearance feature starts these 93 Region II references with white fill at 20% opacity. Applied and verified locally and on Hostinger on September 21, 2026 (610013d). The setup preserves boundary shapes, other provinces' colors and all account assignments. See `docs/GEOFENCE_APPEARANCE_2026_09_21.md`.

- Mountain Province has ten municipality planning-reference geofences: Barlig, Bauko, Besao, Bontoc, Natonin, Paracelis, Sabangan, Sadanga, Sagada and Tadian. Authorized users select Mountain Province in Municipality geofences; Bontoc appears as Bontoc (Mountain Province). Existing records and province permissions are preserved. Deployed September 20, 2026; these approximate outlines require LGU/NAMRIA validation before official use. See `docs/MOUNTAIN_PROVINCE_BOUNDARY_SOURCES.md`.

- Negros Island Region has 63 municipality/city planning-reference geofences: Negros Occidental (31), Negros Oriental (25), Siquijor (6), and Bacolod City (1). Bacolod has its own access scope and is excluded from Negros Occidental administrator access. The local setup preserves the existing geofences and farm records, updates source identifiers and separates Bacolod supervision. These are approximate planning references requiring LGU/NAMRIA verification before official use. No accounts are created. Deployed September 20, 2026; see `docs/NEGROS_ISLAND_BOUNDARY_SOURCES.md`.
- Ramos, Tarlac has nine barangay planning-reference outlines. Select Ramos in Municipality geofences, choose a barangay to highlight it and press Focus to zoom. Includes a visibility toggle, labels, PSGC codes and source/accuracy notes. These simplified references require local validation; they do not assign farmers/parcels, change official boundaries, determine eligibility or appear in municipality snapshots. Access follows existing municipality/province permissions. Deployed September 20, 2026; see `docs/RAMOS_BARANGAY_BOUNDARIES.md`.
- Baguio City has 129 barangay planning-reference outlines in the same Barangay boundaries panel, with alphabetical selection, highlight, Focus, labels, PSGC codes and a visibility toggle. Access follows Baguio's separate city scope; Benguet access alone does not include it. Existing records and the city geofence are preserved. These approximate outlines require local validation. Deployed to Hostinger September 21, 2026 in `a751355`; see `docs/BAGUIO_BARANGAY_BOUNDARIES.md`.
- A separate boundary-only Tarlac import adds Bamban, Capas, Gerona, La Paz, Mayantoc, Moncada, Pura, San Clemente, San Jose, San Manuel, Santa Ignacia, and Victoria. It preserves the six existing references and archived boundary history, creates no sample operational records, and stops the entire import if any boundary or workspace conflicts. The pinned municipality identities and areas are checked against PSA/GeoRiskPH references. These approximate planning boundaries require LGU/NAMRIA verification before official use; normal municipality isolation and parcel geofence validation apply once active.
- The province-level Bulacan import is stored as a clearly labeled ADM2 planning/reference boundary and does not create farmers or operational records.
- A separate Bulacan municipality import adds all 20 municipalities and the 4 component cities (including Baliwag, stored with the legacy `Baliuag` spelling) as ADM3 planning references. It creates missing workspaces or reuses existing active ones and creates no users or sample operational records. Two workspaces deliberately differ from the source wording: the municipality is named Bulakan, because the source spelling is identical to the Bulacan province workspace, and the three cities follow the existing "Tarlac City" wording.
- Because a province boundary contains every municipality inside it, the two cannot both stay active. The municipality import archives the active Bulacan province reference as superseded, records that in the audit trail with its reason, and leaves the province workspace and its archived history otherwise untouched; re-running the province import restores the province-level view. Any other conflicting boundary stops the entire import and the archival is rolled back with it.
- The Baguio import uses a pinned city-level boundary from geoBoundaries, checked against PSA identity and area references. It creates or reuses the Baguio workspace without creating sample records, preserves an existing different active boundary, and applies the normal parcel validation once active. The boundary is an approximate planning reference requiring LGU/NAMRIA verification before official use.
- La Trinidad, Atok, and Tublay have a separate import using verified municipality features from the same pinned dataset. It creates or reuses their Benguet workspaces and activates all three references together, preserving existing different active boundaries and creating no sample records. Any conflict stops the entire import. These planning references retain normal municipality isolation and require LGU/NAMRIA verification before official use.
- A second Benguet boundary-only import adds Bakun, Bokod, Buguias, Itogon, Kabayan, Kapangan, Kibungan, Mankayan, Sablan, and Tuba. It creates missing municipality workspaces or reuses existing active ones, preserves existing boundaries and archived history, and creates no users or sample operational records. All ten references are applied together; an identity or boundary conflict stops the entire import. They use the existing geofence visibility and opacity controls, municipality isolation, and parcel validation. These are approximate planning references requiring LGU/NAMRIA verification before official use.

- Remaining CAR coverage adds 53 municipality/city planning-reference geofences: Abra (27), Apayao (7), Ifugao (11), and Kalinga (8, including Tabuk City). Together with Benguet, separate Baguio City and Mountain Province, this covers 77 CAR municipalities/cities. Repeated town names include their province, and Tabuk remains under Kalinga supervision. Existing records and account permissions are preserved; imports do not create accounts or assign Regional Heads. See `docs/REMAINING_CAR_BOUNDARY_SOURCES.md` for sources and deployment instructions and `docs/REMAINING_CAR_LOCAL_SETUP_2026_09_21.md` for local installation status. These approximate outlines require LGU/NAMRIA validation before official use.

### Parcel geofence validation

- Every existing parcel is classified as inside, near boundary, crossing, outside, invalid, or unconfigured.
- Review list for parcels that require field verification.
- Newly created and edited parcels are checked against the farmer’s official municipality boundary.
- Parcels completely outside the assigned municipality are blocked.
- Invalid parcel geometry is blocked.
- Parcels crossing a boundary can be saved with a visible warning.
- Parcels near a boundary receive a review warning.
- Browser KML/KMZ imports and server bulk imports use the same municipality-boundary validation.
- Outside or invalid bulk-import parcels are skipped and included in the import result summary.

### Municipality land snapshot

- Download button for the currently selected municipality.
- Available only when the municipality has an active official boundary.
- Includes all municipality-owned plotted parcels, including parcels outside the active municipality geofence.
- Produces a high-resolution square PNG framed around the official boundary and every municipal parcel.
- Satellite imagery is visible only inside the official municipality boundary.
- Areas outside the official boundary are rendered white.
- Municipality-owned parcels fully outside the boundary remain visible as white shapes with red warning outlines.
- Parcels crossing the boundary show imagery and fill only for the portion inside the municipality, with an orange warning outline that remains visible over white areas.
- Inside and near-boundary parcels use separate visual styles.
- Omits titles, timestamps, statistics, legends, exception lists, footers, and other report text so the downloaded image contains only the geofence and in-boundary parcel map.
- Preserves the Google logo and attribution area.
- Uses a same-origin, authenticated, and throttled Google Static Maps request.
- Explains satellite download failures for Google access/configuration, quota, connectivity, and busy exports; failed images are not cached, and diagnostic logs exclude provider bodies and secret-bearing URLs.
- Uses a versioned map frame to prevent stale satellite imagery from becoming misaligned after boundary changes.
- Records a successful completed snapshot download in the audit trail without storing the full geometry.

## 9. Public QR land verification

- Public read-only land page opened from a farmer ID QR code.
- Random 40-character public token instead of a sequential farmer ID.
- Google Maps hybrid satellite view.
- Interactive pan, zoom, and map-type controls.
- Read-only parcel selection.
- Parcel area and declared farm-area display.
- Farmer registry ID, farmer name, and general farm location.
- Multiple parcel support.
- Mobile-friendly layout.
- Rate limiting of public requests.
- Search-engine indexing disabled.
- Browser and intermediary caching disabled.
- Contact numbers, birth dates, account details, assistance records, and other sensitive information are excluded.

## 10. Agriculture and fisheries assistance

The historical database name remains `rice_seed_distributions`, but the module supports both agriculture and fisheries assistance.

### Record management

- Create, view, edit, delete, search, filter, paginate, import, and export assistance releases.
- Link every release to a registered farmer.
- Copy a farmer identity and location snapshot into each historical release record.
- Municipality ownership and authorization checks.
- Municipal users automatically receive their assigned municipality.
- Provincial users select the municipality for new records and imports.

### Supported agriculture assistance

- Rice seed.
- Corn seed.
- Vegetable seed.
- Fertilizer or abono.
- Soil amendment.
- Other seeds and agricultural inputs.
- Custom assistance item, variety, specification, and notes.

### Supported fisheries assistance

- Tilapia fingerlings.
- Hito or catfish fingerlings.
- Bangus fingerlings.
- Carp fingerlings.
- Other fish species.
- Fish feed.
- Fishing gear.
- Aquaculture inputs.
- Other fisheries assistance.

### Quantities and reporting

- Kilograms, sacks, packs, grams, liters, milliliters, bottles, pieces, sets, rolls, boxes, and bundles.
- Fingerling releases require the piece unit.
- Kilogram reports exclude pieces, sacks, bottles, liters, and other incompatible units.
- Separate agriculture and fisheries reporting.
- Fisheries release totals and fingerling counts.
- NRP claimed area, claimed seed, lot series, crop establishment, sowing label, harvested area, production bags, planted variety, and seed class.
- Text, municipality, sector, category, identity, gender, eligibility, numeric-range, and date-range filters.
- Monthly release charts.
- Item, category, location, gender, age, eligibility, crop-establishment, yield-variety, seed-class, and municipality-area charts.
- Chunked filtered CSV export with spreadsheet-formula protection.
- NRP Excel import with municipality-scoped FFRS and RSBSA matching.
- Certified, Registered, and Not Specified seed classes, with imported legacy values kept editable.

### Assistance coverage map

- Open **Agriculture & Fisheries → Assistance coverage map** to compare recorded
  releases by municipality in an authorized province or independent-city scope.
- Filter by municipality, assistance type, exact program/sheet reference, planting
  year, and dry/wet season. Program and planting period currently come from rice
  distribution sheets; legacy and other releases remain available under All
  seasons / All years or Not recorded. Seasons are never guessed from dates.
- View release counts, distinct linked farmer records, and quantities separated
  by unit. Incomplete period, farmer-link, and quantity data are disclosed.
- Open the map on demand. Municipality colors represent recorded release counts;
  gray means no matching releases, not proof that the area received no assistance.
  Areas without a usable active boundary remain in the table.
- Individual farm parcels are not colored because releases have no explicit
  parcel link. The report does not calculate hectares or eligibility coverage.
- The table works without the map or JavaScript. Scope and role restrictions apply
  to both the report and the boundary endpoint; no records are changed.

### Rice Seed Distribution Sheet

- Group existing assistance releases into a printable distribution sheet for one programme reference and planting season.
- Create, edit, search, and delete municipality-owned sheets; a sheet holding releases must be emptied before it can be deleted.
- Record the planting season and year, an optional harvest season and year, an optional default seed-bag weight, and sheet notes.
- Attach a release to a sheet in the same municipality only; releases recorded before sheets existed stay fully editable without one.
- Record the declared rice area separately from the total farm area, and the seed-bag weight separately from the harvest bag weight.
- Record the number of seed bags; the released kilograms are computed from bags multiplied by bag weight and kept in the single existing total.
- Record the harvest season and year explicitly; they are never guessed from the planting season or the current date.
- Record data-privacy consent as yes, no, or not recorded, with not recorded as the default for anything never asked.
- Record Kalinga Package kits received and the name of a representative who collected on the farmer's behalf.
- View the sheet on screen and download the same sheet as a wide grouped Excel workbook.
- Grouped season headings built from the stored season and year, headers repeated on every printed page, a totals row, and a blank signature column for the recipient.
- Spreadsheet-formula protection on every exported cell.
- Sheet downloads and sheet changes appear in the audit trail.
- Municipal staff work only in their own municipality, provincial staff choose a municipality in their province, and System Owner and Super Administrator access stays read-only.
- Very large sheets are directed to the streaming CSV export instead of a single oversized workbook.

## 11. Animal Health services

The historical route and table names retain “anti-rabies” for compatibility, but the module supports general animal-health services.

### Supported services

- Vaccination.
- Deworming.
- Vitamins and supplementation.
- Treatment.

### Supported animals

- Dogs and cats.
- Cattle and carabao.
- Goats and sheep.
- Swine.
- Chickens, ducks, and turkeys.
- Horses and rabbits.
- Other farm animals.

### Animal-health records and reports

- Create, view, edit, delete, search, filter, and paginate service records.
- Owner or raiser information.
- Barangay and optional birthday.
- Animal species, breed, name, and color.
- Number of animals served.
- Product, medicine, vaccine, vitamin, or treatment used.
- Dosage and administration route.
- Diagnosis or reason for service.
- Service notes and administering staff.
- Service date and next follow-up date.
- Existing-owner lookup within the selected municipality.
- Previous animal or group suggestions for an owner.
- Filters by municipality, service type, species, owner, animal, product, diagnosis, barangay, and year.
- Total service, animal, owner, and animal-profile reporting.
- Service mix, species coverage, monthly activity, barangay, breed, and owner-age charts.
- Legacy anti-rabies records default safely to vaccination, Anti-rabies vaccine, and one animal when generalized fields are missing.
- Province-wide Animal Health workflow for Provincial Veterinary Office accounts.

## 12. Farmers’ cooperatives

- Create, view, edit, delete, search, filter, sort, and paginate cooperatives.
- Cooperative name, chairperson, contact number, address, and description.
- Municipality-owned cooperative records.
- Assign and synchronize cooperative members.
- Only farmers from the cooperative’s municipality can be assigned.
- Cooperative membership-change auditing.
- Prevent municipality transfer while a cooperative still has assigned members.
- Cooperative, member, populated-group, empty-group, and machinery totals.
- Formatted Excel export of assigned farmers.
- Standardized Laravel route-model binding for reliable edit, update, and delete actions.

## 13. Agricultural machinery inventory

- Municipality-specific machinery dashboard.
- Create, view, edit, delete, search, filter, sort, paginate, and export assets.
- Search-first inventory controls with quick views for available, in-use, attention, repair, and unassigned assets.
- Collapsible advanced filters and clear active-filter summaries.
- Responsive asset cards for phone and tablet workflows.
- Assign equipment to either a farmer or a cooperative.
- Holder must belong to the same municipality as the machinery.
- Municipality-unique asset codes.
- Machinery type and category.
- Brand, model, and serial number.
- Acquisition year, date, source, and cost.
- Equipment condition and availability.
- Current location.
- Service-hour tracking.
- Last- and next-maintenance dates.
- Maintenance and operational notes.
- Registered, available, and in-use asset totals.
- Farmer and cooperative holder statistics.
- Total acquisition value.
- Machinery-category and condition charts.
- Maintenance-attention queue.
- Automatic attention indicator for maintenance, repair, unserviceable status, and maintenance due within 30 days.
- Municipality-scoped farmer and cooperative holder lookup.
- Guided create/edit workflow with optional acquisition and notes sections, a live record summary, assignment feedback, and maintenance-date warnings.
- Chunked and formula-safe CSV export.

## 14. Backup Folder

The Backup Folder is a protected document repository. It is not an automatic database-backup scheduler.

- Upload one or multiple files.
- Maximum file size of 50 MB per file.
- Web-page, image-markup, and program files such as .html, .svg, .js, .php, and .exe are refused, because they can run inside the browser instead of being read as documents.
- Folder names accept letters, numbers, spaces, dashes, underscores, and / only.
- Private local storage.
- Municipality ownership for every file.
- Uploader, folder, notes, MIME type, file size, and SHA-256 hash tracking.
- Filename, folder, note, and hash search.
- Contains, starts-with, ends-with, and exact search modes.
- Municipality, folder, uploader, extension, date, and file-size filters.
- Sorting and filtered storage totals.
- Authorized preview, inline viewing, download, editing, and deletion.
- PDF, image, text, spreadsheet, and supported document preview.
- Any other stored file, including one saved before the upload rules above, downloads instead of opening in the page, so a saved file can never run as part of the system.
- Text-like file editing.
- In-browser `.xlsx` editing.
- File size and SHA-256 recalculation after an edit.
- Physical stored-file removal when its database record is deleted.
- Municipal users only access their assigned municipality’s files.
- Provincial agriculture staff can work across municipalities.
- System Owners, Super Administrators, and Provincial Veterinary Office accounts are denied access.

## 15. User management

- Account listing, search, filtering, and pagination.
- Create and edit accounts.
- Activate and deactivate accounts.
- Assign and change supported roles.
- Assign municipalities to municipal roles; their province follows their municipality.
- Assign an active province to Super Admin, Provincial Staff, and Provincial Veterinary Office accounts.
- Clear municipality assignment for provincial roles.
- Reset passwords securely.
- Require password confirmation, at least twelve characters and a breached-password check in normal account forms.
- Prevent more than one active Municipal Head for the same municipality.
- Prevent self-deletion.
- System Owners can manage provincial Super Admin accounts. Super Admins cannot manage other Super Admins or the System Owner.
- Protect every System Owner account and prevent users from changing their own role, province, municipality, or active status.
- Municipal Heads can manage only Municipal Staff from the same municipality.
- Super Administrators can manage only provincial staff, veterinary staff, municipal heads, and municipal staff in their assigned province.
- Only the System Owner can choose a different province for a provincial account.
- Inactive Super Admin accounts prepared during setup require a new password before activation.
- Create province-wide Animal Health-only Provincial Veterinary Office accounts.

## 16. Audit trail

- System Owner audit dashboard across all provinces, and Super Administrator audit dashboard limited to the assigned province.
- Province ownership is saved with each audit event and does not follow later account reassignment. Global, unknown-scope, and cross-province reassignment events are visible only to the System Owner.
- Activity totals for today and the previous seven days.
- Security, failed-login, blocked-login, lockout, repeated-failure, timeout, and deletion alerts.
- When one device produces a long run of unsuccessful sign-ins, the trail keeps the first 20 entries and then records a single entry saying the rest of the 15-minute window was suppressed, so a flood of generated entries cannot hide genuine activity. Successful sign-ins are always recorded.
- Search and filtering by event, module, municipality, actor, and local date range.
- Before-and-after values for supported changes.
- Request method, URL, IP address, browser information, actor, role, and municipality context.
- Audit records for farmers, parcels, assistance releases, Animal Health, cooperatives, machinery, backups, users, and municipalities.
- Authentication and session-timeout events.
- Export events.
- Cooperative membership changes.
- Municipality boundary creation, import, update, activation, archival, and replacement.
- Completed municipality snapshot downloads.
- Filtered CSV export using a stable maximum audit ID.
- UTC timestamp storage with Philippine-time display.
- Automatic removal of passwords, tokens, secrets, profile-photo paths, and other protected values from audit data.
- Audit failures do not interrupt the user’s main operation.

## 17. Weather and agricultural advisories

- Weather panel embedded directly in the farmer Parcel Map.
- Municipality-specific forecast selection.
- Municipal users are locked to their assigned municipality.
- Provincial and Super Administrator users can select an active municipality in their assigned province; the System Owner can select across provinces.
- The panel follows the municipality of the currently selected farmer.
- Current temperature and apparent temperature.
- Humidity and wind conditions.
- Seven-day rainfall total.
- Peak rain probability and wind-gust indicators.
- Three-day outlook.
- Rule-based farming guidance for heavy rainfall, rain probability, strong wind, heat, and irrigation review.
- Cached Open-Meteo forecasts.
- Last-known forecast fallback during provider outages.
- Manual refresh with anti-request-stampede locking.
- Direct PAGASA weather, tropical cyclone, flood, and agricultural weather links.
- Clear distinction between system guidance and official PAGASA or disaster-risk warnings.

## 18. Import, export, and reporting capabilities

| Module | Import | Export or download |
| --- | --- | --- |
| Farmers | Excel parcel-listing and outside-LGU sheets | Farmer ID, profile data, and registry views |
| Farm parcels | KML, KMZ, XML, and selected-farmer imports | Parcel sheet and high-resolution PNG |
| Municipality geofences | KML, KMZ, GeoJSON, JSON, and XML | Municipality land snapshot PNG |
| Agriculture and fisheries assistance | NRP Excel workbook | Filtered CSV |
| Cooperatives | Member assignment workflow | Formatted member Excel workbook |
| Machinery | Holder selection and record entry | Filtered CSV |
| Backup Folder | Multiple protected file upload | Authorized original-file download |
| Audit trail | Automatic system events | Filtered CSV |

## 19. Data-quality and duplicate-control features

- Municipality-scoped farmer and record matching.
- FFRS and RSBSA matching during imports.
- Repeated parcel rows are aggregated into one farmer when matching rules identify the same person.
- Related records must belong to the same municipality.
- Farmer deletion is blocked when protected dependencies exist.
- Cooperative municipality changes are blocked while members are assigned.
- Machinery asset codes are unique within each municipality.
- One active Municipal Head per municipality.
- One active municipality geofence per municipality.
- Dashboard warnings for missing FFRS numbers, locations, municipality assignments, and parcel mapping.
- Geofence review for misplaced, crossing, near-boundary, and invalid parcels.

## 20. Concurrent-user and performance features

- Parcel hover cards reuse their measured size and move once per animation frame, without background blur or unnecessary geometry scans for stored areas.
- Geofence overview uses one polygon per component; extra contrast outlines return when zoomed in or a municipality is selected. Original boundaries remain exact.

- Per-account request synchronization for state-changing operations.
- Shared record locks when two staff members try to change the same record.
- Municipality-level locks for boundary and parcel mutations.
- Optimistic record-version tokens on normal edit forms.
- Stale-edit rejection instead of silently overwriting newer changes.
- Database row locking inside retried transactions.
- Duplicate form-submission protection in the browser.
- Atomic multi-record transactions where required.
- Chunked large CSV exports.
- Stable export boundaries so new records do not unexpectedly enter an export already in progress.
- CSV formula-injection protection.
- Lazy municipality parcel loading on maps.
- Cached weather, active-boundary, map-frame, and Static Maps data.
- Atomic cache locks that prevent simultaneous requests from overloading external providers.
- Pagination for large lists.
- Server-side municipality filtering before search, charts, totals, lookups, and exports.

## 21. External services and integrations

- Google Maps JavaScript API for authenticated maps and the public QR map.
- Google Map ID support.
- Google Maps Static API for satellite PNG exports.
- Open-Meteo for municipality-level weather forecasts.
- PAGASA links for official Philippine weather and agricultural bulletins.
- Nominatim and OpenStreetMap for authenticated geocoding.
- PhpSpreadsheet for Excel imports and exports.
- Endroid QR Code for farmer land-verification QR codes.
- Chart.js for dashboard and module charts.
- DataTables, Tom Select, Handsontable, SheetJS, CodeMirror, JSZip, and document-preview libraries in applicable interfaces.

## 22. Privacy and security principles

- Municipality data separation is enforced by `municipality_id` rather than display text.
- Interface visibility is never treated as the only security control.
- Laravel policies protect record-level actions.
- CSRF protection is used for state-changing browser requests.
- Public QR pages expose only limited, reviewed farmer and land information.
- Farmer profile photos and Backup Folder files use private storage.
- API and public-map requests are rate-limited.
- Server-side API proxies keep private service keys out of downloadable content and browser scripts when appropriate.
- Spreadsheet exports are protected against formula injection.
- Audit records exclude passwords, tokens, secrets, and protected paths.
- Random farmer public tokens prevent predictable public land URLs.

## 23. Important operational requirements

- The municipality-boundary migration must be applied before using geofencing.
- General agriculture/fisheries input fields must be migrated before using `input_category` and `quantity_unit` reports.
- The machinery migration must be applied before using Machinery Inventory.
- The Animal Health extension migration must be applied before using generalized services and animal counts.
- A Google Maps JavaScript key and Map ID are required for interactive authenticated maps.
- A Google Maps Static API key is required for satellite PNG and municipality snapshot exports.
- `APP_URL` must match the deployed HTTPS domain.
- The application timezone should remain UTC for storage, while `APP_DISPLAY_TIMEZONE` controls local display.
- A shared atomic cache such as Redis is recommended before running the application on multiple servers.
- A single Hostinger server can use the shared file cache as long as all PHP workers use the same filesystem.

## 24. Current scope clarifications

- The Backup Folder stores protected files but does not automatically schedule database backups.
- Weather guidance is advisory and is not an official PAGASA warning.
- The farmer registry card is not a replacement for a national government ID.
- The public parcel map is for verification and does not expose confidential farmer records.
- Office accounts have no public self-registration, email verification, forgotten-password, or user-facing password-reset workflow. Farmer portal recovery is assisted by authorized agriculture staff after identity verification.
- The project still depends on an approved legacy baseline SQL schema for a completely new installation because the repository does not yet contain migrations for every original core table.

---

**Document status:** Updated for the current Agriculture Information System build as of September 2026. Update this catalog whenever a role, module, workflow, integration, or security rule changes.

### Assistance form usability

The assistance entry/edit form provides a searchable beneficiary selector with names, FFRS and RSBSA identifiers, a scoped beneficiary count, municipality-first guidance, and no-match messages. Dropdowns open above surrounding form sections. Larger controls, clearer profile/summary text, and stacked mobile fields improve entry.

### Local Baguio and Benguet account setup

The local setup now matches the four Hostinger Baguio/Benguet accounts and their roles. Baguio City is separately supervised; the Benguet Super Admin cannot access its records. The Benguet office Head Agriculturist is limited to the legacy Benguet office workspace, and the La Trinidad staff account is limited to La Trinidad.

## Farmer portal — September 20, 2026

Farmers can sign in separately from office users to see their own profile, farm parcels, recorded crops by year and wet/dry season, and assistance history. Each account belongs to one verified farmer record. Other farmers and office modules remain inaccessible.

Authorized agriculture staff issue access through **Farmer profile/history → Farmer portal access**, after verifying identity. A single-use code expires in 24 hours; the farmer chooses their own password. A stable AgriGOV login ID always works; a recorded RSBSA number is accepted for sign-in only when unique. Birthdays cannot be used to activate or recover accounts. This does not connect to a central RSBSA login service. The September 24 local update shares the office and farmer login layout, with a clear switch between both. Activation prompts are removed from the login screen; this interface update awaits deployment.

Staff can reissue access for recovery or disable it. Both revoke old access. There is no automatic portal account creation, SMS delivery, public registration, farmer editing, assistance application, correction-request queue or digital-ID download in this first version. Contact the agriculture office for corrections. Every saved farmer has an AgriGOV ID; issuing portal access enables activation with that same ID. The primary-ID display/search update is committed and awaits deployment; see [AgriGOV farmer IDs](docs/AGRIGOV_FARMER_IDS.md).

The read-only satellite map loads one owned parcel at a time. The portal has mobile layouts, pagination, missing-record messages, sign-out and inactivity expiration. Installation requires one additive account-table migration. See `docs/FARMER_PORTAL.md` for activation and deployment details. Deployed to Hostinger on September 20, 2026, with 55 read-only checks passing. An approved real-farmer pilot remains pending; see `docs/FARMER_PORTAL_DEPLOYMENT_2026_09_20.md`.

### Map label visibility

Farmers and Municipality geofences offer a Map labels: On/Off button to hide Google's place and road labels and show satellite imagery. Parcels, geofences and AgriGOV municipality names remain visible. Labels start on when reopening the page. Deployed to Hostinger September 21, 2026 in `a751355`; see docs/MAP_LABEL_VISIBILITY.md.

Farmer cards show distinct imported parcel addresses separated by a slash. Farm location never falls back to a residence address; missing parcel addresses are labeled as not recorded. The back supports wrapped addresses in the preview and digital/PNG card, with a full address list above the preview and on the printed sheet. Oversized card text refers to that list; oversized digital/PNG backs show guidance instead of silently omitting addresses. See `docs/FARMER_CARD_PARCEL_ADDRESSES.md` for verification and deployment status.

CALABARZON reference coverage was deployed to Hostinger through GitHub commit `496b706` on September 22: 142 municipality/city geofences across Batangas, Cavite, Laguna, Quezon, Rizal and the separate Lucena City scope. Run the explicit transaction-backed `CalabarzonBoundarySeeder` only after a verified backup; it preserves existing workspaces, styles and operational data and stops on conflicts. The explicit `region-access:configure --owner=<id> --region=region4a` command links the five provinces and separate Lucena City scope without changing other regions or issuing accounts. Source attribution, water-inclusive Cavinti and Noveleta area conventions, validation and verified deployment are documented in [docs/CALABARZON_BOUNDARY_SOURCES.md](docs/CALABARZON_BOUNDARY_SOURCES.md).

Three inactive CALABARZON provincial Super Admin accounts were prepared for Cavite, Batangas and Laguna using the official office addresses `agriculture.cavite@yahoo.com`, `agri@batangas.gov.ph` and `faesopaglaguna@gmail.com`. Each requires a password and activation by an authorized owner; no invitation email was sent.
