<?php

namespace Tests\Unit;

use App\Exceptions\FinancialIntegrityException;
use App\Repositories\GoogleSheets\BaseSheetRepository;
use App\Repositories\GoogleSheets\StudentRepository;
use App\Repositories\MySql\BaseMySqlRepository;
use Google_Service_Sheets_ValueRange;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Legacy M1 Google write-contract fixture. M2 coverage lives in
 * M2GoogleOffRuntimeTest and M2MySqlRuntimePersistenceTest. The legacy
 * fixtures remain non-tests so obsolete Google write doubles cannot enter QA.
 */
class GoogleSheetsRepositoryIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_compatibility_repository_shim_uses_mysql_runtime_base(): void
    {
        $this->assertTrue(is_subclass_of(BaseSheetRepository::class, BaseMySqlRepository::class));
    }

    public function legacyAllRepositoryFilesLoadWithValidConcreteSheetIdentity(): void
    {
        $files = glob(app_path('Repositories/GoogleSheets/*Repository.php'));
        $classes = collect($files)
            ->map(fn (string $file) => 'App\\Repositories\\GoogleSheets\\'.pathinfo($file, PATHINFO_FILENAME))
            ->reject(fn (string $class) => $class === BaseSheetRepository::class)
            ->values();

        $this->assertCount(48, $files);
        $this->assertCount(47, $classes);

        $sheetCacheKeys = [];

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $this->assertFalse($reflection->isAbstract(), $class.' tidak boleh abstract.');
            $this->assertTrue($reflection->isSubclassOf(BaseSheetRepository::class));

            $repository = $reflection->newInstance();
            $identity = [];
            foreach (['sheetName', 'cacheKey', 'primaryKey'] as $propertyName) {
                $property = new \ReflectionProperty(BaseSheetRepository::class, $propertyName);
                $identity[$propertyName] = trim((string) $property->getValue($repository));
                $this->assertNotSame('', $identity[$propertyName], $class.' '.$propertyName);
            }

            if (isset($sheetCacheKeys[$identity['sheetName']])) {
                $this->assertSame(
                    $sheetCacheKeys[$identity['sheetName']],
                    $identity['cacheKey'],
                    $identity['sheetName'].' harus menggunakan cache key yang sama di semua repository.'
                );
            }
            $sheetCacheKeys[$identity['sheetName']] = $identity['cacheKey'];
        }
    }

    public function legacyAppendWritesFormulaLikeValuesAsRawAndPreservesNumbers(): void
    {
        $resource = new FakeSheetsValuesResource([
            ['Record_ID', 'Equals', 'Plus', 'Minus', 'At', 'Integer', 'Decimal', 'Empty'],
        ]);
        $repository = new TestSheetRepository($resource);

        $result = $repository->append([
            'Record_ID' => 'REC-001',
            'Equals' => '=SUM(A1:A2)',
            'Plus' => '+cmd',
            'Minus' => '-cmd',
            'At' => '@payload',
            'Integer' => 100,
            'Decimal' => 4.5,
            'Empty' => null,
        ]);

        $this->assertTrue($result);
        $this->assertSame('RAW', $resource->appendParams['valueInputOption']);
        $this->assertSame([
            'REC-001', '=SUM(A1:A2)', '+cmd', '-cmd', '@payload', 100, 4.5, '',
        ], $resource->appendBody->getValues()[0]);
    }

    public function legacyUpdateWritesFormulaLikeValuesAsRaw(): void
    {
        $resource = new FakeSheetsValuesResource([
            ['Record_ID', 'Value'],
            ['REC-001', 'old'],
        ]);
        $repository = new TestSheetRepository($resource);

        $this->assertTrue($repository->update('rec-001', ['Value' => '=SUM(A1:A2)']));
        $this->assertSame('RAW', $resource->updateParams['valueInputOption']);
        $this->assertSame([['REC-001', '=SUM(A1:A2)']], $resource->updateBody->getValues());
    }

    public function legacyUpdateIgnoresPrimaryKeyPayloadAndPreservesRouteIdentity(): void
    {
        $resource = new FakeSheetsValuesResource([
            ['Record_ID', 'Value', 'Created_At'],
            ['REC-001', 'old', '2026-01-01 00:00:00'],
        ]);
        $repository = new TestSheetRepository($resource);

        $this->assertTrue($repository->update('REC-001', [
            'Record_ID' => 'REC-999',
            'Value' => 'new',
        ]));

        $this->assertSame([
            ['REC-001', 'new', '2026-01-01 00:00:00'],
        ], $resource->updateBody->getValues());
    }

    public function legacyAppendRejectsMissingAndDuplicatePrimaryKeysWithoutMutation(): void
    {
        $resource = new FakeSheetsValuesResource([
            ['Record_ID', 'Value'],
            ['REC-001', 'existing'],
        ]);
        $repository = new TestSheetRepository($resource);

        try {
            $repository->append(['Value' => 'missing id']);
            $this->fail('PK kosong seharusnya ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('wajib diisi', $e->getMessage());
        }

        try {
            $repository->append(['Record_ID' => 'rec-001', 'Value' => 'duplicate']);
            $this->fail('PK duplikat seharusnya ditolak.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('sudah ada', $e->getMessage());
        }

        $this->assertSame(0, $resource->appendCalls);
    }

    public function legacyFinancialSchemaGuardRejectsCriticalFieldLossBeforeAppend(): void
    {
        $resource = new FakeSheetsValuesResource([
            ['Payment_ID', 'Amount_Paid'],
        ]);
        $repository = new FinancialSchemaGuardRepository($resource, 'FINANCE_PAYMENT');

        $this->expectException(FinancialIntegrityException::class);
        $repository->append([
            'Payment_ID' => 'PAY-001',
            'Amount_Paid' => 100,
            'Status' => 'Verified',
        ]);

        $this->assertSame(0, $resource->appendCalls);
    }

    public function legacyFinancialSchemaGuardAllowsOptionalOmissions(): void
    {
        $resource = new FakeSheetsValuesResource([
            [
                'Payment_ID', 'Invoice_ID', 'Student_ID', 'Amount_Paid',
                'Payment_Date', 'Payment_Method', 'Reference_Number', 'Proof_Image',
                'Status', 'Verified_By', 'Verified_At', 'Notes',
                'Created_By', 'Created_At', 'Updated_By', 'Updated_At',
                'Idempotency_Key', 'Idempotency_Fingerprint', 'Receipt_Number',
                'Payment_Type', 'Is_Active',
            ],
        ]);
        $repository = new FinancialSchemaGuardRepository($resource, 'FINANCE_PAYMENT');

        $this->assertTrue($repository->append([
            'Payment_ID' => 'PAY-002',
            'Amount_Paid' => 100,
        ]));
        $this->assertSame(1, $resource->appendCalls);
    }

    public function legacyFinancialSchemaGuardRejectsUpdateWhenRequiredColumnIsMissing(): void
    {
        $resource = new FakeSheetsValuesResource([
            ['Payment_ID', 'Amount_Paid'],
            ['PAY-003', 100],
        ]);
        $repository = new FinancialSchemaGuardRepository($resource, 'FINANCE_PAYMENT');

        $this->expectException(FinancialIntegrityException::class);
        $repository->update('PAY-003', ['Amount_Paid' => 101]);
        $this->assertNull($resource->updateBody);
    }

    public function legacyUpdateOfMissingRecordThrowsWithoutFalseSuccess(): void
    {
        $resource = new FakeSheetsValuesResource([
            ['Record_ID', 'Value'],
        ]);
        $repository = new TestSheetRepository($resource);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak ditemukan');

        $repository->update('REC-404', ['Value' => 'new']);
    }

    public function legacyEmptyDatasetAndMissingDeleteFailSafely(): void
    {
        $resource = new FakeSheetsValuesResource([]);
        $repository = new TestSheetRepository($resource);

        $this->assertCount(0, $repository->fetchAll());

        $this->expectException(\RuntimeException::class);
        $repository->hardDelete('REC-404');
    }

    public function legacyGoogleReadFailureIsNotMasqueradedAsEmptyDataset(): void
    {
        $resource = new FakeSheetsValuesResource([]);
        $resource->getException = new \RuntimeException('Google read failed');
        $repository = new TestSheetRepository($resource);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google read failed');

        $repository->fetchAll();
    }

    public function legacyIdentityRepositoryMutationClearsCrossModuleLookupCache(): void
    {
        Cache::put('all_students_lookup_map', collect([['Student_ID' => 'STALE']]), 300);
        Cache::put('all_classes_lookup_map', collect([['Class_ID' => 'STALE']]), 300);

        (new StudentRepository)->clearCache();

        $this->assertFalse(Cache::has('all_students_lookup_map'));
        $this->assertFalse(Cache::has('all_classes_lookup_map'));
    }
}

class TestSheetRepository extends BaseSheetRepository
{
    public function __construct(FakeSheetsValuesResource $resource)
    {
        $this->service = (object) ['spreadsheets_values' => $resource];
        $this->spreadsheetId = 'spreadsheet-test';
        $this->sheetName = 'TEST_SHEET';
        $this->cacheKey = 'test_sheet_'.spl_object_id($this);
        $this->primaryKey = 'Record_ID';
        $this->cacheTtl = 1;
    }
}

class FinancialSchemaGuardRepository extends BaseSheetRepository
{
    public function __construct(FakeSheetsValuesResource $resource, string $sheetName)
    {
        $this->service = (object) ['spreadsheets_values' => $resource];
        $this->spreadsheetId = 'spreadsheet-test';
        $this->sheetName = $sheetName;
        $this->cacheKey = 'schema_guard_'.spl_object_id($this);
        $this->primaryKey = $sheetName === 'FINANCE_INVOICE' ? 'Invoice_ID' : 'Payment_ID';
        $this->cacheTtl = 1;
    }
}

class FakeSheetsValuesResource
{
    public int $appendCalls = 0;

    public ?array $appendParams = null;

    public ?Google_Service_Sheets_ValueRange $appendBody = null;

    public ?array $updateParams = null;

    public ?Google_Service_Sheets_ValueRange $updateBody = null;

    public ?\Throwable $getException = null;

    public function __construct(private array $values) {}

    public function get($spreadsheetId, $range)
    {
        if ($this->getException) {
            throw $this->getException;
        }

        return new class($this->values)
        {
            public function __construct(private array $values) {}

            public function getValues(): array
            {
                return $this->values;
            }
        };
    }

    public function append($spreadsheetId, $range, $body, $params): bool
    {
        $this->appendCalls++;
        $this->appendBody = $body;
        $this->appendParams = $params;

        return true;
    }

    public function update($spreadsheetId, $range, $body, $params): bool
    {
        $this->updateBody = $body;
        $this->updateParams = $params;

        return true;
    }
}
