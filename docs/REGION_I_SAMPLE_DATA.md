# Region I demonstration records

The owner requested live sample farmers, seed distribution and hypothetical mapped land. The selected demonstration workspaces are Bacarra (Ilocos Norte), Narvacan (Ilocos Sur) and Bacnotan (La Union). Each receives four farmers, three plots per farmer, one rice-seed release per farmer and one distribution batch: **12 farmers, 36 plots, 12 releases and three batches** overall.

## Classification and reporting

Every farmer is named `Sample 01–04 Synthetic Farmer`; each has an explicitly synthetic `SAMPLE-R1-V1-M<municipality-id>-F<01–04>` marker. There are no invented official RSBSA numbers, birthdays, contact details, farmer accounts or office accounts. Consent stays **Not recorded**, all eligibility flags remain false and no harvest results are claimed.

Plots are hypothetical rectangles of approximately 0.25 hectares each, about 0.75 hectares per farmer and 9 hectares overall. They must fit strictly inside the currently active municipality reference, avoid holes and avoid existing/generated parcels. Administrative containment does not establish real farmland, ownership, survey accuracy or agricultural suitability. Names explicitly say **Hypothetical**. No geofence is changed or created.

Each synthetic wet-season 2026 batch contains illustrative releases of 1, 2, 2 and 3 bags at 20 kg per bag: **160 kg per municipality, 480 kg overall**, dated September 24, 2026. The example rice variety/class and irrigated ecosystem are demonstration assumptions. Release and batch notes say that no real farmer, surveyed land, consent, entitlement or seed delivery is represented.

**Samples appear in operational listings, maps, distribution sheets and dashboard totals.** The application has no separate demo-data exclusion switch. These records are for demonstration and must not be counted as evidence of actual program delivery.

## Explicit command

`demo:region1` is not part of `DatabaseSeeder`, migrations, scheduled work or automatic deployment. It requires an existing active System Owner and exactly three active municipalities in distinct active Region I provinces. No membership or ownership is reassigned.

```sh
# Read-only database preview (cache locks are still acquired).
php artisan demo:region1 --owner=OWNER_ID --municipality=BACARRA_ID --municipality=NARVACAN_ID --municipality=BACNOTAN_ID

# Only after owner authorization, reviewed preview and a verified private backup.
php artisan demo:region1 --owner=OWNER_ID --municipality=BACARRA_ID --municipality=NARVACAN_ID --municipality=BACNOTAN_ID --apply
```

The command uses the shared plotting/boundary locks and a retried database transaction. All placement checks precede inserts. Existing rows are never updated or deleted. Marker collisions, partial cohorts, inactive/mismatched scopes, missing/ambiguous boundaries or an unavailable required audit receipt abort the complete import. The geometry helper bounds candidates, boundary detail and existing-parcel input; it fails instead of ignoring parcels or simplifying boundaries.

Normal model audit events are attributed to the authorizing owner. A required owner-only import receipt stores counts, scope IDs and a digest, never raw tokens or credentials. Repeating the same complete unchanged cohort is a no-op, including timestamps and audit events. Edits, partial deletion, changed boundaries or a different selection require human review; reruns do not repair or overwrite the data.

## Verification and deployment

Focused tests use an isolated SQLite in-memory database. Coverage includes ownership, existing-row preservation, exact relationships and units, geometry, rollback, default preview, repeat safety, audit failure, and existing cross-province policies. Run PHP 8.1–8.3, Pint on changed PHP paths and `git diff --check`. A development checkout whose vendor directory is a junction must override autoload mappings for the current checkout when testing; do not mutate another checkout's shared dependencies.

No migration, dependency update or public asset change is required. Deploy through GitHub push followed by Hostinger `git pull --ff-only origin main`. Verify supported PHP, preserve server changes, take a private verified database backup, refresh application caches and run the preview before the explicit import. Compare every pre-existing row and all accounts/geofences afterward; verify all three workspaces and a zero-change rerun. Keep backup contents, environment values and connection credentials outside source control and responses.

Status: implementation and verification in progress; live insertion has not yet been performed.
