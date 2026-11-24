<?php

namespace App\Support\Migration;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class QuizMultimediaSchemaGuard
{
    /** @var array<string,array{kind:string,length?:int,unsigned?:bool,nullable:bool}> */
    private const MEDIA_COLUMNS = [
        'Image_Path' => ['kind' => 'varchar', 'length' => 512, 'nullable' => true],
        'Image_Mime' => ['kind' => 'varchar', 'length' => 100, 'nullable' => true],
        'Image_Size_Bytes' => ['kind' => 'bigint', 'unsigned' => true, 'nullable' => true],
        'Audio_Path' => ['kind' => 'varchar', 'length' => 512, 'nullable' => true],
        'Audio_Mime' => ['kind' => 'varchar', 'length' => 100, 'nullable' => true],
        'Audio_Size_Bytes' => ['kind' => 'bigint', 'unsigned' => true, 'nullable' => true],
        'Audio_Duration_Milliseconds' => ['kind' => 'bigint', 'unsigned' => true, 'nullable' => true],
    ];

    /** @return array<string,array{kind:string,length?:int,unsigned?:bool,nullable:bool}> */
    public static function mediaColumns(): array
    {
        return self::MEDIA_COLUMNS;
    }

    public static function assertMigrationTarget(?string $migrationConnection = null): void
    {
        $connectionName = $migrationConnection ?: DB::getDefaultConnection();
        if (! is_string($connectionName) || $connectionName === '') {
            throw new RuntimeException('Quiz multimedia migration refused: migration connection is not configured.');
        }

        $connectionConfig = config("database.connections.{$connectionName}");
        if (! is_array($connectionConfig)) {
            throw new RuntimeException('Quiz multimedia migration refused: migration connection configuration is missing.');
        }

        $configuredDriver = (string) ($connectionConfig['driver'] ?? '');
        $configuredDatabase = (string) ($connectionConfig['database'] ?? '');
        self::assertTargetState([
            'app_env' => (string) config('app.env'),
            'migration_connection' => $connectionName,
            'runtime_connection' => (string) config('wms_runtime.connection'),
            'resolved_connection' => $connectionName,
            'configured_driver' => $configuredDriver,
            'driver' => $configuredDriver,
            'configured_database' => $configuredDatabase,
            'runtime_database' => (string) config('wms_runtime.database'),
            'production_database' => (string) config('wms_runtime.quiz_multimedia_migration_database'),
            'effective_database' => $configuredDatabase,
            'selected_database' => $configuredDatabase,
            'url' => (string) ($connectionConfig['url'] ?? ''),
        ]);

        try {
            /** @var Connection $connection */
            $connection = DB::connection($connectionName);
            $driver = $connection->getDriverName();
            $effectiveDatabase = $connection->getDatabaseName();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Quiz multimedia migration refused: the configured migration connection could not be inspected.',
                0,
                $exception,
            );
        }

