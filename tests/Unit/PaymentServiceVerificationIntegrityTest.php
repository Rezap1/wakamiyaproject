<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PaymentServiceVerificationIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 'USR-FINANCE', 'User_ID' => 'USR-FINANCE']));
        Cache::flush();
    }

    public function test_verified_payment_status_is_immutable_to_prevent_finance_transaction_tampering(): void
    {
        $paymentRepository = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepository->shouldReceive('getById')->once()->with('PAY-VERIFIED')->andReturn([
            'Payment_ID' => 'PAY-VERIFIED',
            'Invoice_ID' => 'INV-001',
            'Amount_Paid' => 500000,
            'Status' => 'Verified',
        ]);
        $paymentRepository->shouldReceive('update')->never();

        $service = $this->makeService($paymentRepository);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah terverifikasi');

        $service->verifyPayment('PAY-VERIFIED', 'USR-FINANCE', 'Rejected', 'Kesalahan status');
    }

    public function test_verification_rejects_payment_that_would_overpay_invoice(): void
    {
        $paymentRepository = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepository->shouldReceive('getById')->once()->with('PAY-OVERPAY')->andReturn([
            'Payment_ID' => 'PAY-OVERPAY',
            'Invoice_ID' => 'INV-OVERPAY',
            'Amount_Paid' => 3000000,
            'Status' => 'Waiting Verification',
        ]);
        $paymentRepository->shouldReceive('getAll')->once()->andReturn(collect([
            [
                'Payment_ID' => 'PAY-OLD',
                'Invoice_ID' => 'INV-OVERPAY',
                'Amount_Paid' => 8000000,
                'Status' => 'Verified',
            ],
            [
                'Payment_ID' => 'PAY-OVERPAY',
                'Invoice_ID' => 'INV-OVERPAY',
                'Amount_Paid' => 3000000,
                'Status' => 'Waiting Verification',
            ],
        ]));
        $paymentRepository->shouldReceive('update')->never();

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->with('INV-OVERPAY')->andReturn([
            'Invoice_ID' => 'INV-OVERPAY',
            'Amount' => 10000000,
            'Status' => 'Partial Paid',
        ]);
        $this->app->instance(InvoiceService::class, $invoiceService);

        $service = $this->makeService($paymentRepository);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('melebihi sisa tagihan');

        $service->verifyPayment('PAY-OVERPAY', 'USR-FINANCE', 'Verified', 'Validasi pembayaran');
    }

    public function test_verified_payment_cannot_be_deleted_after_cash_transaction_is_created(): void
    {
        $paymentRepository = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepository->shouldReceive('getById')->once()->with('PAY-VERIFIED')->andReturn([
            'Payment_ID' => 'PAY-VERIFIED',
            'Invoice_ID' => 'INV-001',
            'Amount_Paid' => 500000,
            'Status' => 'Verified',
        ]);
        $paymentRepository->shouldReceive('delete')->never();

        $service = $this->makeService($paymentRepository);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('tidak dapat dihapus');

        $service->deletePayment('PAY-VERIFIED');
    }

    public function test_verification_persistence_failure_does_not_report_success_or_run_reconciliation(): void
    {
        $paymentRepository = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepository->shouldReceive('getById')->once()->with('PAY-PENDING')->andReturn([
            'Payment_ID' => 'PAY-PENDING',
            'Invoice_ID' => 'INV-001',
            'Amount_Paid' => 500000,
            'Status' => 'Waiting Verification',
        ]);
        $paymentRepository->shouldReceive('getAll')->once()->andReturn(collect());
        $paymentRepository->shouldReceive('update')->once()->andReturn(false);
        $paymentRepository->shouldNotReceive('clearCache');

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->with('INV-001')->andReturn([
            'Invoice_ID' => 'INV-001',
            'Amount' => 500000,
            'Status' => 'Issued',
        ]);
        $this->app->instance(InvoiceService::class, $invoiceService);

        $service = $this->makeService($paymentRepository);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Gagal menyimpan status pembayaran');

        $service->verifyPayment('PAY-PENDING', 'SPOOFED-ACTOR', 'Verified');
    }

    public function test_repeated_verified_request_repairs_invoice_state_without_duplicate_transaction(): void
    {
        $payment = [
            'Payment_ID' => 'PAY-VERIFIED',
            'Invoice_ID' => 'INV-001',
            'Amount_Paid' => 500000,
            'Status' => 'Verified',
        ];

        $paymentRepository = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepository->shouldReceive('getById')->once()->with('PAY-VERIFIED')->andReturn($payment);
        $paymentRepository->shouldReceive('getAll')->once()->andReturn(collect([$payment]));
        $paymentRepository->shouldNotReceive('update');

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->with('INV-001')->andReturn([
            'Invoice_ID' => 'INV-001',
            'Amount' => 500000,
            'Status' => 'Partial Paid',
        ]);
        $this->app->instance(InvoiceService::class, $invoiceService);

        $invoiceRepository = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepository->shouldReceive('update')->once()->with('INV-001', Mockery::on(
            fn ($row) => ($row['Status'] ?? '') === 'Paid'
        ))->andReturn(true);
        $invoiceRepository->shouldReceive('clearCache')->once();

        $transactionRepository = Mockery::mock(TransactionRepositoryInterface::class);
        $transactionRepository->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Transaction_ID' => 'TRX-001', 'Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-VERIFIED'],
        ]));

        $service = new PaymentService(
            $paymentRepository,
            $invoiceRepository,
            Mockery::mock(StudentRepositoryInterface::class),
            Mockery::mock(CompanyRepositoryInterface::class),
            Mockery::mock(AccountRepositoryInterface::class),
            $transactionRepository,
            Mockery::mock(EnterpriseEventService::class)
        );

        $result = $service->verifyPayment('PAY-VERIFIED', 'SPOOFED-ACTOR', 'Verified');

        $this->assertSame('PAY-VERIFIED', $result['Payment_ID']);
    }

    private function makeService(PaymentRepositoryInterface $paymentRepository): PaymentService
    {
        return new PaymentService(
            $paymentRepository,
            Mockery::mock(InvoiceRepositoryInterface::class),
            Mockery::mock(StudentRepositoryInterface::class),
            Mockery::mock(CompanyRepositoryInterface::class),
            Mockery::mock(AccountRepositoryInterface::class),
            Mockery::mock(TransactionRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
