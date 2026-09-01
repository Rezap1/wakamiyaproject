<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class InvoiceServiceTuitionCapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 'USR-FINANCE', 'User_ID' => 'USR-FINANCE', 'Role' => 'FINANCE']));
        Cache::flush();

        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('getDefaultTuitionFee')->zeroOrMoreTimes()->andReturn(7500000.0);
        $this->app->instance(SystemSettingService::class, $settings);

        $programRepo = Mockery::mock(ProgramRepositoryInterface::class);
        $programRepo->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(null);
        $this->app->instance(ProgramRepositoryInterface::class, $programRepo);

        $batchRepo = Mockery::mock(BatchRepositoryInterface::class);
        $batchRepo->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(null);
        $this->app->instance(BatchRepositoryInterface::class, $batchRepo);
    }

    public function test_rejects_new_education_invoice_when_existing_legacy_invoice_already_reaches_tuition_cap(): void
    {
        $service = $this->makeService([
            [
                'Invoice_ID' => 'INV-OLD',
                'Invoice_Type' => 'STUDENT',
                'Student_ID' => 'STU001',
                'Category' => '',
                'Description' => 'Tagihan biaya pendidikan awal siswa',
                'Line_Items' => json_encode([
                    ['description' => 'Biaya Pendidikan Pokok', 'qty' => 1, 'unit_price' => 7500000, 'subtotal' => 7500000],
                ]),
                'Amount' => 7500000,
                'Status' => 'Waiting Payment',
                'Is_Active' => 'TRUE',
            ],
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('tidak boleh melebihi plafon');

        $service->create([
            'Invoice_ID' => 'INV-NEW',
            'Invoice_Type' => 'STUDENT',
            'Student_ID' => 'STU001',
            'Category' => 'SPP / Biaya Pendidikan',
            'Due_Date' => now()->addDays(14)->format('Y-m-d'),
            'items' => [
                ['description' => 'SPP Bulan Ini', 'qty' => 1, 'unit_price' => 100000],
            ],
        ]);
    }

    public function test_allows_education_invoice_until_remaining_tuition_cap_and_normalizes_category(): void
    {
        $repository = Mockery::mock(InvoiceRepositoryInterface::class);
        $repository->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect([
            [
                'Invoice_ID' => 'INV-OLD',
                'Invoice_Type' => 'STUDENT',
                'Student_ID' => 'STU001',
                'Category' => 'Biaya Pendidikan',
                'Amount' => 5000000,
                'Status' => 'Waiting Payment',
                'Is_Active' => 'TRUE',
            ],
        ]));
        $repository->shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            return ($data['Category'] ?? '') === 'Biaya Pendidikan'
                && (float) ($data['Amount'] ?? 0) === 2500000.0;
        }))->andReturn(['Invoice_ID' => 'INV-STU-TEST']);
        $repository->shouldReceive('clearCache')->once();

        $service = $this->makeService([], $repository);

        $invoice = $service->create([
            'Invoice_ID' => 'INV-NEW',
            'Invoice_Type' => 'STUDENT',
            'Student_ID' => 'STU001',
            'Category' => 'SPP / Biaya Pendidikan',
            'Due_Date' => now()->addDays(14)->format('Y-m-d'),
            'items' => [
                ['description' => 'Biaya Pendidikan', 'qty' => 1, 'unit_price' => 2500000],
            ],
        ]);

        $this->assertSame('Biaya Pendidikan', $invoice['Category']);
        $this->assertSame(2500000.0, (float) $invoice['Amount']);
    }

    private function makeService(array $invoices, ?InvoiceRepositoryInterface $repository = null): InvoiceService
    {
        if ($repository === null) {
            $repository = Mockery::mock(InvoiceRepositoryInterface::class);
            $repository->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect($invoices));
        }

        $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepo->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('findById')->zeroOrMoreTimes()->with('STU001')->andReturn([
            'Student_ID' => 'STU001',
            'Full_Name' => 'Syahwal',
            'Program_ID' => '',
            'Batch_ID' => '',
        ]);

        $companyRepo = Mockery::mock(CompanyRepositoryInterface::class);

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->zeroOrMoreTimes();

        return new InvoiceService($repository, $enterpriseEvent, $studentRepo, $companyRepo, $paymentRepo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