        $selectedDatabase = null;
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                $metadata = $connection->selectOne('SELECT DATABASE() AS database_name');
                $selectedDatabase = is_object($metadata) ? ($metadata->database_name ?? null) : null;
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'Quiz multimedia migration refused: SELECT DATABASE() could not verify the effective target.',
                    0,
                    $exception,
                );
            }
        }

        self::assertTargetState([
            'app_env' => (string) config('app.env'),
            'migration_connection' => $connectionName,
            'runtime_connection' => (string) config('wms_runtime.connection'),
            'resolved_connection' => (string) $connection->getName(),
            'configured_driver' => $configuredDriver,
            'driver' => $driver,
            'configured_database' => $configuredDatabase,
            'runtime_database' => (string) config('wms_runtime.database'),
            'production_database' => (string) config('wms_runtime.quiz_multimedia_migration_database'),
            'effective_database' => (string) $effectiveDatabase,
            'selected_database' => is_string($selectedDatabase) ? $selectedDatabase : '',
            'url' => (string) ($connectionConfig['url'] ?? ''),
        ]);
    }

    /** @param array<string,string> $state */
    public static function assertTargetState(array $state): void
    {
        $environment = $state['app_env'] ?? '';
        if (! in_array($environment, ['production', 'testing'], true)) {
            throw new RuntimeException('Quiz multimedia migration refused: application environment is not allowed.');
        }

        $migrationConnection = $state['migration_connection'] ?? '';
        if ($migrationConnection === ''
            || $migrationConnection !== ($state['runtime_connection'] ?? '')
            || $migrationConnection !== ($state['resolved_connection'] ?? '')) {
            throw new RuntimeException('Quiz multimedia migration refused: migration and runtime connections do not match.');
        }

        $driver = $state['driver'] ?? '';
        if ($driver === '' || $driver !== ($state['configured_driver'] ?? '')) {
            throw new RuntimeException('Quiz multimedia migration refused: configured and effective database drivers do not match.');
        }

        $configuredDatabase = $state['configured_database'] ?? '';
        $runtimeDatabase = $state['runtime_database'] ?? '';
        $effectiveDatabase = $state['effective_database'] ?? '';
        if ($configuredDatabase === '' || $runtimeDatabase === '' || $effectiveDatabase === '') {
            throw new RuntimeException('Quiz multimedia migration refused: database target configuration is incomplete.');
        }

        if ($driver === 'sqlite') {
            if ($environment !== 'testing'
                || $configuredDatabase !== ':memory:'
                || $runtimeDatabase !== ':memory:'
                || $effectiveDatabase !== ':memory:') {
                throw new RuntimeException('Quiz multimedia migration refused: only testing SQLite :memory: is allowed.');
            }

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Quiz multimedia migration refused: database driver is not supported.');
        }

        if (($state['url'] ?? '') !== '') {
            throw new RuntimeException('Quiz multimedia migration refused: database URL targets are not allowed for this migration.');
        }

        $selectedDatabase = $state['selected_database'] ?? '';
        if ($selectedDatabase === '') {
            throw new RuntimeException('Quiz multimedia migration refused: SELECT DATABASE() returned no target.');
        }

        if ($environment === 'testing') {
            $expectedDatabase = 'wms_mysql_test';
        } else {
            $expectedDatabase = $state['production_database'] ?? '';
            if ($expectedDatabase === '') {
                throw new RuntimeException('Quiz multimedia migration refused: owner-approved production target is not configured.');
            }
            if ($expectedDatabase === 'wms_mysql_test') {
                throw new RuntimeException('Quiz multimedia migration refused: the testing database is not a production target.');
            }
        }

        if ($configuredDatabase !== $expectedDatabase
            || $runtimeDatabase !== $expectedDatabase
            || $effectiveDatabase !== $expectedDatabase
            || $selectedDatabase !== $expectedDatabase) {
            throw new RuntimeException('Quiz multimedia migration refused: configured, runtime, effective, and approved database targets do not match.');
        }
    }

    public static function requireQuestionTable(): void
    {
        if (! Schema::hasTable('QUIZ_QUESTION')) {
            throw new RuntimeException('Quiz multimedia migration requires the existing QUIZ_QUESTION table.');
        }
    }

    public static function assertMediaColumns(): void
    {
        self::requireQuestionTable();
        self::assertColumns('QUIZ_QUESTION', self::MEDIA_COLUMNS);
    }

    public static function assertIdempotencyTables(): void
    {
        self::assertColumns('QUIZ_MEDIA_OPERATION', [
            'Idempotency_Key' => ['kind' => 'char', 'length' => 36, 'nullable' => false],
            'User_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Operation_Type' => ['kind' => 'varchar', 'length' => 16, 'nullable' => false],
            'Target_Quiz_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => true],
            'Payload_Hash' => ['kind' => 'char', 'length' => 64, 'nullable' => false],
            'Result_Quiz_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Committed_At' => ['kind' => 'datetime', 'nullable' => false],
        ]);
        self::assertColumns('QUIZ_MEDIA_TOKEN_USE', [
            'Token_Hash' => ['kind' => 'char', 'length' => 64, 'nullable' => false],
            'Idempotency_Key' => ['kind' => 'char', 'length' => 36, 'nullable' => false],
            'User_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Quiz_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Question_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Media_Type' => ['kind' => 'varchar', 'length' => 16, 'nullable' => false],
            'Final_Path' => ['kind' => 'varchar', 'length' => 512, 'nullable' => false],
            'Committed_At' => ['kind' => 'datetime', 'nullable' => false],
        ]);

        self::assertIndexes('QUIZ_MEDIA_OPERATION', [
            'PRIMARY' => ['Idempotency_Key'],
            'quiz_media_operation_user_time' => ['User_ID', 'Committed_At'],
        ]);
        self::assertIndexes('QUIZ_MEDIA_TOKEN_USE', [
            'PRIMARY' => ['Token_Hash'],
            'quiz_media_token_operation' => ['Idempotency_Key'],
            'quiz_media_token_quiz' => ['Quiz_ID'],
        ]);

        if (self::isMySqlFamily()) {
            foreach (['QUIZ_MEDIA_OPERATION', 'QUIZ_MEDIA_TOKEN_USE'] as $table) {
                $metadata = DB::selectOne(
                    'SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$table],
                );
                if (strtoupper((string) ($metadata->ENGINE ?? '')) !== 'INNODB') {
                    throw new RuntimeException("{$table} must use the InnoDB storage engine.");
                }
            }
        }
    }

    public static function assertAudienceTables(): void
    {
        self::assertColumns('QUIZ_ATTEMPT', [
            'Class_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => true],
        ]);
        self::assertColumns('QUIZ_AUDIENCE_CLASS', [
            'Quiz_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Class_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Created_At' => ['kind' => 'datetime', 'nullable' => false],
        ]);
        self::assertColumns('QUIZ_RUNTIME_STATE', [
            'Quiz_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Attempt_Generation' => ['kind' => 'bigint', 'unsigned' => true, 'nullable' => false],
            'Updated_At' => ['kind' => 'datetime', 'nullable' => false],
        ]);
        self::assertColumns('QUIZ_TOMBSTONE', [
            'Quiz_ID' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Deleted_By' => ['kind' => 'varchar', 'length' => 191, 'nullable' => false],
            'Deleted_At' => ['kind' => 'datetime', 'nullable' => false],
        ]);
        self::assertIndexes('QUIZ_AUDIENCE_CLASS', [
            'PRIMARY' => ['Quiz_ID', 'Class_ID'],
            'quiz_audience_class_lookup' => ['Class_ID', 'Quiz_ID'],
        ]);
        self::assertIndexes('QUIZ_RUNTIME_STATE', ['PRIMARY' => ['Quiz_ID']]);
        self::assertIndexes('QUIZ_TOMBSTONE', ['PRIMARY' => ['Quiz_ID']]);

        if (self::isMySqlFamily()) {
            foreach (['QUIZ_AUDIENCE_CLASS', 'QUIZ_RUNTIME_STATE', 'QUIZ_TOMBSTONE'] as $table) {
                $metadata = DB::selectOne(
                    'SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$table],
                );
                if (strtoupper((string) ($metadata->ENGINE ?? '')) !== 'INNODB') {
                    throw new RuntimeException("{$table} must use the InnoDB storage engine.");
                }
            }
        }
    }

    /** @param array<string,array{kind:string,length?:int,unsigned?:bool,nullable:bool}> $expected */
    private static function assertColumns(string $table, array $expected): void
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("Required table {$table} is missing.");
        }

        $columns = collect(Schema::getColumns($table))->keyBy(fn (array $column): string => strtolower((string) $column['name']));
        foreach ($expected as $name => $definition) {
            $column = $columns->get(strtolower($name));
            if (! is_array($column)) {
                throw new RuntimeException("Required column {$table}.{$name} is missing.");
            }
            $type = strtolower((string) ($column['type'] ?? $column['type_name'] ?? ''));
            $typeName = strtolower((string) ($column['type_name'] ?? $type));
            if (! self::typeMatches($type, $typeName, $definition['kind'])) {
                throw new RuntimeException("Column {$table}.{$name} has an incompatible type.");
            }
            if ((bool) ($column['nullable'] ?? true) !== $definition['nullable'] && self::isMySqlFamily()) {
                throw new RuntimeException("Column {$table}.{$name} has incompatible nullability.");
            }

            if (self::isMySqlFamily()) {
                self::assertMySqlColumn($table, $name, $definition);
            }
        }
    }

    /** @param array{kind:string,length?:int,unsigned?:bool,nullable:bool} $expected */
    private static function assertMySqlColumn(string $table, string $column, array $expected): void
    {
        $metadata = DB::selectOne(
            'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );
        if ($metadata === null
            || strtolower((string) $metadata->DATA_TYPE) !== $expected['kind']
            || ((string) $metadata->IS_NULLABLE === 'YES') !== $expected['nullable']
            || (isset($expected['length']) && (int) $metadata->CHARACTER_MAXIMUM_LENGTH !== $expected['length'])
            || (($expected['unsigned'] ?? false) && ! str_contains(strtolower((string) $metadata->COLUMN_TYPE), 'unsigned'))) {
            throw new RuntimeException("Column {$table}.{$column} does not match the required MySQL definition.");
        }
    }

    /** @param array<string,list<string>> $expected */
    private static function assertIndexes(string $table, array $expected): void
    {
        $indexes = collect(Schema::getIndexes($table));
        foreach ($expected as $name => $columns) {
            $index = $indexes->first(function (array $index) use ($name): bool {
                return $name === 'PRIMARY'
                    ? (bool) ($index['primary'] ?? false)
                    : strcasecmp((string) ($index['name'] ?? ''), $name) === 0;
            });
            if (! is_array($index) || array_map('strtolower', $index['columns'] ?? []) !== array_map('strtolower', $columns)) {
                throw new RuntimeException("Required index {$table}.{$name} is missing or incompatible.");
            }
            if ($name === 'PRIMARY' && ! (bool) ($index['unique'] ?? false)) {
                throw new RuntimeException("Primary index {$table}.{$name} must be unique.");
            }
        }
    }

    private static function typeMatches(string $type, string $typeName, string $expected): bool
    {
        if (! self::isMySqlFamily()) {
            return match ($expected) {
                'char', 'varchar' => $typeName === 'varchar' || str_starts_with($type, 'varchar'),
                'bigint' => in_array($typeName, ['integer', 'bigint'], true) || str_contains($type, 'int'),
                'datetime' => str_contains($typeName, 'datetime') || str_contains($type, 'datetime'),
                default => false,
            };
        }

        return match ($expected) {
            'char' => $typeName === 'char' || str_starts_with($type, 'char'),
            'varchar' => $typeName === 'varchar' || str_starts_with($type, 'varchar'),
            'bigint' => str_contains($typeName, 'bigint') || str_contains($type, 'bigint'),
            'datetime' => str_contains($typeName, 'datetime') || str_contains($type, 'datetime'),
            default => false,
        };
    }

    private static function isMySqlFamily(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
