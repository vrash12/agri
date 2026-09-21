# Ramos full registry source rows

The Ramos workbook backfill retains all 32 columns from `PARCEL LISTING` and `OUTSIDE LGU` as municipality-owned source rows linked to the existing farmer by the exact FFRS identifier.

## Data behavior

- The active sheets contain 3,129 source rows for 1,656 distinct FFRS farmer profiles.
- The 245 `DELISTED` rows are retained separately with no active farmer link. Their deceased, duplicate and inactive statuses remain source data and never reactivate a profile.
- Every original row remains separate. Repeated parcel and commodity rows are not collapsed.
- The source workbook SHA-256, sheet name and row number form the idempotency key.
- Original RSBSA values remain in the source payload even when the canonical farmer RSBSA is blank because the workbook number conflicts with another FFRS profile.
- The source row does not change the farmer's canonical name, FFRS, RSBSA, municipality or numeric database relationships.
- `DELISTED` remains excluded from active farmer profiles and is available only as a separate inactive-record review source.

## Deployment

1. Take and verify a private database backup.
2. Deploy the application commit through GitHub.
3. Run `php artisan migrate --force` during maintenance mode.
4. Run the private checksum-locked Ramos backfill outside the public application directory.
5. Verify 3,129 linked active rows, 245 unlinked delisted rows, 1,656 represented farmers, municipality ownership, source hashes, and unchanged pre-existing farmer rows.
6. Clear caches, compile Blade views and restore the application.

The workbook and generated source-row manifest contain personal information. Keep them outside the repository and public web directories.
