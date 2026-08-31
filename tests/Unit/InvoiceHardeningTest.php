<?php

namespace Tests\Unit;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Finance\InvoiceService;
use App\Repositories\GoogleSheets\BaseSheetRepository;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class InvoiceHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->actingAs(new GenericUser(['id' => 'USR-FINANCE', 'User_ID' => 'USR-FINANCE']));
    }

    public function test_allocator_uses_max_suffix_not_count_and_handles_gaps(): void
    {
        $repo = new HardeningInvoiceRepository([
            ['Invoice_ID' => 'INV-STU-' . date('Y') . '-000002'],
            ['Invoice_ID' => 'INV-STU-' . date('Y') . '-000004'],
            ['Invoice_ID' => 'INV-STU-' . date('Y') . '-000027'],
            ['Invoice_ID' => 'INV-STU-' . date('Y') . '-000031'],
            ['Invoice_ID' => 'INV-STU-' . (date('Y') - 1) . '-999999'],
            ['Invoice_ID' => 'INV-STU-' . date('Y') . '-MALFORMED'],
        ]);
        $service = $this->makeService($repo);

        $this->assertSame('INV-STU-' . date('Y') . '-000032', $service->generateInvoiceNumber('STUDENT'));
        $repo->rows[] = ['Invoice_ID' => 'INV-STU-' . date('Y') . '-000032'];
        Cache::put('invoice_counter_INV-STU_' . date('Y'), 4, 3600);
        $this->assertSame('INV-STU-' . date('Y') . '-000033', $service->generateInvoiceNumber('STUDENT'));
    }

    public function test_consecutive_allocations_are_unique_and_prefixes_years_are_isolated(): void
    {
        $repo = new HardeningInvoiceRepository([]);
        $service = $this->makeService($repo);
        $first = $service->generateInvoiceNumber('STUDENT');
        $second = $service->generateInvoiceNumber('STUDENT');
        $company = $service->generateInvoiceNumber('COMPANY');

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('INV-CORP-' . date('Y') . '-', $company);
    }

    public function test_duplicate_candidate_is_reconciled_without_overwriting_existing_row(): void
    {
        $repo = new HardeningInvoiceRepository([], true);
        $service = $this->makeService($repo);
        $invoice = $service->create([
            'Invoice_Type' => 'STUDENT',
            'Student_ID' => 'STU001',
            'Category' => 'Medical',
            'Due_Date' => now()->addDays(14)->format('Y-m-d'),
            'items' => [['description' => 'Medical', 'qty' => 1, 'unit_price' => 100]],
        ]);

        $this->assertStringEndsWith('000002', $invoice['Invoice_ID']);
        $this->assertCount(1, $repo->created);
    }

    public function test_same_idempotency_key_returns_first_invoice_only(): void
    {
        $repo = new HardeningInvoiceRepository([]);
        $service = $this->makeService($repo);
        $payload = [
            'Invoice_Type' => 'STUDENT', 'Student_ID' => 'STU001', 'Category' => 'Medical',
            'Due_Date' => now()->addDays(14)->format('Y-m-d'),
            'Idempotency_Key' => '11111111-1111-4111-8111-111111111111',
            'items' => [['description' => 'Medical', 'qty' => 1, 'unit_price' => 100]],
        ];
        $first = $service->create($payload);
        $second = $service->create($payload);

        $this->assertSame($first['Invoice_ID'], $second['Invoice_ID']);
        $this->assertCount(1, $repo->created);
    }

    public function test_form_contains_double_submit_guard(): void
    {
        $view = file_get_contents(resource_path('views/finance/invoices/create.blade.php'));
        $this->assertStringContainsString('Idempotency_Key', $view);
        $this->assertStringContainsString('dataset.submitting', $view);
    }

    public function test_repository_does_not_retry_deterministic_duplicate(): void
    {
        $resource = new HardeningSheetsResource([
            ['Record_ID', 'Value'], ['REC-001', 'existing'],
        ]);
        $repository = new HardeningSheetRepository($resource);

        $this->expectException(DuplicatePrimaryKeyException::class);
        $repository->append(['Record_ID' => 'REC-001', 'Value' => 'duplicate']);
        $this->assertSame(0, $resource->appendCalls);
    }

    public function test_ambiguous_append_is_verified_before_returning_success(): void
    {
        $resource = new HardeningSheetsResource([['Record_ID', 'Value']]);
        $resource->appendException = new \RuntimeException('network timeout');
        $resource->appendCommitsBeforeException = true;
        $repository = new HardeningSheetRepository($resource);

        $this->assertTrue($repository->append(['Record_ID' => 'REC-001', 'Value' => 'new']));
        $this->assertSame(1, $resource->appendCalls);
    }

    public function test_timeout_before_append_is_retried_but_not_after_persisted_write(): void
    {
        $resource = new HardeningSheetsResource([['Record_ID', 'Value']]);
        $resource->appendException = new \RuntimeException('timeout');
        $resource->appendExceptionOnce = true;
        $repository = new HardeningSheetRepository($resource);

        $this->assertTrue($repository->append(['Record_ID' => 'REC-002', 'Value' => 'ok']));
        $this->assertSame(2, $resource->appendCalls);
    }

    public function test_rate_limit_and_server_errors_are_bounded_retries(): void
    {
        foreach ([429, 503] as $status) {
            $resource = new HardeningSheetsResource([['Record_ID', 'Value']]);
            $resource->getException = new \RuntimeException('Google transient error', $status);
            $repository = new HardeningSheetRepository($resource);

            try {
                $repository->append(['Record_ID' => 'REC-003', 'Value' => 'x']);
                $this->fail('Transient error seharusnya dilempar setelah bounded retry.');
            } catch (\RuntimeException $e) {
                $this->assertSame($status, $e->getCode());
                $this->assertSame(4, $resource->getCalls);
            }
        }
    }

    public function test_permanent_client_error_is_not_retried(): void
    {
        $resource = new HardeningSheetsResource([['Record_ID', 'Value']]);
        $resource->getException = new \RuntimeException('bad request', 400);
        $repository = new HardeningSheetRepository($resource);

        try {
            $repository->append(['Record_ID' => 'REC-004', 'Value' => 'x']);
            $this->fail('Permanent 4xx seharusnya dilempar.');
        } catch (\RuntimeException $e) {
            $this->assertSame(400, $e->getCode());
        }
        $this->assertSame(1, $resource->getCalls);
    }

    public function test_primary_invoice_success_is_returned_when_side_effect_dispatch_fails(): void
    {
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('notification down'));
        $service = $this->makeService(new HardeningInvoiceRepository([]), $events);

        $invoice = $service->create([
            'Invoice_Type' => 'STUDENT', 'Student_ID' => 'STU001', 'Category' => 'Medical',
            'Due_Date' => now()->addDays(14)->format('Y-m-d'),
            'items' => [['description' => 'Medical', 'qty' => 1, 'unit_price' => 100]],
        ]);

        $this->assertStringStartsWith('INV-STU-', $invoice['Invoice_ID']);
    }

    private function makeService(HardeningInvoiceRepository $repo, ?EnterpriseEventService $events = null): InvoiceService
    {
        $student = Mockery::mock(StudentRepositoryInterface::class);
        $student->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(['Student_ID' => 'STU001', 'Program_ID' => '', 'Batch_ID' => '']);
        $company = Mockery::mock(CompanyRepositoryInterface::class);
        $payment = Mockery::mock(PaymentRepositoryInterface::class);
        $payment->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());
        if ($events === null) {
            $events = Mockery::mock(EnterpriseEventService::class);
            $events->shouldReceive('dispatch')->zeroOrMoreTimes()->andReturn(true);
        }
        return new InvoiceService($repo, $events, $student, $company, $payment);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

