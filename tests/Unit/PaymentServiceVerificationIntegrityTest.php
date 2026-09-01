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
use App\Services\Finance\TransactionService;
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
        $this->actingAs(new GenericUser(['id' => 'USR-FINANCE', 'User_ID' => 'USR-FINANCE', 'Role' => 'FINANCE']));
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

    public function test_repeated_verified_request_is_rejected_as_terminal_transition(): void
    {
        $payment = [
            'Payment_ID' => 'PAY-VERIFIED',
            'Invoice_ID' => 'INV-001',
            'Amount_Paid' => 500000,
            'Status' => 'Verified',
        ];

        $paymentRepository = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepository->shouldReceive('getById')->once()->with('PAY-VERIFIED')->andReturn($payment);
        $paymentRepository->shouldReceive('getAll')->never();
        $paymentRepository->shouldNotReceive('update');

        $service = $this->makeService($paymentRepository);
        $this->expectException(\App\Exceptions\FinancialIntegrityException::class);
        $service->verifyPayment('PAY-VERIFIED', 'SPOOFED-ACTOR', 'Verified');
    }

    /** @dataProvider allowedVerificationTransitions */
    public function test_allowed_payment_state_transitions_are_explicit(string $from, string $to): void
    {
        $repo = Mockery::mock(PaymentRepositoryInterface::class);
        $stateRow = [
            'Payment_ID' => 'PAY-STATE', 'Invoice_ID' => 'INV-STATE',
            'Amount_Paid' => 100, 'Status' => $from, 'Payment_Method' => 'CASH', 'Payment_Date' => '2026-08-31',
        ];
        if ($to === 'Verified') {
            $verifiedRow = $stateRow;
            $verifiedRow['Status'] = 'Verified';
            $repo->shouldReceive('getById')->times(3)->with('PAY-STATE')->andReturnValues([$stateRow, $verifiedRow, $verifiedRow]);
        } else {
            $repo->shouldReceive('getById')->once()->with('PAY-STATE')->andReturn($stateRow);
        }
        $repo->shouldReceive('update')->once()->with('PAY-STATE', Mockery::on(fn ($data) => ($data['Status'] ?? '') === $to))->andReturn(true);
        $repo->shouldReceive('clearCache')->once();
        $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
        $txService = null;
        if ($to === 'Verified') {
            $repo->shouldReceive('getAll')->twice()->andReturn(collect());
            $invoiceRepo->shouldReceive('update')->once()->andReturn(true);
            $invoiceRepo->shouldReceive('clearCache')->once();
            $invoiceService = Mockery::mock(InvoiceService::class);
            $invoiceService->shouldReceive('getById')->twice()->with('INV-STATE')->andReturn([
                'Invoice_ID' => 'INV-STATE', 'Amount' => 100, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE',
            ]);
            $this->app->instance(InvoiceService::class, $invoiceService);
            $txService = Mockery::mock(TransactionService::class);
            $txService->shouldReceive('create')->once()->andReturnTrue();
            $this->app->instance(TransactionService::class, $txService);
        }
        $txRepo = Mockery::mock(TransactionRepositoryInterface::class);
        if ($to === 'Verified') {
            $txRepo->shouldReceive('fetchAll')->once()->andReturn(collect());
        }
        $service = new PaymentService($repo, $invoiceRepo, Mockery::mock(StudentRepositoryInterface::class), Mockery::mock(CompanyRepositoryInterface::class), new class implements AccountRepositoryInterface {
            public function fetchAll(){ return collect([['Account_ID'=>'ACC-1','Account_Code'=>'101','Account_Name'=>'Kas Utama','Account_Category'=>'ASSET','Is_Active'=>'TRUE']]); }
            public function findById(string $id){ return null; } public function create(array $data){ return true; } public function update(string $id,array $data){ return true; } public function delete(string $id){ return true; } public function generateNewId(string $prefix='ACC',int $padding=6): string{return 'ACC-1';}
        }, $txRepo, Mockery::mock(EnterpriseEventService::class), $txService);
        $this->assertTrue($service->verifyPayment('PAY-STATE', 'ignored', $to) === true);
    }

    public static function allowedVerificationTransitions(): array
    {
        return [['Waiting Verification', 'Rejected'], ['Waiting Verification', 'Need Revision'],
            ['Need Revision', 'Rejected'], ['Need Revision', 'Need Revision'], ['Need Revision', 'Verified']];
    }

    public function test_need_revision_can_transition_to_verified(): void
    {
        $repo = Mockery::mock(PaymentRepositoryInterface::class);
        $pending = ['Payment_ID' => 'PAY-NR', 'Invoice_ID' => 'INV-NR', 'Amount_Paid' => 100,
            'Payment_Method' => 'CASH', 'Payment_Date' => '2026-08-31', 'Status' => 'Need Revision'];
        $verified = array_merge($pending, ['Status' => 'Verified']);
        $repo->shouldReceive('getById')->times(3)->with('PAY-NR')->andReturnValues([$pending, $verified, $verified]);
        $repo->shouldReceive('getAll')->twice()->andReturn(collect());
        $repo->shouldReceive('update')->once()->with('PAY-NR', Mockery::on(fn ($data) => ($data['Status'] ?? '') === 'Verified'))->andReturn(true);
        $repo->shouldReceive('clearCache')->once();
        $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepo->shouldReceive('update')->once()->andReturn(true);
        $invoiceRepo->shouldReceive('clearCache')->once();
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->twice()->with('INV-NR')->andReturn([
            'Invoice_ID' => 'INV-NR', 'Amount' => 100, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(InvoiceService::class, $invoiceService);
        $txService = Mockery::mock(TransactionService::class);
        $txService->shouldReceive('create')->once()->andReturnTrue();
        $this->app->instance(TransactionService::class, $txService);
        $txRepo = Mockery::mock(TransactionRepositoryInterface::class);
        $txRepo->shouldReceive('fetchAll')->once()->andReturn(collect());
        $service = new PaymentService($repo, $invoiceRepo, Mockery::mock(StudentRepositoryInterface::class), Mockery::mock(CompanyRepositoryInterface::class), new class implements AccountRepositoryInterface {
            public function fetchAll(){ return collect([['Account_ID'=>'ACC-1','Account_Code'=>'101','Account_Name'=>'Kas Utama','Account_Category'=>'ASSET','Is_Active'=>'TRUE']]); }
            public function findById(string $id){ return null; } public function create(array $data){ return true; } public function update(string $id,array $data){ return true; } public function delete(string $id){ return true; } public function generateNewId(string $prefix='ACC',int $padding=6): string{return 'ACC-1';}
        }, $txRepo, Mockery::mock(EnterpriseEventService::class), $txService);
        $this->assertTrue($service->verifyPayment('PAY-NR', 'ignored', 'Verified'));
    }

    /** @dataProvider terminalPaymentStates */
    public function test_terminal_payment_states_cannot_reenter_verification(string $from, string $to): void
    {
        $repo = Mockery::mock(PaymentRepositoryInterface::class);
        $repo->shouldReceive('getById')->once()->with('PAY-TERM')->andReturn([
            'Payment_ID' => 'PAY-TERM', 'Invoice_ID' => 'INV-TERM', 'Amount_Paid' => 100, 'Status' => $from,
        ]);
        $repo->shouldNotReceive('update');
        $service = $this->makeService($repo);
        $this->expectException(\Exception::class);
        $service->verifyPayment('PAY-TERM', 'ignored', $to);
    }

    public static function terminalPaymentStates(): array
    {
        return [['Verified', 'Verified'], ['Verified', 'Rejected'], ['Verified', 'Cancelled'],
            ['Rejected', 'Verified'], ['Rejected', 'Need Revision'], ['Cancelled', 'Verified'],
            ['Cancelled', 'Rejected'], ['Cancelled', 'Need Revision'], ['Reversed', 'Verified'],
            ['Reversed', 'Rejected'], ['Reversed', 'Need Revision']];
    }

    public function test_deletion_re_reads_persisted_state_and_rejects_stale_cached_verified_payment(): void
    {
        $repo = Mockery::mock(PaymentRepositoryInterface::class);
        $repo->shouldReceive('getById')->twice()->with('PAY-STALE')->andReturnValues([
            ['Payment_ID' => 'PAY-STALE', 'Status' => 'Waiting Verification'],
            ['Payment_ID' => 'PAY-STALE', 'Status' => 'Verified'],
        ]);
        $repo->shouldNotReceive('update');
        $service = $this->makeService($repo);
        $this->expectException(\Exception::class);
        $service->deletePayment('PAY-STALE');
    }

    /** @dataProvider destructiveTerminalStates */
    public function test_destructive_payment_mutation_rejects_all_terminal_states(string $state): void
    {
        $repo = Mockery::mock(PaymentRepositoryInterface::class);
        $repo->shouldReceive('getById')->once()->with('PAY-TERM-DEL')->andReturn(['Payment_ID' => 'PAY-TERM-DEL', 'Status' => $state]);
        $repo->shouldNotReceive('update');
        $service = $this->makeService($repo);
        $this->expectException(\Exception::class);
        $service->deletePayment('PAY-TERM-DEL');
    }

    public static function destructiveTerminalStates(): array
    {
        return [['Verified'], ['Reversed'], ['Rejected'], ['Cancelled']];
    }

    public function test_destructive_payment_mutation_uses_persisted_state_when_cache_says_need_revision(): void
    {
        $repo = Mockery::mock(PaymentRepositoryInterface::class);
        $repo->shouldReceive('getById')->twice()->with('PAY-STALE-REV')->andReturnValues([
            ['Payment_ID' => 'PAY-STALE-REV', 'Status' => 'Need Revision'],
            ['Payment_ID' => 'PAY-STALE-REV', 'Status' => 'Reversed'],
        ]);
        $repo->shouldNotReceive('update');
        $service = $this->makeService($repo);
        $this->expectException(\Exception::class);
        $service->deletePayment('PAY-STALE-REV');
    }

    /** @dataProvider cancellablePaymentStates */
    public function test_only_waiting_or_need_revision_payments_can_be_cancelled(string $state): void
    {
        $row = ['Payment_ID' => 'PAY-CANCEL', 'Status' => $state, 'Student_ID' => 'STU-1'];
        $repo = Mockery::mock(PaymentRepositoryInterface::class);
        $repo->shouldReceive('getById')->twice()->with('PAY-CANCEL')->andReturn($row);
        $repo->shouldReceive('update')->once()->with('PAY-CANCEL', Mockery::on(fn ($data) => ($data['Status'] ?? '') === 'Cancelled'))->andReturn(true);
        $repo->shouldReceive('clearCache')->once();
        $service = $this->makeService($repo);
        $this->assertTrue($service->deletePayment('PAY-CANCEL'));
    }

    public static function cancellablePaymentStates(): array
    {
        return [['Waiting Verification'], ['Need Revision']];
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
