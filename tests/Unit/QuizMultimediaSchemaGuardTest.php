<?php

namespace Tests\Unit;

use App\Support\Migration\QuizMultimediaSchemaGuard;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class QuizMultimediaSchemaGuardTest extends TestCase
{
    public function test_owner_approved_production_target_is_accepted(): void
    {
        $this->configureMySql('production', 'wms_owner_approved', 'wms_owner_approved');
        $this->mockMySqlConnection('wms_owner_approved');

        QuizMultimediaSchemaGuard::assertMigrationTarget('mysql');
        $this->addToAssertionCount(1);
    }

    public function test_missing_production_target_is_rejected(): void
    {
        $this->expectGuardFailure('owner-approved production target is not configured');
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'production_database' => '',
        ]));
    }

    public function test_effective_database_mismatch_is_rejected(): void
    {
        $this->expectGuardFailure('database targets do not match');
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'effective_database' => 'wrong_database',
        ]));
    }

    public function test_selected_database_mismatch_is_rejected(): void
    {
        $this->expectGuardFailure('database targets do not match');
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'selected_database' => 'wrong_database',
        ]));
    }

    public function test_connection_mismatch_is_rejected(): void
    {
        $this->expectGuardFailure('migration and runtime connections do not match');
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'runtime_connection' => 'other',
        ]));
    }

    public function test_runtime_database_mismatch_is_rejected(): void
    {
        $this->expectGuardFailure('database targets do not match');
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'runtime_database' => 'wrong_database',
        ]));
    }

    public function test_testing_environment_with_production_database_is_rejected(): void
    {
        $this->expectGuardFailure('database targets do not match');
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'app_env' => 'testing',
        ]));
    }

    public function test_production_environment_with_testing_database_is_rejected(): void
    {
        $this->expectGuardFailure('testing database is not a production target');
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'configured_database' => 'wms_mysql_test',
            'runtime_database' => 'wms_mysql_test',
            'production_database' => 'wms_mysql_test',
            'effective_database' => 'wms_mysql_test',
            'selected_database' => 'wms_mysql_test',
        ]));
    }

    public function test_mariadb_testing_target_is_accepted(): void
    {
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'app_env' => 'testing',
            'configured_driver' => 'mariadb',
            'driver' => 'mariadb',
            'configured_database' => 'wms_mysql_test',
            'runtime_database' => 'wms_mysql_test',
            'effective_database' => 'wms_mysql_test',
            'selected_database' => 'wms_mysql_test',
        ]));

        $this->addToAssertionCount(1);
    }

    public function test_sqlite_in_memory_testing_target_is_accepted(): void
    {
        QuizMultimediaSchemaGuard::assertMigrationTarget('sqlite');
        $this->addToAssertionCount(1);
    }

    public function test_sqlite_production_target_is_rejected(): void
    {
        $this->expectGuardFailure('only testing SQLite :memory: is allowed');
        QuizMultimediaSchemaGuard::assertTargetState([
            'app_env' => 'production',
            'migration_connection' => 'sqlite',
            'runtime_connection' => 'sqlite',
            'resolved_connection' => 'sqlite',
            'configured_driver' => 'sqlite',
            'driver' => 'sqlite',
            'configured_database' => ':memory:',
            'runtime_database' => ':memory:',
            'effective_database' => ':memory:',
        ]);
    }

    public function test_sqlite_file_target_is_rejected(): void
    {
        $this->expectGuardFailure('only testing SQLite :memory: is allowed');
        QuizMultimediaSchemaGuard::assertTargetState([
            'app_env' => 'testing',
            'migration_connection' => 'sqlite',
            'runtime_connection' => 'sqlite',
            'resolved_connection' => 'sqlite',
            'configured_driver' => 'sqlite',
            'driver' => 'sqlite',
            'configured_database' => database_path('database.sqlite'),
            'runtime_database' => database_path('database.sqlite'),
            'effective_database' => database_path('database.sqlite'),
        ]);
    }

    public function test_unsupported_driver_is_rejected(): void
    {
        $this->expectGuardFailure('database driver is not supported');
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'configured_driver' => 'pgsql',
            'driver' => 'pgsql',
        ]));
    }

    public function test_unapproved_environment_is_rejected(): void
    {
        $this->expectGuardFailure('application environment is not allowed');
        QuizMultimediaSchemaGuard::assertTargetState($this->mysqlState([
            'app_env' => 'local',
        ]));
    }

    public function test_select_database_failure_is_rejected(): void
    {
        $this->configureMySql('production', 'wms_owner_approved', 'wms_owner_approved');
        $connection = $this->mockConnectionBase('wms_owner_approved');
        $connection->shouldReceive('selectOne')->once()->andThrow(new RuntimeException('unavailable'));

        $this->expectGuardFailure('SELECT DATABASE() could not verify the effective target');
        QuizMultimediaSchemaGuard::assertMigrationTarget('mysql');
    }

    public function test_both_migrations_reject_an_unsafe_target_before_first_ddl(): void
    {
        Schema::create('QUIZ_QUESTION', function (Blueprint $table): void {
            $table->string('Question_ID')->primary();
        });
        config()->set('wms_runtime.connection', 'wrong_connection');

        foreach ([
            '2026_11_20_000001_add_quiz_question_media_fields.php',
            '2026_11_21_000001_create_quiz_media_idempotency_tables.php',
        ] as $file) {
            try {
                $migration = require database_path("migrations/wms_mysql/{$file}");
                $migration->up();
                $this->fail("{$file} did not reject the unsafe target.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('migration and runtime connections do not match', $exception->getMessage());
            }
        }

        $this->assertFalse(Schema::hasColumn('QUIZ_QUESTION', 'Image_Path'));
        $this->assertFalse(Schema::hasTable('QUIZ_MEDIA_OPERATION'));
        $this->assertFalse(Schema::hasTable('QUIZ_MEDIA_TOKEN_USE'));
    }

    public function test_both_migration_down_methods_reject_an_unsafe_target_before_ddl(): void
    {
        Schema::create('QUIZ_QUESTION', function (Blueprint $table): void {
            $table->string('Question_ID')->primary();
        });
        $mediaMigration = require database_path('migrations/wms_mysql/2026_11_20_000001_add_quiz_question_media_fields.php');
        $idempotencyMigration = require database_path('migrations/wms_mysql/2026_11_21_000001_create_quiz_media_idempotency_tables.php');
        $mediaMigration->up();
        $idempotencyMigration->up();

        config()->set('wms_runtime.connection', 'wrong_connection');
        foreach ([$idempotencyMigration, $mediaMigration] as $migration) {
            try {
                $migration->down();
                $this->fail('Migration down did not reject the unsafe target.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('migration and runtime connections do not match', $exception->getMessage());
            }
        }

        $this->assertTrue(Schema::hasColumn('QUIZ_QUESTION', 'Image_Path'));
        $this->assertTrue(Schema::hasTable('QUIZ_MEDIA_OPERATION'));
        $this->assertTrue(Schema::hasTable('QUIZ_MEDIA_TOKEN_USE'));
    }

    private function configureMySql(string $environment, string $database, ?string $productionDatabase): void
    {
        config()->set([
            'app.env' => $environment,
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => $database,
            'database.connections.mysql.url' => null,
            'wms_runtime.connection' => 'mysql',
            'wms_runtime.database' => $database,
            'wms_runtime.quiz_multimedia_migration_database' => $productionDatabase,
        ]);
    }

    private function mockMySqlConnection(string $database): void
    {
        $connection = $this->mockConnectionBase($database);
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT DATABASE() AS database_name')
            ->andReturn((object) ['database_name' => $database]);
    }

    private function mockConnectionBase(string $database): Mockery\MockInterface
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->once()->andReturn($database);
        $connection->shouldReceive('getName')->zeroOrMoreTimes()->andReturn('mysql');
        DB::shouldReceive('connection')->once()->with('mysql')->andReturn($connection);

        return $connection;
    }

    /**
     * @param  array<string,string>  $overrides
     * @return array<string,string>
     */
    private function mysqlState(array $overrides = []): array
    {
        return array_replace([
            'app_env' => 'production',
            'migration_connection' => 'mysql',
            'runtime_connection' => 'mysql',
            'resolved_connection' => 'mysql',
            'configured_driver' => 'mysql',
            'driver' => 'mysql',
            'configured_database' => 'wms_owner_approved',
            'runtime_database' => 'wms_owner_approved',
            'production_database' => 'wms_owner_approved',
            'effective_database' => 'wms_owner_approved',
            'selected_database' => 'wms_owner_approved',
            'url' => '',
        ], $overrides);
    }

    private function expectGuardFailure(string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
    }
}
