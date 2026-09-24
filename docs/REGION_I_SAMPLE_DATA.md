# Region I demonstration records

The owner requested live sample farmers, seed distribution and hypothetical mapped land. The selected demonstration workspaces are Bacarra (Ilocos Norte), Narvacan (Ilocos Sur) and Bacnotan (La Union). Each receives four farmers, three plots per farmer, one rice-seed release per farmer and one distribution batch: **12 farmers, 36 plots, 12 releases and three batches** overall.

## Classification and reporting

Every farmer is named `Sample 01–04 Synthetic Farmer`; each has an explicitly synthetic `SAMPLE-R1-V1-M<municipality-id>-F<01–04>` marker. The original import created no invented official RSBSA numbers, birthdays, contact details, farmer accounts or office accounts. The separately authorized testing accounts described below were added afterward. Consent stays **Not recorded**, all eligibility flags remain false and no harvest results are claimed.

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

## Verified live status — September 24, 2026

The implementation was pushed through GitHub and installed on Hostinger with commit `55342117924467676865292570c4646f4b36515c`. A private verified backup was taken before the write (21 tables, 5,375,076 bytes; checksum retained privately). The live preview and apply completed for Bacarra, Narvacan and Bacnotan. Postflight preserved all 91 users and 818 geofences; the only operational additions were 12 farmers, 36 plots, 12 releases and three batches, plus the expected audit events and one owner-only receipt. A repeat `--apply` returned `unchanged`, and all 36 plots classified `inside` their active boundaries.

## Verified sample portal access — September 24, 2026

The owner explicitly authorized the requested testing credential for these 12 live synthetic farmers only. Preflight verified all exact markers, names, municipality ownership, the complete sample graph and its original import receipt. None had a portal account. A private verified backup covered 21 tables and 11,757 rows before the change (SHA-256 `ea81d4d644b1a9654265e39a2d5a4bdc6624d7e49a7a8be518e17776326df4ba`).

A one-time private CLI operation created 12 active, activated `farmer_portal_accounts` using canonical AgriGOV IDs and individually salted bcrypt hashes. It used a transaction, farmer/account locks, freshness checks, and a required owner-only audit receipt (`6088`). All 20 non-audit table fingerprints, excluding only the targeted portal accounts, matched the preflight after setup and sign-in verification. All 91 office users, 818 geofences and operational records were preserved.

All 12 sign-ins passed through the live HTTPS login form with CSRF. Each account reached its own profile, parcel and assistance pages with non-storable responses and signed out successfully; 12 sign-in and 12 sign-out audit events were verified. The sample graph remained unchanged. The private one-time administration script was removed afterward. Runtime remains `bd75ced`; this operation did not deploy the pending richer portal screens.

No credential is recorded here or in the repository. This operation is a scoped demonstration exception, not a password default or an automatic account seeder. Normal staff-assisted activation, password requirements, account scope and throttles are unchanged. No real-farmer access, email, harvest or seasonal-crop record was added.
