<?php

namespace Tests\Unit\Migration;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class MigrationCommandSafetyTest extends TestCase
{
    public function test_command_refuses_to_guess_missing_mysql_credentials(): void
    {
        config()->set('app.env', 'local');
        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            config()->set("database.connections.wms_migration.{$key}", null);
        }

        $exit = Artisan::call('wms:migrate-sheets-to-mysql', ['--migrate' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        foreach (['WMS_MIGRATION_DB_HOST', 'WMS_MIGRATION_DB_PORT', 'WMS_MIGRATION_DB_DATABASE', 'WMS_MIGRATION_DB_USERNAME', 'WMS_MIGRATION_DB_PASSWORD'] as $variable) {
            $this->assertStringContainsString($variable, $output);
        }
    }

    public function test_command_refuses_an_unexpected_database_before_connecting(): void
    {
        config()->set('app.env', 'local');
        config()->set('database.connections.wms_migration', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'not_wms_mysql',
            'username' => 'local-user',
            'password' => '',
        ]);

        $exit = Artisan::call('wms:migrate-sheets-to-mysql');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString("only allows 'wms_mysql'", Artisan::output());
    }
}
