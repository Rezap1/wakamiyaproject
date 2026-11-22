<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\MySqlTestGuard;
use Tests\TestCase;

class MySqlTestGuardTest extends TestCase
{
    public function test_exact_isolated_local_configuration_is_accepted_without_connecting(): void
    {
        MySqlTestGuard::assertSafeConfiguration($this->safeState());

        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafeOverrides')]
    public function test_unsafe_configuration_is_rejected_before_any_connection(string $key, string $value): void
    {
        $state = $this->safeState();
        $state[$key] = $value;

        $this->expectException(RuntimeException::class);
        MySqlTestGuard::assertSafeConfiguration($state);
    }

    public static function unsafeOverrides(): array
    {
        return [
            'environment' => ['app_env', 'production'],
            'missing opt in' => ['opt_in', ''],
            'driver' => ['driver', 'pgsql'],
            'remote host' => ['host', 'db.example.test'],
            'localhost alias' => ['host', 'localhost'],
            'port' => ['port', '3307'],
            'database' => ['database', 'wms_mysql'],
            'runtime database' => ['runtime_database', 'wms_mysql'],
            'url override' => ['url', 'mysql://remote.example.test/wms_mysql_test'],
            'root user' => ['username', 'root'],
            'administrator user' => ['username', 'administrator'],
        ];
    }

    /** @return array<string,string> */
    private function safeState(): array
    {
        return [
            'app_env' => 'testing',
            'opt_in' => '1',
            'connection_name' => 'mysql',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'wms_mysql_test',
            'runtime_database' => 'wms_mysql_test',
            'url' => '',
            'username' => 'wms_test_runner',
        ];
    }
}
