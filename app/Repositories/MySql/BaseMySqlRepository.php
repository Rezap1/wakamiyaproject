<?php

namespace App\Repositories\MySql;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Exceptions\FinancialIntegrityException;
use App\Helpers\UserResolverHelper;
use App\Services\Migration\ValueNormalizer;
use App\Support\Migration\WmsMigrationManifest;
use Closure;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Stringable;

/**
 * Shared query-builder persistence for the 45 canonical WMS business tables.
 *
 * Concrete repository names and interfaces stay stable for the service layer.
 * This implementation has no Google client, HTTP retry, sheet cache, or
 * service-account dependency.
 */
abstract class BaseMySqlRepository
{
    protected string $sheetName = '';

    protected string $cacheKey = '';

    protected string $primaryKey = 'id';

    protected bool $skipRowsWithoutPrimaryKey = true;

    /** @var list<string> */
    protected array $expectedHeaders = [];

    private ?array $contractCache = null;

    public function __construct()
    {
        // Runtime must boot without Google credentials or a Google client.
    }

    public function fetchAll()
    {
        return $this->fetchAllFresh();
    }

    public function fetchAllFresh()
    {
        if (! $this->tableExists()) {
            return collect();
        }

        $query = $this->connection()->table($this->table());
        if ($this->preservesDuplicateIds()) {
            $query->orderBy('_source_row_number');
        }

        return $query->get()
            ->map(fn (object $row): array => $this->toLegacyRow((array) $row))
            ->when(
                $this->skipRowsWithoutPrimaryKey && $this->primaryKey !== '',
                fn (Collection $rows): Collection => $rows->filter(
                    fn (array $row): bool => trim((string) ($row[$this->primaryKey] ?? '')) !== ''
                )->values()
            );
    }

    /** @return list<string> */
    public function fetchHeadersFresh()
    {
        $headers = array_values(array_filter(
            Schema::connection($this->connectionName())->getColumnListing($this->table()),
            fn (string $column): bool => ! str_starts_with($column, '_migration_')
                && ! str_starts_with($column, '_runtime_')
                && $column !== '_source_row_number'
        ));
        $this->assertExpectedHeaders($headers);
        $this->assertManifestHeaders($headers);

        return $headers;
    }

    public function findByIdFresh($id)
    {
        if (! $this->tableExists()) {
            return null;
        }

        $row = $this->identityQuery($id)->first();

        return $row ? $this->toLegacyRow((array) $row) : null;
    }

    public function clearCache()
    {
        if (in_array($this->table(), [
            'MASTER_EMPLOYEE',
            'MASTER_STUDENT',
            'MASTER_TEACHER',
            'MASTER_USER',
            'MASTER_ROLE',
            'MASTER_CLASS',
            'MASTER_BATCH',
        ], true)) {
            UserResolverHelper::clearCache();
        }
    }

    /**
     * Allocate a visible WMS ID through a transactionally locked sequence.
     * The first allocation bootstraps from imported canonical IDs.
     */
    public function generateNewId(string $prefix, int $padding = 6): string
    {
        $quotedPrefix = preg_quote($prefix, '/');
        $next = $this->allocateNextSequence(
            strtolower($this->table().':'.$this->primaryKey.':'.$prefix),
            $this->primaryKey,
            static function (string $value) use ($quotedPrefix): ?int {
                return preg_match('/^'.$quotedPrefix.'(\d+)$/i', $value, $matches)
                    ? (int) $matches[1]
                    : null;
            }
        );

        return $prefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    public function append(array $data)
    {
        $headers = $this->headers();
        $this->assertDurableWriteSchema($headers);
        $primaryKeyValue = trim((string) ($data[$this->primaryKey] ?? ''));
        if ($primaryKeyValue === '') {
            throw new InvalidArgumentException("Nilai primary key '{$this->primaryKey}' wajib diisi.");
        }

        try {
            $this->connection()->transaction(function () use ($data, $headers, $primaryKeyValue): void {
                if ($this->identityQuery($primaryKeyValue)->lockForUpdate()->exists()) {
                    throw new DuplicatePrimaryKeyException(
                        "Primary key '{$primaryKeyValue}' sudah ada di tabel '{$this->table()}'."
                    );
                }

                $insert = [];
                foreach ($headers as $header) {
                    $insert[$header] = $this->normalizeForDatabase($data[$header] ?? null, $header);
                }
                if ($this->preservesDuplicateIds()) {
                    $lastSourceRow = (int) $this->connection()->table($this->table())
                        ->lockForUpdate()
                        ->max('_source_row_number');
                    $insert['_source_row_number'] = max(1, $lastSourceRow + 1);
                }

                $this->connection()->table($this->table())->insert($insert);
            }, 5);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new DuplicatePrimaryKeyException(
                    "Primary key '{$primaryKeyValue}' sudah ada di tabel '{$this->table()}'.",
                    (int) $e->getCode(),
                    $e
                );
            }
            throw $e;
        }

        $this->clearCache();

        return true;
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }

