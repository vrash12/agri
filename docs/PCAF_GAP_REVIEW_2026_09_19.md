# PCAF feedback: remaining work as of 19 September 2026

## Basis and limits

Compared the supplied IICTU letter with the current working tree based on main
`5d6f078`, the existing PCAF roadmap, and the recorded Hostinger release of that
commit. This is a code and documentation review, not a new full live acceptance
test. The letter gives recommendations following a review of visible features;
it is neither a formal acceptance checklist nor a security certification.

Several roadmap paragraphs are historical. In particular, production reporting
is no longer missing: `HarvestRecordController`, `/harvest-records`, and
`DashboardMetrics::productionByCommodity` provide entry, export, and yearly
commodity reporting. The deployment receipt records these as installed, but
actual production submissions were not tested in that release.

## The eight suggested graphs

| Recommendation | Current implementation | Remaining work or qualification |
| --- | --- | --- |
| Farmers by municipality | Dashboard bar chart using scoped farmer counts. | Verify with office data and all relevant roles in the walkthrough. |
| Production trend by major commodity | Harvest entry and yearly commodity/unit series; rice releases can project harvest records. | Agree which commodities staff must record and the reporting cycle; verify real data completeness. Do not substitute assistance quantities for harvest. |
| Assistance and beneficiaries by program or municipality | Dashboard charts by category and municipality; distinct beneficiaries separated from release transactions. | Validate office totals and reporting periods. |
| Farm/parcel mapping coverage | Counts farmers with and without at least one mapped parcel. | It cannot report unmapped parcel counts without a complete parcel inventory. Confirm this definition with the office. |
| Animal-health services by month or municipality | Dashboard now includes monthly service-type bars with a reporting-year selector. | Implemented and checked locally; Hostinger deployment and office acceptance remain pending. |
| Fisheries activities, beneficiaries, fingerlings | Release/beneficiary charts plus selected-year fingerling quantities by active municipality, in pieces. | Implemented and checked locally; verify office totals and deploy. Incomplete quantities and undated releases are disclosed separately. |
| Machinery availability/condition by type | Separate condition and availability stacked bars now group current inventory by equipment type. | Implemented and checked locally; deploy and verify actual inventory classifications. |
| Municipality comparison including production | Adds selected-year commodity/unit production to the existing independent indicators. | Implemented and checked locally; deploy and verify harvest completeness. Mapping still counts farmers, not parcels; no composite ranking is invented. |

Evidence: `app/Support/DashboardMetrics.php`, `resources/views/dashboard.blade.php`,
`resources/views/anti_rabies_vaccinations/index.blade.php`, and harvest routes in
`routes/web.php`.

The dashboard follow-up is documented in `DASHBOARD_ENHANCEMENTS_2026_09_19.md`.
It does not close the separate operational walkthrough and data-completeness work.

## Remaining work across the broader recommendations

1. **Verification and office demonstration.** Roadmap Milestone 5 remains open:
   role-by-role walkthrough, scoped access, concurrent edits, realistic empty/error
   states, keyboard accessibility, phone/tablet use, rural connection behavior,
   synthetic demonstration records, and a checklist linked to the letter. Arrange
   the demonstration date with PCAF when ready; no message has been sent.
2. **Automated reporting.** On-demand exports exist. There is no scheduled report
   generation/delivery workflow: the scheduler currently schedules database backups.
   Agree report formats, period, recipients and delivery method first. Farmer
   directory, animal-health register and dashboard exports also lack dedicated
   routes; their required formats should be agreed before implementation.
3. **Mobile collection.** Online responsive forms exist. The farmer assistance
   history still has a 980px minimum-width table. Improve its phone presentation and
   verify the maps and entry workflows on actual devices. Offline capture and sync
   are absent; the letter does not explicitly require offline operation, so confirm
   that need before designing device storage and conflict resolution.
4. **GIS decision support.** Parcel mapping, municipality geofences, spatial
   validation and snapshot exports exist. The new **Crops by season** layer is
   deployed to Hostinger with explicit parcel/year/season records, crop colors,
   filtering, and a legend. A synthetic local Google 3D walkthrough passed;
   64 read-only production checks passed on 2026-09-19. Signed-in production
   browser submissions and office-data acceptance remain pending. See
   `SEASONAL_PARCEL_CROPS.md`. The municipality-level assistance coverage report is
   now implemented locally with a lazy map, explicit rice-sheet program/planting
   period filters, and missing-data disclosure. Deployment and office acceptance
   remain pending; see `ASSISTANCE_COVERAGE_MAP.md`. The production map layer and
   any parcel-level assistance layer remain future work. Farmer-level records
   must not be assigned to particular parcels without evidence. Planning boundaries
   still need validation by the responsible office before official use.
5. **Data quality and standardization.** Existing validation, controlled units,
   duplicate-claim warnings, profiles and assistance histories are implemented.
   Decide whether FFRS/RSBSA uniqueness is global or municipality-specific; current
   validation is global. Obtain municipality-specific barangay references before
   treating free-text location groups as a complete barangay report. Verify actual
   harvest and other operational data, instead of assuming an implemented chart
   means complete records.
6. **Security verification and reported defects.** Authorization, tenancy,
   throttling, auditing, and server-side logout are implemented. The owner reported
   that Back showed a signed-in page after logout. That symptom alone does not prove
   the session survived; verify both stale display and server access. The opacity
   editor/drawing defect was reproduced in local JavaScript tests. Fixes for both
   issues passed 24 JavaScript and 9 PHP tests and were deployed at 08:53 UTC.
   Both served JavaScript files match the tested local copies. Signed-in live
   interaction checks remain pending; see `OPACITY_SESSION_FIX_2026_09_19.md`.

## Practical order

1. Close the reported map and logout defects and verify live behavior.
2. Finish the remaining chart breakdowns, farmer-history mobile layout, and office
   decisions about reporting and identifiers.
3. Implement the agreed reporting/GIS additions and assess field collection.
4. Complete Milestone 5 and prepare the PCAF demonstration.

Do not describe the entire system as unfinished: the registry, assistance history,
harvest entry, most analytics, GIS foundation and scoped access are already built.
Do not call Milestones 1–4 fully accepted based only on the roadmap's summary labels.
