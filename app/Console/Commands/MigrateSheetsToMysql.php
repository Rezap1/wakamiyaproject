<?php

namespace App\Console\Commands;

use App\Services\Migration\GoogleSheetsReadOnlySource;
use App\Services\Migration\MigrationEngine;
use App\Services\Migration\ValueNormalizer;
use App\Support\Migration\WmsMigrationManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class MigrateSheetsToMysql extends Command
{
    protected $signature = 'wms:migrate-sheets-to-mysql
        {--migrate : Run the isolated M1 schema migrations after the target guard passes}
        {--dry-run : Read and validate the source without writing business rows}
        {--reconcile-only : Do not import; compare the current source with the current target}';

    protected $description = 'Read production Google Sheets and idempotently copy them to the guarded local wms_mysql target';

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('reconcile-only')) {
            $this->error('--dry-run and --reconcile-only are mutually exclusive.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $startedAt = now(config('wms_migration.timezone'))->toIso8601String();

        try {
            [$connection, $databaseVersion] = $this->assertSafeTarget();

            if ($this->option('migrate')) {
                $exit = Artisan::call('migrate', [
                    '--database' => $connection,
                    '--path' => 'database/migrations/wms_mysql',
                    '--force' => true,
                ]);
                $this->output->write(Artisan::output());
                if ($exit !== self::SUCCESS) {
                    throw new RuntimeException('The isolated WMS schema migration failed.');
                }
            }

            $this->assertSchemaPresent($connection);
            $source = app(GoogleSheetsReadOnlySource::class);
            $snapshot = $this->readSnapshot($source);
            $startDigests = $this->digests($snapshot);

            $engine = new MigrationEngine(
                $connection,
                new ValueNormalizer((string) config('wms_migration.timezone'))
            );
            $result = $engine->run(
                $snapshot,
                (bool) $this->option('dry-run'),
                (bool) $this->option('reconcile-only')
            );

            $endSnapshot = $this->readSnapshot($source, false);
            $endDigests = $this->digests($endSnapshot);
            $changedSheets = array_keys(array_filter($startDigests, static fn (string $digest, string $sheet): bool => ($endDigests[$sheet] ?? null) !== $digest, ARRAY_FILTER_USE_BOTH));

            $report = [
                'format' => 'wms-mysql-m1-report-v1',
                'mode' => $this->option('dry-run') ? 'dry-run' : ($this->option('reconcile-only') ? 'reconcile-only' : 'import'),
                'source' => 'google-sheets-read-only',
                'source_sheets' => array_keys(WmsMigrationManifest::contracts()),
                'target_tables' => array_keys(WmsMigrationManifest::contracts()),
                'target_connection' => $connection,
                'target_database' => config("database.connections.{$connection}.database"),
                'database_version' => $databaseVersion,
                'started_at' => $startedAt,
                'ended_at' => now(config('wms_migration.timezone'))->toIso8601String(),
                'duration_seconds' => round(microtime(true) - $started, 3),
                'source_changed_during_run' => $changedSheets !== [],
                'changed_sheets' => $changedSheets,
                'constraints' => [
                    'foreign_keys_enabled' => [],
                    'foreign_keys_staged' => WmsMigrationManifest::relationships(),
                    'duplicate_id_strategy' => 'MASTER_SYSTEM_SETTING preserves every row by _source_row_number',
                ],
                'safety' => [
                    'google_sheets_writes_performed' => false,
                    'production_modified' => false,
                    'production_env_changed' => false,
                    'google_sheet_schema_changed' => false,
                    'runtime_switched_to_mysql' => false,
                ],
                ...$result,
            ];

            $path = $this->writeReport($report);
            $this->renderSummary($report);
            $this->info("Report: {$path}");

            if (! $this->passes($report)) {
                $this->error('M1 reconciliation did not pass. Inspect the JSON report; no Google Sheets writes were performed.');

                return self::FAILURE;
            }

            $this->info('M1 import/reconciliation passed. Runtime cutover remains disabled.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array{0: string, 1: string} */
    private function assertSafeTarget(): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('M1 importer is local/testing only and refuses this APP_ENV.');
        }

        $connection = (string) config('wms_migration.connection');
        $configuration = config("database.connections.{$connection}");
        $required = [
            'host' => 'WMS_MIGRATION_DB_HOST',
            'port' => 'WMS_MIGRATION_DB_PORT',
            'database' => 'WMS_MIGRATION_DB_DATABASE',
            'username' => 'WMS_MIGRATION_DB_USERNAME',
            'password' => 'WMS_MIGRATION_DB_PASSWORD',
        ];
        $missing = [];
        foreach ($required as $key => $variable) {
            if (! is_array($configuration) || ! array_key_exists($key, $configuration) || $configuration[$key] === null || ($key !== 'password' && $configuration[$key] === '')) {
                $missing[] = $variable;
            }
        }
        if ($missing !== []) {
            throw new RuntimeException('Missing local migration environment variables: '.implode(', ', $missing).'.');
        }

        $allowed = (string) config('wms_migration.allowed_database');
        if (($configuration['database'] ?? null) !== $allowed) {
            throw new RuntimeException("Refusing configured database '{$configuration['database']}'; M1 only allows '{$allowed}'.");
        }

        $database = DB::connection($connection);
        $selected = (string) ($database->selectOne('select database() as current_database')->current_database ?? '');
        if ($selected !== $allowed) {
            throw new RuntimeException("Refusing connected database '{$selected}'; expected '{$allowed}'.");
        }
        $version = (string) ($database->selectOne('select version() as server_version')->server_version ?? 'unknown');

        return [$connection, $version];
    }

    private function assertSchemaPresent(string $connection): void
    {
        $schema = Schema::connection($connection);
        $missing = array_values(array_filter(
            array_keys(WmsMigrationManifest::contracts()),
            static fn (string $table): bool => ! $schema->hasTable($table)
        ));
        if ($missing !== []) {
            throw new RuntimeException('M1 schema is missing. Re-run with --migrate. Missing tables: '.implode(', ', $missing).'.');
        }
    }

    /** @return array<string, array{headers: list<string>, rows: list<array{source_row: int, values: array<string, mixed>}>}> */
    private function readSnapshot(GoogleSheetsReadOnlySource $source, bool $showProgress = true): array
    {
        if ($showProgress) {
            $this->line('Reading 45 Google Sheets ranges in one read-only batch...');
        }

        return $source->readMany(array_keys(WmsMigrationManifest::contracts()));
    }

    /** @return array<string, string> */
    private function digests(array $snapshot): array
    {
        $digests = [];
        foreach ($snapshot as $sheet => $data) {
            $digests[$sheet] = hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        return $digests;
    }

    private function writeReport(array $report): string
    {
        $directory = (string) config('wms_migration.report_directory');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create report directory {$directory}.");
        }
        $path = $directory.'/mysql-m1-'.now()->format('Ymd-His').'.json';
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $path;
    }

    private function renderSummary(array $report): void
    {
        $rows = [];
        foreach ($report['tables'] as $table => $data) {
            $rows[] = [$table, $data['source_rows'], $data['target_rows'], $data['inserted'], $data['updated'], $data['unchanged'], $data['skipped']];
        }
        $this->table(['Table', 'Source', 'Target', 'Insert', 'Update', 'Same', 'Skipped'], $rows);
    }

    private function passes(array $report): bool
    {
        if ($report['source_changed_during_run']) {
            return false;
        }
        foreach ($report['tables'] as $table) {
            if ($table['skipped'] > 0 || $table['conversion_errors'] !== [] || $table['missing_ids'] !== [] || $table['extra_ids'] !== []) {
                return false;
            }
            foreach ($table['numeric_reconciliation'] as $numeric) {
                if ($numeric['difference'] !== '0.0000') {
                    return false;
                }
            }
        }
        foreach ($report['finance_by_type'] as $numeric) {
            if ($numeric['difference'] !== '0.0000') {
                return false;
            }
        }

        return true;
    }
}