    public function updateRow($id, array $data)
    {
        $data = $this->stripPrimaryKeyFromUpdateData($data);
        $headers = $this->headers();
        $this->assertDurableWriteSchema($headers);
        $updates = [];
        foreach ($data as $column => $value) {
            $actual = collect($headers)->first(fn (string $header): bool => strcasecmp($header, (string) $column) === 0);
            if ($actual !== null) {
                $updates[$actual] = $this->normalizeForDatabase($value, $actual);
            }
        }

        $this->connection()->transaction(function () use ($id, $updates): void {
            $query = $this->identityQuery($id)->lockForUpdate();
            if ($this->preservesDuplicateIds()) {
                // Sheets updated the first matching row. Source row number
                // reproduces that deterministically without deleting history.
                $query->orderBy('_source_row_number');
            }
            $row = $query->first();
            if ($row === null) {
                throw new RuntimeException("Record '{$id}' tidak ditemukan di tabel '{$this->table()}'.");
            }
            if ($updates === []) {
                return;
            }

            $target = $this->connection()->table($this->table());
            if ($this->preservesDuplicateIds()) {
                $target->where('_migration_row_id', $row->_migration_row_id);
            } else {
                $target->where($this->primaryKey, $row->{$this->primaryKey});
            }
            $target->update($updates);
        }, 5);

        $this->clearCache();

        return true;
    }

    protected function stripPrimaryKeyFromUpdateData(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if (strcasecmp(trim((string) $key), trim($this->primaryKey)) === 0) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    public function softDelete($id)
    {
        return $this->update($id, [
            'Is_Active' => 'FALSE',
            'Updated_At' => now()->toDateTimeString(),
        ]);
    }

    public function delete($id)
    {
        return $this->hardDelete($id);
    }

    public function hardDelete($id)
    {
        $deleted = $this->connection()->transaction(function () use ($id): int {
            $query = $this->identityQuery($id)->lockForUpdate();
            if ($this->preservesDuplicateIds()) {
                $query->orderBy('_source_row_number');
            }
            $row = $query->first();
            if ($row === null) {
                throw new RuntimeException("Record '{$id}' tidak ditemukan di tabel '{$this->table()}'.");
            }

            $target = $this->connection()->table($this->table());
            if ($this->preservesDuplicateIds()) {
                $target->where('_migration_row_id', $row->_migration_row_id);
            } else {
                $target->where($this->primaryKey, $row->{$this->primaryKey});
            }

            return $target->delete();
        }, 5);

        $this->clearCache();

        return $deleted === 1;
    }

    public function hardDeleteMany(array $ids): int
    {
        $needles = collect($ids)
            ->map(fn ($id): string => strtolower(trim((string) $id)))
            ->filter()
            ->unique()
            ->values();
        if ($needles->isEmpty()) {
            return 0;
        }

        $deleted = $this->connection()->transaction(function () use ($needles): int {
            $rowsQuery = $this->connection()->table($this->table());
            $this->wherePrimaryKeyIn($rowsQuery, $needles->all());
            $rows = $rowsQuery->lockForUpdate()->get();

            if ($this->preservesDuplicateIds()) {
                $rowIds = $rows->groupBy(fn (object $row): string => strtolower(trim((string) $row->{$this->primaryKey})))
                    ->filter(fn (Collection $matches): bool => $matches->count() === 1)
                    ->map(fn (Collection $matches) => $matches->first()->_migration_row_id)
                    ->values();

                return $rowIds->isEmpty()
                    ? 0
                    : $this->connection()->table($this->table())->whereIn('_migration_row_id', $rowIds)->delete();
            }

            $deleteQuery = $this->connection()->table($this->table());
            $this->wherePrimaryKeyIn($deleteQuery, $needles->all());

            return $deleteQuery->delete();
        }, 5);

        $this->clearCache();

        return $deleted;
    }

    /** Destructive reset is test-only to protect the imported baseline. */
    public function truncateData()
    {
        if (! app()->environment('testing') || ! config('wms_runtime.allow_test_truncate', false)) {
            throw new RuntimeException('truncateData hanya diizinkan pada database test terisolasi.');
        }

        $this->connection()->table($this->table())->delete();
        $this->clearCache();

        return true;
    }

    /** Execute a multi-repository workflow on the guarded runtime connection. */
    public function transaction(Closure $callback)
    {
        return $this->connection()->transaction($callback, 5);
    }

    /** Lock and return one canonical row for the lifetime of the outer transaction. */
    public function lockById($id)
    {
        if (! $this->tableExists()) {
            return null;
        }

        $query = $this->identityQuery($id)->lockForUpdate();
        if ($this->preservesDuplicateIds()) {
            $query->orderBy('_source_row_number');
        }
        $row = $query->first();

        return $row ? $this->toLegacyRow((array) $row) : null;
    }

    protected function firstWhereColumn(string $column, mixed $value, bool $caseInsensitive = false): ?array
    {
        if (! $this->tableExists()) {
            return null;
        }

        $query = $this->connection()->table($this->table());
        if ($caseInsensitive) {
            // Canonical MySQL tables use a case-insensitive utf8mb4 collation,
            // so a normal equality predicate remains case-insensitive and can
            // use the column index. SQLite is allowed only in unit tests.
            $query->where($column, trim((string) $value));
        } else {
            $query->where($column, $value);
        }
        if ($this->preservesDuplicateIds()) {
            $query->orderBy('_source_row_number');
        }
        $row = $query->first();

        return $row ? $this->toLegacyRow((array) $row) : null;
    }

    protected function rowsWhereColumn(string $column, mixed $value, bool $caseInsensitive = false): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        $query = $this->connection()->table($this->table());
        if ($caseInsensitive) {
            $query->where($column, trim((string) $value));
        } else {
            $query->where($column, $value);
        }

        return $query->get()->map(fn (object $row): array => $this->toLegacyRow((array) $row));
    }

