<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\MigrationEngine;
use App\Services\Migration\ValueNormalizer;
use App\Support\Migration\WmsMigrationManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MigrationEngineTest extends TestCase
{
    private const CONNECTION = 'm1_test';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);
        $migration = require database_path('migrations/wms_mysql/2026_10_02_000001_create_wms_business_schema.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_schema_contains_every_sheet_and_canonical_primary_keys(): void
    {
        foreach (WmsMigrationManifest::contracts() as $contract) {
            $this->assertTrue(Schema::connection(self::CONNECTION)->hasTable($contract['table']));
            $this->assertTrue(Schema::connection(self::CONNECTION)->hasColumns($contract['table'], $contract['headers']));
        }

        $this->assertTrue(Schema::connection(self::CONNECTION)->hasColumn('MASTER_SYSTEM_SETTING', '_source_row_number'));
    }

    public function test_import_preserves_ids_and_is_idempotent(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['MASTER_ROLE']['rows'][] = $this->row('MASTER_ROLE', 2, [
            'Role_ID' => 'ROLE-001',
            'Role_Name' => '管理者',
            'Is_Active' => 'FALSE',
        ]);

        $first = $this->engine()->run($snapshot);
        $second = $this->engine()->run($snapshot);

        $this->assertSame(1, $first['tables']['MASTER_ROLE']['inserted']);
        $this->assertSame(0, $second['tables']['MASTER_ROLE']['inserted']);
        $this->assertSame(1, $second['tables']['MASTER_ROLE']['unchanged']);
        $this->assertSame('ROLE-001', DB::connection(self::CONNECTION)->table('MASTER_ROLE')->value('Role_ID'));
        $this->assertSame(0, DB::connection(self::CONNECTION)->table('MASTER_ROLE')->value('Is_Active'));
    }

    public function test_duplicate_canonical_ids_are_detected_without_silent_deduplication(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['MASTER_ROLE']['rows'] = [
            $this->row('MASTER_ROLE', 2, ['Role_ID' => 'DUP-1']),
            $this->row('MASTER_ROLE', 3, ['Role_ID' => 'dup-1']),
        ];
        $snapshot['MASTER_SYSTEM_SETTING']['rows'] = [
            $this->row('MASTER_SYSTEM_SETTING', 2, ['Setting_ID' => 'SETTING-DUP', 'Setting_Value' => 'one']),
            $this->row('MASTER_SYSTEM_SETTING', 3, ['Setting_ID' => 'SETTING-DUP', 'Setting_Value' => 'two']),
        ];

        $result = $this->engine()->run($snapshot);

        $this->assertSame([2, 3], $result['tables']['MASTER_ROLE']['duplicate_ids']['DUP-1']);
        $this->assertSame(2, $result['tables']['MASTER_ROLE']['skipped']);
        $this->assertSame(0, DB::connection(self::CONNECTION)->table('MASTER_ROLE')->count());
        $this->assertSame(2, DB::connection(self::CONNECTION)->table('MASTER_SYSTEM_SETTING')->count());
        $this->assertSame([2, 3], $result['tables']['MASTER_SYSTEM_SETTING']['duplicate_ids']['SETTING-DUP']);
    }

    public function test_reconciliation_reports_exact_money_and_orphans(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['FINANCE_INVOICE']['rows'] = [
            $this->row('FINANCE_INVOICE', 2, ['Invoice_ID' => 'INV-1', 'Student_ID' => 'MISSING-STUDENT', 'Amount' => '1000000.1200']),
            $this->row('FINANCE_INVOICE', 3, ['Invoice_ID' => 'INV-2', 'Amount' => '-0.1200']),
        ];

        $result = $this->engine()->run($snapshot);
        $amount = $result['tables']['FINANCE_INVOICE']['numeric_reconciliation']['Amount'];

        $this->assertSame('1000000.0000', $amount['source']);
        $this->assertSame($amount['source'], $amount['target']);
        $this->assertSame('0.0000', $amount['difference']);
        $this->assertNotEmpty(array_filter($result['orphans'], static fn (array $orphan): bool => $orphan['child'] === 'FINANCE_INVOICE' && $orphan['column'] === 'Student_ID'));
    }

    public function test_finance_income_and_expense_are_reconciled_separately(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['FINANCE_TRANSACTION']['rows'] = [
            $this->row('FINANCE_TRANSACTION', 2, ['Transaction_ID' => 'TRX-1', 'Type' => 'INCOME', 'Amount' => '120.5000']),
            $this->row('FINANCE_TRANSACTION', 3, ['Transaction_ID' => 'TRX-2', 'Type' => 'EXPENSE', 'Amount' => '20.2500']),
        ];

        $finance = $this->engine()->run($snapshot)['finance_by_type'];

        $this->assertSame(['source' => '120.5000', 'target' => '120.5000', 'difference' => '0.0000'], $finance['INCOME']);
        $this->assertSame(['source' => '20.2500', 'target' => '20.2500', 'difference' => '0.0000'], $finance['EXPENSE']);
    }

    /** @return array<string, array{headers: list<string>, rows: list<array{source_row: int, values: array<string, mixed>}>}> */
    private function snapshot(): array
    {
        $snapshot = [];
        foreach (WmsMigrationManifest::contracts() as $sheet => $contract) {
            $snapshot[$sheet] = ['headers' => $contract['headers'], 'rows' => []];
        }

        return $snapshot;
    }

    /** @return array{source_row: int, values: array<string, mixed>} */
    private function row(string $sheet, int $sourceRow, array $values): array
    {
        $row = array_fill_keys(WmsMigrationManifest::contracts()[$sheet]['headers'], null);

        return ['source_row' => $sourceRow, 'values' => array_replace($row, $values)];
    }

    private function engine(): MigrationEngine
    {
        return new MigrationEngine(self::CONNECTION, new ValueNormalizer('Asia/Jakarta'));
    }
}
