# Agriculture Information System — Complete Feature Catalog

## 1. System overview

The Agriculture Information System is a province-wide Laravel and MySQL platform for managing agricultural records across the Provincial Agriculture Office and participating municipal agriculture offices. It combines farmer registration, GIS land mapping, agricultural and fisheries assistance, animal-health services, cooperative management, machinery monitoring, protected files, dashboards, reporting, user administration, and audit records in one municipality-aware system.

The application is designed for multiple offices using the system at the same time. Every operational record is assigned to a municipality, and users only receive the records and actions allowed by their role.

## 2. Supported user roles

| Role | Main access |
| --- | --- |
| Super Administrator | Province-wide operational viewing, municipality geofence management, user management, system security, and audit trail |
| Provincial Agriculture Staff | Province-wide operational access with the ability to select a municipality when creating or importing records |
| Provincial Veterinary Office | Province-wide access limited to Animal Health services |
| Municipal Head Agriculturist | Full operational access for the assigned municipality and management of municipal staff accounts |
| Municipal Staff | Operational access limited to the assigned municipality |

### Role and municipality safeguards

- Municipal accounts can only view and manage records belonging to their assigned municipality.
- Provincial agriculture staff can work across municipalities but must select the correct municipality for new records and imports.
- Provincial Veterinary Office accounts can only access the Animal Health module.
- Super Administrators have read-only oversight of ordinary operational records.
- Only Super Administrators can manage official municipality geofences.
- Super Administrators cannot access the Backup Folder.
- Municipal Heads can only manage Municipal Staff accounts from their own municipality.
- Every protected record is checked again on the server before it can be viewed, changed, deleted, downloaded, previewed, or exported.

## 3. Authentication and session security

- Secure email and password login.
- Optional “Remember me” login.
- Passwords are stored as one-way hashes and cannot be retrieved as plaintext.
- Session ID regeneration after a successful login.
- Active-account and supported-role validation.
- Municipality assignment and municipality status validation for municipal accounts.
- Last-login timestamp tracking.
- Successful, failed, and blocked login auditing.
- Secure logout with session invalidation and CSRF-token regeneration.
- Automatic logout after 15 minutes of inactivity.
- One-minute session-expiration warning.
- Activity synchronization across browser tabs.
- Server-side idle-session enforcement even when browser JavaScript is unavailable.
- Throttled activity heartbeat while a user is active.

## 4. Dashboard and analytics

### Municipal and provincial operations dashboard

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

### Super Administrator dashboard

- Province-wide municipality comparison.
- Farmer totals per municipality.
- Mapping coverage and mapped hectares per municipality.
- Distribution, animal-health, cooperative, machinery, and staffing statistics.
- Identification of municipalities without a Municipal Head.
- Identification of municipalities without farmer records.
- Identification of municipalities behind on parcel mapping.
- Monitoring of operational records that have no assigned municipality.
- Province-wide read-only oversight without operational data-entry controls.

## 5. Farmer registry

- Create, view, edit, search, filter, paginate, and delete farmer profiles.
- Municipality workspace selector for provincial users.
- Municipality-specific registry totals and map data.
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
- Municipality-scoped parcel loading prevents parcels from different municipalities from being mixed.
- Authorized selected-farmer KML and KMZ import.
- Server-side KML and XML bulk parcel import for one municipality.
- Import matching by parcel code, full name, surname and barangay, unique surname, and controlled fallback matching.
- Imported KML colors are preserved when available.
- Printable parcel information sheet.
- High-resolution parcel PNG export.
- Same-origin Google Static Maps proxy for satellite exports.

## 8. Official municipality geofencing

### Boundary management

- Province-wide Google Maps geofence workspace.
- Municipality search and selection.
- Display of active, draft, and archived boundary records.
- Distinct boundary colors and municipality labels.
- Fit-to-boundary and province reset controls.
- Active official geofences are also displayed beneath parcels in the Farmers 3D map, with a show/hide control and scope-aware camera fitting.
- Super Administrator-only boundary creation and modification.
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
- Safe simplification of oversized geometry.
- Configurable maximum geometry size.
- Detection of overlapping active municipality boundaries.
- Shared municipality edges are allowed when they do not create an actual overlap.
- Optimistic locking and municipality-level mutation locks for concurrent edits.

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
- Guided create/edit workflow with completion progress, a live record summary, assignment feedback, and maintenance-date warnings.
- Chunked and formula-safe CSV export.

## 14. Backup Folder

The Backup Folder is a protected document repository. It is not an automatic database-backup scheduler.

- Upload one or multiple files.
- Maximum file size of 50 MB per file.
- Private local storage.
- Municipality ownership for every file.
- Uploader, folder, notes, MIME type, file size, and SHA-256 hash tracking.
- Filename, folder, note, and hash search.
- Contains, starts-with, ends-with, and exact search modes.
- Municipality, folder, uploader, extension, date, and file-size filters.
- Sorting and filtered storage totals.
- Authorized preview, inline viewing, download, editing, and deletion.
- PDF, image, text, spreadsheet, and supported document preview.
- Text-like file editing.
- In-browser `.xlsx` editing.
- File size and SHA-256 recalculation after an edit.
- Physical stored-file removal when its database record is deleted.
- Municipal users only access their assigned municipality’s files.
- Provincial agriculture staff can work across municipalities.
- Super Administrators and Provincial Veterinary Office accounts are denied access.

## 15. User management

- Account listing, search, filtering, and pagination.
- Create and edit accounts.
- Activate and deactivate accounts.
- Assign and change supported roles.
- Assign municipalities to municipal roles.
- Clear municipality assignment for provincial roles.
- Reset passwords securely.
- Require password confirmation and a minimum of eight characters.
- Prevent more than one active Municipal Head for the same municipality.
- Prevent self-deletion.
- Prevent deletion of a Super Administrator through the normal controller.
- Municipal Heads can manage only Municipal Staff from the same municipality.
- Super Administrators can manage the broader account list.
- Create province-wide Animal Health-only Provincial Veterinary Office accounts.

## 16. Audit trail

- Super Administrator-only audit dashboard.
- Activity totals for today and the previous seven days.
- Security, failed-login, blocked-login, timeout, and deletion alerts.
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
- Provincial and Super Administrator users can select an active municipality.
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
- There is currently no public self-registration, email verification, forgotten-password, or user-facing password-reset workflow.
- The project still depends on an approved legacy baseline SQL schema for a completely new installation because the repository does not yet contain migrations for every original core table.

---

**Document status:** Updated for the current Agriculture Information System build as of September 2026. Update this catalog whenever a role, module, workflow, integration, or security rule changes.