    /**
     * @param  Closure(string): ?int  $extractNumber
     */
    protected function allocateNextSequence(
        string $sequenceKey,
        string $sourceColumn,
        Closure $extractNumber
    ): int {
        return $this->connection()->transaction(function () use ($sequenceKey, $sourceColumn, $extractNumber): int {
            $sequenceTable = config('wms_runtime.sequence_table', 'WMS_ID_SEQUENCE');
            $this->connection()->table($sequenceTable)->insertOrIgnore([
                'Sequence_Key' => $sequenceKey,
                'Last_Value' => 0,
                'Updated_At' => now()->toDateTimeString(),
            ]);
            $sequence = $this->connection()->table($sequenceTable)
                ->where('Sequence_Key', $sequenceKey)
                ->lockForUpdate()
                ->first();
            if ($sequence === null) {
                throw new RuntimeException("Sequence '{$sequenceKey}' tidak dapat dikunci.");
            }

            $current = (int) $sequence->Last_Value;
            if ($current === 0) {
                $maximum = 0;
                $this->connection()->table($this->table())
                    ->whereNotNull($sourceColumn)
                    ->orderBy($sourceColumn)
                    ->select($sourceColumn)
                    ->chunk(500, function (Collection $rows) use (&$maximum, $sourceColumn, $extractNumber): void {
                        foreach ($rows as $row) {
                            $number = $extractNumber(trim((string) $row->{$sourceColumn}));
                            if ($number !== null) {
                                $maximum = max($maximum, $number);
                            }
                        }
                    });
                $current = $maximum;
            }

            $next = $current + 1;
            $this->connection()->table($sequenceTable)
                ->where('Sequence_Key', $sequenceKey)
                ->update(['Last_Value' => $next, 'Updated_At' => now()->toDateTimeString()]);

            return $next;
        }, 5);
    }

    protected function connection(): ConnectionInterface
    {
        $connection = DB::connection($this->connectionName());
        $driver = $connection->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $expected = (string) config('wms_runtime.database', 'wms_mysql');
            $configured = (string) $connection->getDatabaseName();
            if ($configured !== $expected) {
                throw new RuntimeException(
                    "WMS runtime database harus EXACTLY '{$expected}', configured '{$configured}'."
                );
            }
        } elseif (! app()->environment('testing')) {
            throw new RuntimeException("Driver '{$driver}' tidak diizinkan untuk WMS runtime.");
        }

