<?php

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MySqlTestGuard
{
    public static function connection(): Connection
    {
        $connectionName = (string) config('wms_runtime.connection');
        $connectionConfig = (array) config("database.connections.{$connectionName}", []);
        self::assertSafeConfiguration([
            'app_env' => (string) config('app.env'),
            'opt_in' => (string) env('WMS_MYSQL_INTEGRATION', ''),
            'connection_name' => $connectionName,
            'driver' => (string) ($connectionConfig['driver'] ?? ''),
            'host' => (string) ($connectionConfig['host'] ?? ''),
            'port' => (string) ($connectionConfig['port'] ?? ''),
            'database' => (string) ($connectionConfig['database'] ?? ''),
            'runtime_database' => (string) config('wms_runtime.database'),
            'url' => (string) ($connectionConfig['url'] ?? ''),
            'username' => (string) ($connectionConfig['username'] ?? ''),
        ]);

        $connection = DB::connection($connectionName);
        $metadata = $connection->selectOne(
            'SELECT DATABASE() AS database_name, @@port AS server_port, @@sql_mode AS sql_mode, VERSION() AS server_version, CURRENT_USER() AS authenticated_user',
        );
        $currentUser = strtolower(strtok((string) ($metadata->authenticated_user ?? ''), '@') ?: '');
        if (($metadata->database_name ?? null) !== 'wms_mysql_test'
            || (int) ($metadata->server_port ?? 0) !== 3306
            || ! str_contains(strtolower((string) ($metadata->server_version ?? '')), 'mariadb')
            || (! str_contains((string) ($metadata->sql_mode ?? ''), 'STRICT_TRANS_TABLES')
                && ! str_contains((string) ($metadata->sql_mode ?? ''), 'STRICT_ALL_TABLES'))
            || self::isAdministrator($currentUser)) {
            throw new RuntimeException('Refusing unsafe MySQL integration connection metadata.');
        }

        $markers = $connection->select(
            "SELECT TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('QUIZ', 'QUIZ_QUESTION', 'WMS_ID_SEQUENCE')",
        );
        if (count($markers) !== 3 || collect($markers)->contains(
            fn (object $table): bool => strtoupper((string) $table->ENGINE) !== 'INNODB'
        )) {
            throw new RuntimeException('Refusing MySQL integration database without the required InnoDB schema markers.');
        }

        return $connection;
    }

    /** @param array<string,string> $state */
    public static function assertSafeConfiguration(array $state): void
    {
        if (($state['app_env'] ?? '') !== 'testing'
            || ($state['opt_in'] ?? '') !== '1'
            || ! in_array($state['driver'] ?? '', ['mysql', 'mariadb'], true)
            || ($state['host'] ?? '') !== '127.0.0.1'
            || ($state['port'] ?? '') !== '3306'
            || ($state['database'] ?? '') !== 'wms_mysql_test'
            || ($state['runtime_database'] ?? '') !== 'wms_mysql_test'
            || ($state['url'] ?? '') !== ''
            || ($state['connection_name'] ?? '') === ''
            || self::isAdministrator(strtolower($state['username'] ?? ''))) {
            throw new RuntimeException('Refusing unsafe MySQL integration test configuration.');
        }
    }

    private static function isAdministrator(string $username): bool
    {
        return $username === '' || in_array($username, ['root', 'admin', 'administrator'], true);
    }
}