class HardeningInvoiceRepository implements InvoiceRepositoryInterface
{
    public array $rows;
    public array $created = [];
    private bool $duplicateOnce;

    public function __construct(array $rows, bool $duplicateOnce = false)
    {
        $this->rows = $rows;
        $this->duplicateOnce = $duplicateOnce;
    }
    public function getAll() { return collect($this->rows); }
    public function getAllFresh() { return collect($this->rows); }
    public function getById($id) { return collect($this->rows)->firstWhere('Invoice_ID', $id); }
    public function findByIdFresh($id) { return $this->getById($id); }
    public function create(array $data) {
        if ($this->duplicateOnce) {
            $this->duplicateOnce = false;
            throw new DuplicatePrimaryKeyException('duplicate');
        }
        $this->created[] = $data;
        $this->rows[] = $data;
        return true;
    }
    public function update($id, array $data) { return true; }
    public function delete($id) { return true; }
    public function clearCache() {}
}

class HardeningSheetRepository extends BaseSheetRepository
{
    public function __construct(HardeningSheetsResource $resource)
    {
        $this->service = (object) ['spreadsheets_values' => $resource];
        $this->spreadsheetId = 'test';
        $this->sheetName = 'TEST_SHEET';
        $this->cacheKey = 'hardening_sheet_' . spl_object_id($this);
        $this->primaryKey = 'Record_ID';
        $this->cacheTtl = 1;
    }
}

class HardeningSheetsResource
{
    public int $appendCalls = 0;
    public int $getCalls = 0;
    public ?\Throwable $getException = null;
    public ?\Throwable $appendException = null;
    public bool $appendExceptionOnce = false;
    public bool $appendCommitsBeforeException = false;
    public function __construct(public array $values) {}
    public function get($spreadsheetId, $range) {
        $this->getCalls++;
        if ($this->getException) {
            throw $this->getException;
        }
        return new class($this->values) { public function __construct(private array $values) {} public function getValues(): array { return $this->values; } };
    }
    public function append($spreadsheetId, $range, $body, $params) {
        $this->appendCalls++;
        if ($this->appendException && ($this->appendExceptionOnce === false || $this->appendCalls === 1)) {
            $e = $this->appendException;
            if ($this->appendExceptionOnce) { $this->appendException = null; }
            if ($this->appendCommitsBeforeException) { $this->values[] = $body->getValues()[0]; }
            throw $e;
        }
        $this->values[] = $body->getValues()[0];
        return true;
    }
}