        return $connection;
    }

    protected function connectionName(): string
    {
        return (string) config('wms_runtime.connection', config('database.default'));
    }

    protected function assertExpectedHeaders(array $headers): void
    {
        if ($this->expectedHeaders !== [] && array_values($headers) !== array_values($this->expectedHeaders)) {
            throw new RuntimeException("Kolom tabel '{$this->table()}' tidak sesuai schema yang diharapkan.");
        }
    }

    protected function assertDurableWriteSchema(array $headers): void
    {
        $required = config("finance.schema.{$this->table()}", []);
        $missing = array_values(array_diff($required, $headers));
        if ($missing !== []) {
            throw new FinancialIntegrityException(
                "Tabel {$this->table()} tidak memiliki kolom wajib: ".implode(', ', $missing)
                .'; penulisan dihentikan untuk mencegah kehilangan data.'
            );
        }
    }

    /** @return list<string> */
    private function headers(): array
    {
        $headers = array_values(array_unique([
            ...$this->contract()['headers'],
            ...config('wms_runtime.runtime_columns.'.$this->table(), []),
        ]));
        $this->assertExpectedHeaders($headers);

        return $headers;
    }

    private function table(): string
    {
        if ($this->sheetName === '' || ! array_key_exists($this->sheetName, WmsMigrationManifest::contracts())) {
            throw new RuntimeException("Repository table '{$this->sheetName}' tidak terdaftar pada manifest WMS.");
        }

        return $this->sheetName;
    }

    /** @return array{sheet:string,table:string,primary_key:string,headers:list<string>,preserve_duplicate_ids:bool} */
    private function contract(): array
    {
        return $this->contractCache ??= WmsMigrationManifest::contracts()[$this->table()];
    }

    private function preservesDuplicateIds(): bool
    {
        return $this->contract()['preserve_duplicate_ids'];
    }

    private function identityQuery($id)
    {
        $query = $this->connection()->table($this->table());
        $value = trim((string) $id);
        if (in_array($this->connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // Canonical tables use a case-insensitive collation. Keeping the
            // indexed column bare lets primary/unique indexes serve lookups.
            return $query->where($this->primaryKey, $value);
        }

        return $query->whereRaw('LOWER('.$this->quotedPrimaryKey().') = ?', [strtolower($value)]);
    }

    /** @param list<string> $values */
    private function wherePrimaryKeyIn($query, array $values): void
    {
        if (in_array($this->connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $query->whereIn($this->primaryKey, $values);

            return;
        }

        $query->whereIn(DB::raw('LOWER('.$this->quotedPrimaryKey().')'), $values);
    }

    private function quotedPrimaryKey(): string
    {
        return $this->connection()->getQueryGrammar()->wrap($this->primaryKey);
    }

    private function normalizeForDatabase(mixed $value, string $column): mixed
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s.u');
        } elseif ($value instanceof Stringable) {
            $value = (string) $value;
        }

        $type = $column === 'Late_Minutes' ? 'integer' : WmsMigrationManifest::columnType($column);

        return (new ValueNormalizer((string) config('app.timezone', 'Asia/Jakarta')))
            ->normalize($value, $type)['value'];
    }

    /** @param array<string,mixed> $row */
    private function toLegacyRow(array $row): array
    {
        $result = [];
        foreach ($this->headers() as $column) {
            $value = $row[$column] ?? null;
            if ($value !== null && WmsMigrationManifest::columnType($column) === 'boolean') {
                $value = ((int) $value) === 1 ? 'TRUE' : 'FALSE';
            }
            $result[$column] = $value;
        }

        return $result;
    }

    /** @param list<string> $headers */
    private function assertManifestHeaders(array $headers): void
    {
        if ($headers !== $this->headers()) {
            throw new RuntimeException("Kolom tabel '{$this->table()}' tidak sesuai manifest M1.");
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[0] ?? ''), ['23000', '23505'], true)
            || in_array((int) ($e->errorInfo[1] ?? 0), [1062, 1555, 2067], true);
    }

    private function tableExists(): bool
    {
        $exists = Schema::connection($this->connectionName())->hasTable($this->table());
        if (! $exists && (! app()->environment('testing') || ! config('wms_runtime.allow_missing_test_schema', false))) {
            throw new RuntimeException("Tabel runtime '{$this->table()}' tidak tersedia.");
        }

        return $exists;
    }
}
