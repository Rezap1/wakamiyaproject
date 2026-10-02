# WMS MySQL migration M1

M1 creates a local MySQL copy for migration validation. It does not change
`DB_CONNECTION`, replace the Google Sheets repositories, or cut production
runtime over to MySQL.

## Local configuration

Set these values only in the local `.env` (never commit that file):

```dotenv
WMS_MIGRATION_DB_HOST=127.0.0.1
WMS_MIGRATION_DB_PORT=3306
WMS_MIGRATION_DB_DATABASE=wms_mysql
WMS_MIGRATION_DB_USERNAME=your-local-user
WMS_MIGRATION_DB_PASSWORD=your-local-password
```

Run `php artisan config:clear` after adding or changing these values.

When the local Laravel `DB_*` variables already point exactly to `wms_mysql`,
the dedicated connection may reuse those credentials. `WMS_MIGRATION_DB_*`
always takes precedence. The importer still uses the separately named
`wms_migration` connection and applies the same exact-database guard.

An intentionally empty local password is supported when it is explicitly
present in `.env`. The command refuses a missing variable, a non-local/test
`APP_ENV`, a configured database other than `wms_mysql`, or a connection whose
`SELECT DATABASE()` result is not exactly `wms_mysql`.

## Run M1

```shell
php artisan wms:migrate-sheets-to-mysql --migrate
```

The `--migrate` option runs only migrations under
`database/migrations/wms_mysql` and only after the destination guard passes.
The importer then reads every active sheet with the Google
`spreadsheets.readonly` scope, upserts by the existing canonical business ID,
re-reads the source to detect snapshot drift, and writes a reconciliation
report under `storage/app/migration-reports/`.

Other safe modes are:

```shell
php artisan wms:migrate-sheets-to-mysql --dry-run
php artisan wms:migrate-sheets-to-mysql --reconcile-only
```

Neither mode changes Google Sheets. `--dry-run` also avoids business-row writes
to MySQL; `--reconcile-only` compares the current source and target.

## Constraint strategy

Canonical IDs are primary keys except on `MASTER_SYSTEM_SETTING`, where the
live source currently contains repeated `Setting_ID` values. That table uses a
technical source-row identity so every source row is retained and reruns stay
idempotent. `Setting_ID` remains indexed and unchanged.

Foreign-key candidates are indexed and reconciled, but FK constraints are not
enabled in M1 because the live snapshot contains historical orphan references
and can continue changing before cutover. The importer never deletes, dedupes,
or repairs those records. FK activation belongs to a later explicitly approved
stage after a stable cutover snapshot.

JSON-like historical fields use `LONGTEXT`. Valid JSON is preserved byte for
byte; invalid historical JSON is also preserved and reported instead of being
discarded. Money uses `DECIMAL(20,4)`, never floating-point SQL types.
