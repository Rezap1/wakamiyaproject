<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Repositories\GoogleSheets\TransactionRepository as ConcreteTransactionRepository;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\NotificationService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\TransactionService;
use App\Console\Commands\SendInvoiceReminders;
use App\Services\Dashboard\FinanceDashboardService;
use App\Services\Finance\FinanceReportService;
use App\Services\Core\ActivityLogService;
use App\Support\Finance\Money;
use App\Exceptions\FinancialIntegrityException;
use App\Exceptions\AmbiguousSheetWriteException;
use App\Exceptions\DuplicatePrimaryKeyException;
use Illuminate\Auth\GenericUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Illuminate\Console\OutputStyle;

class FinanceHardeningIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->actingAs(new GenericUser(['id' => 'USR-FIN', 'User_ID' => 'USR-FIN', 'Role' => 'FINANCE']));
    }

    public function test_two_payments_for_one_invoice_cannot_cross_the_overpayment_boundary(): void
    {
        $payments = new IntegrityPaymentRepository([
            ['Payment_ID' => 'PAY-A', 'Invoice_ID' => 'INV-1', 'Amount_Paid' => 700, 'Payment_Method' => 'CASH', 'Payment_Date' => '2026-08-31', 'Status' => 'Waiting Verification', 'Student_ID' => 'STU-1'],
            ['Payment_ID' => 'PAY-B', 'Invoice_ID' => 'INV-1', 'Amount_Paid' => 500, 'Payment_Method' => 'CASH', 'Payment_Date' => '2026-08-31', 'Status' => 'Waiting Verification', 'Student_ID' => 'STU-1'],
        ]);
        $invoices = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-1', 'Amount' => 1000, 'Status' => 'Waiting Payment', 'Student_ID' => 'STU-1']]);
        $transactions = new IntegrityTransactionRepository();
        $this->app->instance(InvoiceService::class, Mockery::mock(InvoiceService::class));
        $transactionService = Mockery::mock(TransactionService::class);
        $transactionService->shouldReceive('create')->once()->with(Mockery::on(fn ($data) => ($data['Transaction_Date'] ?? '') === '2026-08-31'))->andReturnTrue();
        $this->app->instance(TransactionService::class, $transactionService);
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class));

        $service->verifyPayment('PAY-A', 'ignored', 'Verified');
        $this->expectExceptionMessage('melebihi sisa tagihan');
        $service->verifyPayment('PAY-B', 'ignored', 'Verified');
        $this->assertSame(700, collect($payments->rows)->where('Status', 'Verified')->sum('Amount_Paid'));
    }

    public function test_receipt_allocator_uses_max_suffix_and_ignores_malformed_ids(): void
    {
        $payments = new IntegrityPaymentRepository([
            ['Payment_ID' => 'RCT-STU-' . date('Y') . '-000001'],
            ['Payment_ID' => 'RCT-STU-' . date('Y') . '-000003'],
            ['Payment_ID' => 'RCT-STU-' . date('Y') . '-MALFORMED'],
        ]);
        $service = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $this->assertSame('RCT-STU-' . date('Y') . '-000004', $service->generateReceiptNumber('STUDENT'));
    }

    public function test_student_transfer_date_becomes_payment_date(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->andReturn([
            'Invoice_ID' => 'INV-1', 'Invoice_Type' => 'STUDENT', 'Student_ID' => 'STU-1',
            'Status' => 'Waiting Payment', 'Amount' => 1000, 'Is_Active' => 'TRUE',
        ]);
        $invoiceService->shouldNotReceive('calculateRemainingAmount');
        $this->app->instance(InvoiceService::class, $invoiceService);
        $payments = new IntegrityPaymentRepository();
        $service = new PaymentService($payments, Mockery::mock(InvoiceRepositoryInterface::class), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));

        $result = $service->submitPayment([
            'Invoice_ID' => 'INV-1', 'Student_ID' => 'STU-1', 'Amount_Paid' => 1000,
            'Transfer_Date' => '2026-08-31', 'Idempotency_Key' => '11111111-1111-4111-8111-111111111111',
        ]);
        $this->assertSame('2026-08-31', $result['Payment_Date']);
    }

    public function test_money_rejects_non_finite_values_and_rounds_explicitly(): void
    {
        $this->assertSame(100.01, Money::value('100.005'));
        $this->expectException(\InvalidArgumentException::class);
        Money::value(INF);
    }

    public function test_transaction_fails_closed_for_unknown_account(): void
    {
        $service = new TransactionService(Mockery::mock(TransactionRepositoryInterface::class), new IntegrityAccountRepository(), Mockery::mock(EnterpriseEventService::class));
        $this->expectExceptionMessage('tidak ditemukan atau tidak aktif');
        $service->create([
            'Transaction_Date' => '2026-08-31', 'Account_ID' => 'MISSING', 'Type' => 'Expense',
            'Category' => 'Test', 'Amount' => 100, 'Reference_Type' => 'Other',
        ]);
    }

    public function test_payment_idempotency_returns_the_same_persisted_payment(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->andReturn([
            'Invoice_ID' => 'INV-1', 'Invoice_Type' => 'STUDENT', 'Student_ID' => 'STU-1',
            'Status' => 'Waiting Payment', 'Amount' => 1000, 'Is_Active' => 'TRUE',
        ]);
        $invoiceService->shouldNotReceive('calculateRemainingAmount');
        $this->app->instance(InvoiceService::class, $invoiceService);
        $payments = new IntegrityPaymentRepository();
        $service = new PaymentService($payments, Mockery::mock(InvoiceRepositoryInterface::class), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $payload = ['Invoice_ID' => 'INV-1', 'Student_ID' => 'STU-1', 'Amount_Paid' => 100, 'Payment_Method' => 'CASH', 'Idempotency_Key' => '22222222-2222-4222-8222-222222222222'];
        $first = $service->submitPayment($payload);
        $second = $service->submitPayment($payload);
        Cache::flush();
        $third = $service->submitPayment($payload);
        $this->assertSame($first['Payment_ID'], $second['Payment_ID']);
        $this->assertSame($first['Payment_ID'], $third['Payment_ID']);
        $this->assertCount(1, $payments->rows);
    }

    public function test_payment_idempotency_rejects_same_token_with_different_payload(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->andReturn([
            'Invoice_ID' => 'INV-1', 'Invoice_Type' => 'STUDENT', 'Student_ID' => 'STU-1',
            'Status' => 'Waiting Payment', 'Amount' => 1000, 'Is_Active' => 'TRUE',
        ]);
        $invoiceService->shouldNotReceive('calculateRemainingAmount');
        $this->app->instance(InvoiceService::class, $invoiceService);
        $service = new PaymentService(new IntegrityPaymentRepository(), Mockery::mock(InvoiceRepositoryInterface::class), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $payload = ['Invoice_ID' => 'INV-1', 'Student_ID' => 'STU-1', 'Amount_Paid' => 100, 'Payment_Method' => 'CASH', 'Idempotency_Key' => '33333333-3333-4333-8333-333333333333'];
        $service->submitPayment($payload);
        $this->expectException(\App\Exceptions\FinancialIntegrityException::class);
        $service->submitPayment(array_merge($payload, ['Amount_Paid' => 101]));
    }

    public function test_payment_idempotency_survives_fresh_service_and_stale_cache(): void
    {
        $payload = [
            'Self_Service' => true, 'Amount_Paid' => 250, 'Payment_Method' => 'TRANSFER',
            'Sender_Name' => 'Student A', 'Transfer_Date' => '2026-09-01',
            'Idempotency_Key' => '88888888-8888-4888-8888-888888888888',
        ];
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        $payments = new IntegrityPaymentRepository();
        $service = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $first = $service->submitPayment($payload);

        // Simulate a fresh PHP process and a cache entry pointing at a row
        // that no longer exists.  Durable lookup must still converge on the
        // one persisted payment.
        Cache::flush();
        $cacheKey = 'payment_idempotency_' . hash('sha256', 'USR-STU:' . $payload['Idempotency_Key']);
        Cache::put($cacheKey, ['status' => 'completed', 'payment_id' => 'PAY-NOT-PERSISTED', 'fingerprint' => 'stale'], 3600);
        $freshProcess = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $retry = $freshProcess->submitPayment($payload);

        $this->assertSame($first['Payment_ID'], $retry['Payment_ID']);
        $this->assertCount(1, $payments->rows);
    }

    public function test_duplicate_persisted_idempotency_identity_fails_closed(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        $payload = [
            'Self_Service' => true, 'Amount_Paid' => 250, 'Payment_Method' => 'TRANSFER',
            'Sender_Name' => 'Student A', 'Transfer_Date' => '2026-09-01',
            'Idempotency_Key' => '99999999-9999-4999-8999-999999999999',
        ];
        $payments = new IntegrityPaymentRepository();
        $service = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $first = $service->submitPayment($payload);
        $payments->rows[] = $first; // forensic duplicate already persisted

        $this->expectException(FinancialIntegrityException::class);
        $service->submitPayment($payload);
    }

    public function test_deterministic_payment_id_collision_with_different_record_fails_closed(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        $key = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $paymentId = 'PAY-' . strtoupper(substr(hash('sha256', 'USR-STU:' . $key), 0, 24));
        $payments = new IntegrityPaymentRepository([[
            'Payment_ID' => $paymentId, 'Idempotency_Key' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Idempotency_Fingerprint' => hash('sha256', 'other'), 'Created_By' => 'USR-STU',
            'Payment_Type' => 'STUDENT_SELF_SERVICE', 'Student_ID' => 'STU-1',
            'Invoice_ID' => '', 'Payment_Method' => 'TRANSFER', 'Payment_Date' => '2026-09-01',
            'Amount_Paid' => 999, 'Status' => 'Waiting Verification', 'Is_Active' => 'TRUE',
        ]]);
        $service = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));

        $this->expectException(FinancialIntegrityException::class);
        $service->submitPayment([
            'Self_Service' => true, 'Amount_Paid' => 250, 'Payment_Method' => 'TRANSFER',
            'Sender_Name' => 'Student A', 'Transfer_Date' => '2026-09-01', 'Idempotency_Key' => $key,
        ]);
    }

    public function test_invoice_amount_mismatch_fails_closed(): void
    {
        $service = new InvoiceService(
            new IntegrityInvoiceRepository(), Mockery::mock(EnterpriseEventService::class),
            new IntegrityStudentRepository(), new IntegrityCompanyRepository(),
            new IntegrityPaymentRepository()
        );
        $this->expectException(\App\Exceptions\FinancialIntegrityException::class);
        $service->formatInvoiceRecord([
            'Invoice_ID' => 'INV-BAD', 'Amount' => 150,
            'Line_Items' => json_encode([['description' => 'X', 'qty' => 1, 'unit_price' => 100]]),
            'Status' => 'Waiting Payment',
        ]);
    }

    public function test_verified_payment_reversal_preserves_original_and_creates_compensation(): void
    {
        $payments = new IntegrityPaymentRepository([
            ['Payment_ID' => 'PAY-1', 'Invoice_ID' => 'INV-1', 'Amount_Paid' => 100, 'Payment_Date' => '2026-08-31', 'Status' => 'Verified', 'Student_ID' => 'STU-1'],
        ]);
        $invoices = new IntegrityInvoiceRepository([
            ['Invoice_ID' => 'INV-1', 'Amount' => 100, 'Status' => 'Paid', 'Student_ID' => 'STU-1'],
        ]);
        $transactions = new IntegrityTransactionRepository();
        $transactions->rows[] = ['Transaction_ID' => 'TRX-1', 'Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-1', 'Account_ID' => 'ACC-1', 'Type' => 'Income', 'Amount' => 100, 'Is_Active' => 'TRUE'];
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->zeroOrMoreTimes()->andReturn($invoices->getById('INV-1'));
        $this->app->instance(InvoiceService::class, $invoiceService);
        $transactionService = Mockery::mock(TransactionService::class);
        $transactionService->shouldReceive('create')->once()->with(Mockery::on(fn ($data) =>
            ($data['Type'] ?? '') === 'Expense'
            && ($data['Reference_Type'] ?? '') === 'PaymentReversal'
            && ($data['Reference_ID'] ?? '') === 'PAY-1'
        ))->andReturnTrue();
        $this->app->instance(TransactionService::class, $transactionService);
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class));
        $result = $service->reversePayment('PAY-1', 'Duplikasi transfer');
        $this->assertSame('Reversed', $result['Status']);
        $this->assertSame('Reversed', $payments->getById('PAY-1')['Status']);
    }

    public function test_invoice_reminder_command_uses_notification_array_contract(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getAll')->once()->andReturn(collect([[
            'Invoice_ID' => 'INV-REM', 'Student_ID' => 'STU-1', 'Category' => 'SPP',
            'Amount' => 100, 'Status' => 'Waiting Payment', 'Due_Date' => now()->addDays(7)->toDateString(),
        ]]));
        $notification = Mockery::mock(NotificationService::class);
        $notification->shouldReceive('CreateNotification')->once()->with(Mockery::on(fn ($data) =>
            ($data['Reference_ID'] ?? '') === 'INV-REM' && ($data['User_ID'] ?? '') === 'STU-1'
        ));
        $command = new SendInvoiceReminders();
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput()));
        $exit = $command->handle($invoiceService, $notification);
        $this->assertNull($exit);
    }

    public function test_invoice_reminder_notification_failure_is_logged_without_command_failure(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getAll')->once()->andReturn(collect([[
            'Invoice_ID' => 'INV-FAIL', 'Student_ID' => 'STU-1', 'Category' => 'SPP',
            'Amount' => 100, 'Status' => 'Waiting Payment', 'Due_Date' => now()->addDays(7)->toDateString(),
        ]]));
        $notification = Mockery::mock(NotificationService::class);
        $notification->shouldReceive('CreateNotification')->once()->andThrow(new \RuntimeException('temporary notification outage'));
        $command = new SendInvoiceReminders();
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput()));
        $this->assertNull($command->handle($invoiceService, $notification));
    }

    public function test_invoice_reminder_skips_malformed_invoice_and_processes_next_invoice(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getAll')->once()->andReturn(collect([
            ['Invoice_ID' => 'INV-BAD-DATE', 'Student_ID' => 'STU-1', 'Status' => 'Waiting Payment', 'Due_Date' => '2026-99-99'],
            ['Invoice_ID' => 'INV-GOOD-DATE', 'Student_ID' => 'STU-2', 'Status' => 'Waiting Payment', 'Due_Date' => now()->addDays(7)->toDateString()],
        ]));
        $notification = Mockery::mock(NotificationService::class);
        $notification->shouldReceive('CreateNotification')->once()->with(Mockery::on(fn ($data) => ($data['Reference_ID'] ?? '') === 'INV-GOOD-DATE'));
        $command = new SendInvoiceReminders();
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput()));
        $this->assertNull($command->handle($invoiceService, $notification));
    }

    public function test_invoice_reminder_source_read_failure_is_explicit_failure(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getAll')->once()->andThrow(new \RuntimeException('sheet read unavailable'));
        $notification = Mockery::mock(NotificationService::class);
        $command = new SendInvoiceReminders();
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput()));
        $this->assertSame(Command::FAILURE, $command->handle($invoiceService, $notification));
    }

    public function test_student_billing_invoice_list_reads_payments_once_for_multiple_invoices(): void
    {
        $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepo->shouldReceive('getAll')->once()->andReturn(collect([
            ['Invoice_ID' => 'INV-1', 'Student_ID' => 'STU-1', 'Amount' => 100, 'Status' => 'Waiting Payment', 'Due_Date' => now()->addDays(7)->toDateString(), 'Is_Active' => 'TRUE'],
            ['Invoice_ID' => 'INV-2', 'Student_ID' => 'STU-1', 'Amount' => 200, 'Status' => 'Waiting Payment', 'Due_Date' => now()->addDays(7)->toDateString(), 'Is_Active' => 'TRUE'],
            ['Invoice_ID' => 'INV-3', 'Student_ID' => 'STU-1', 'Amount' => 300, 'Status' => 'Waiting Payment', 'Due_Date' => now()->addDays(7)->toDateString(), 'Is_Active' => 'TRUE'],
        ]));

        $paymentRepo = new CountingFreshPaymentRepository([
            ['Payment_ID' => 'PAY-1', 'Invoice_ID' => 'INV-1', 'Amount_Paid' => 40, 'Status' => 'Verified'],
            ['Payment_ID' => 'PAY-2', 'Invoice_ID' => 'INV-2', 'Amount_Paid' => 200, 'Status' => 'Verified'],
            ['Payment_ID' => 'PAY-3', 'Invoice_ID' => 'INV-2', 'Amount_Paid' => 25, 'Status' => 'Waiting Verification'],
            ['Payment_ID' => 'PAY-4', 'Invoice_ID' => 'INV-3', 'Amount_Paid' => 25, 'Status' => 'Verified'],
        ]);

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldNotReceive('fetchAll');
        $companyRepo = Mockery::mock(CompanyRepositoryInterface::class);
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->zeroOrMoreTimes()->andReturnTrue();

        $service = new InvoiceService($invoiceRepo, $events, $studentRepo, $companyRepo, $paymentRepo);
        $invoices = $service->getAll();

        $this->assertCount(3, $invoices);
        $this->assertSame('Partial Paid', $invoices[0]['Display_Status']);
        $this->assertSame(40.0, (float) $invoices[0]['Paid_Amount']);
        $this->assertSame(60.0, (float) $invoices[0]['Remaining_Amount']);
        $this->assertSame('Paid', $invoices[1]['Display_Status']);
        $this->assertSame(200.0, (float) $invoices[1]['Paid_Amount']);
        $this->assertSame(0.0, (float) $invoices[1]['Remaining_Amount']);
        $this->assertSame('Partial Paid', $invoices[2]['Display_Status']);
        $this->assertSame(25.0, (float) $invoices[2]['Paid_Amount']);
        $this->assertSame(275.0, (float) $invoices[2]['Remaining_Amount']);
        $this->assertSame(1, $paymentRepo->freshReads);
    }

    public function test_dashboard_excludes_draft_cancelled_and_future_cash(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getAll')->once()->andReturn(collect([
            ['Invoice_ID' => 'DRAFT', 'Status' => 'Draft', 'Amount' => 100],
            ['Invoice_ID' => 'CANCELLED', 'Status' => 'Cancelled', 'Amount' => 100],
            ['Invoice_ID' => 'PAID', 'Status' => 'Paid', 'Amount' => 100],
            ['Invoice_ID' => 'OPEN', 'Status' => 'Waiting Payment', 'Amount' => 100, 'Due_Date' => now()->addDay()->toDateString()],
        ]));
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldReceive('getAll')->once()->andReturn(collect());
        $transactionService = Mockery::mock(TransactionService::class);
        $transactionService->shouldReceive('getAll')->once()->andReturn(collect([
            ['Transaction_ID' => 'NOW', 'Transaction_Date' => now()->toDateString(), 'Type' => 'Income', 'Amount' => 100, 'Category' => 'Current'],
            ['Transaction_ID' => 'FUTURE', 'Transaction_Date' => now()->addDay()->toDateString(), 'Type' => 'Income', 'Amount' => 900, 'Category' => 'Scheduled'],
        ]));
        $activities = Mockery::mock(ActivityLogService::class);
        $activities->shouldReceive('getAllLogs')->once()->andReturn(collect());
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('UnreadCount')->zeroOrMoreTimes()->andReturn(0);
        $service = new FinanceDashboardService(
            $invoiceService, $paymentService, $transactionService,
            Mockery::mock(FinanceReportService::class), $activities, $notifications
        );
        $dashboard = $service->getDashboardData();
        $this->assertSame(50.0, (float) $dashboard['kpi']['collection_rate']);
        $this->assertSame(100.0, (float) $dashboard['kpi']['cash_balance']);
        $this->assertSame(100.0, (float) $dashboard['kpi']['revenue_this_month']);
    }

    public function test_ambiguous_update_is_verified_without_blind_duplicate_retry(): void
    {
        $resource = new IntegrityUpdateResource();
        $repository = new IntegrityUpdateRepository($resource);
        $this->assertTrue($repository->updateRow('R-1', ['Status' => 'updated']));
        $this->assertSame(1, $resource->updateCalls);
    }

    public function test_payment_reversal_cannot_be_created_through_generic_transaction_path(): void
    {
        $payments = new IntegrityPaymentRepository([
            ['Payment_ID' => 'PAY-R', 'Amount_Paid' => 100, 'Status' => 'Verified', 'Is_Active' => 'TRUE'],
        ]);
        $service = new TransactionService(
            new IntegrityTransactionRepository(), new IntegrityAccountRepository(),
            Mockery::mock(EnterpriseEventService::class), new IntegrityInvoiceRepository(), $payments
        );
        $this->expectException(FinancialIntegrityException::class);
        $service->create([
            'Transaction_Date' => '2026-08-31', 'Account_ID' => 'ACC-1', 'Type' => 'Expense',
            'Category' => 'Payment Reversal', 'Amount' => 100,
            'Reference_Type' => 'PaymentReversal', 'Reference_ID' => 'PAY-R',
        ]);
    }

    public function test_verified_payment_ledger_repair_is_idempotent_when_income_already_exists(): void
    {
        $payments = new IntegrityPaymentRepository([
            ['Payment_ID' => 'PAY-IDEM', 'Invoice_ID' => 'INV-IDEM', 'Amount_Paid' => 100,
                'Payment_Method' => 'CASH', 'Payment_Date' => '2026-08-31', 'Status' => 'Verified', 'Is_Active' => 'TRUE'],
        ]);
        $invoices = new IntegrityInvoiceRepository([
            ['Invoice_ID' => 'INV-IDEM', 'Amount' => 100, 'Status' => 'Paid', 'Is_Active' => 'TRUE'],
        ]);
        $transactions = new IntegrityTransactionRepository();
        $transactions->rows[] = ['Transaction_ID' => 'TRX-IDEM', 'Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-IDEM', 'Type' => 'Income', 'Amount' => 100, 'Account_ID' => '101', 'Is_Active' => 'TRUE'];
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class));
        $result = $service->reconcileVerifiedPaymentLedger('PAY-IDEM');
        $this->assertSame('Verified', $result['Status']);
        $this->assertCount(1, $transactions->rows);
    }

    public function test_verified_status_variants_are_normalized_and_unknown_status_fails_closed(): void
    {
        $payments = new IntegrityPaymentRepository([
            ['Payment_ID' => 'P1', 'Invoice_ID' => 'INV-S', 'Amount_Paid' => 100, 'Status' => 'Verified'],
            ['Payment_ID' => 'P2', 'Invoice_ID' => 'INV-S', 'Amount_Paid' => 100, 'Status' => 'verified'],
            ['Payment_ID' => 'P3', 'Invoice_ID' => 'INV-S', 'Amount_Paid' => 100, 'Status' => ' VERIFIED '],
            ['Payment_ID' => 'P4', 'Invoice_ID' => 'INV-S', 'Amount_Paid' => 100, 'Status' => 'VERIFIED'],
        ]);
        $service = new InvoiceService(new IntegrityInvoiceRepository(), Mockery::mock(EnterpriseEventService::class), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), $payments);
        $this->assertSame(400.0, $service->getVerifiedPaymentTotal('INV-S'));

        $payments->rows[] = ['Payment_ID' => 'P5', 'Invoice_ID' => 'INV-S', 'Amount_Paid' => 1, 'Status' => 'UNKNOWN'];
        $this->expectException(FinancialIntegrityException::class);
        $service->getVerifiedPaymentTotal('INV-S');
    }

    public function test_verified_payment_missing_ledger_can_retry_after_creation_failure(): void
    {
        $payments = new IntegrityPaymentRepository([[
            'Payment_ID' => 'PAY-REC', 'Invoice_ID' => 'INV-REC', 'Amount_Paid' => 100,
            'Payment_Method' => 'CASH', 'Payment_Date' => '2026-08-31', 'Status' => 'Verified', 'Is_Active' => 'TRUE',
        ]]);
        $invoices = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-REC', 'Amount' => 100, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE']]);
        $transactions = new IntegrityTransactionRepository();
        $failure = Mockery::mock(TransactionService::class);
        $failure->shouldReceive('create')->once()->andThrow(new \RuntimeException('ledger unavailable'));
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $failure);
        try {
            $service->reconcileVerifiedPaymentLedger('PAY-REC');
            $this->fail('Ledger failure must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('ledger unavailable', $e->getMessage());
        }
        $this->assertCount(0, $transactions->rows);

        $repair = Mockery::mock(TransactionService::class);
        $repair->shouldReceive('create')->once()->andReturnUsing(function ($data) use ($transactions) {
            $transactions->rows[] = $data + ['Is_Active' => 'TRUE'];
            return $data;
        });
        $retry = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $repair);
        $this->assertSame('Verified', $retry->reconcileVerifiedPaymentLedger('PAY-REC')['Status']);
        $this->assertCount(1, $transactions->rows);
    }

    public function test_ambiguous_ledger_append_retry_detects_persisted_exact_transaction(): void
    {
        $payments = new IntegrityPaymentRepository([['Payment_ID' => 'PAY-AMB', 'Invoice_ID' => 'INV-AMB', 'Amount_Paid' => 100, 'Payment_Method' => 'CASH', 'Payment_Date' => '2026-08-31', 'Status' => 'Verified', 'Is_Active' => 'TRUE']]);
        $invoices = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-AMB', 'Amount' => 100, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE']]);
        $transactions = new IntegrityTransactionRepository();
        $ambiguous = Mockery::mock(TransactionService::class);
        $ambiguous->shouldReceive('create')->once()->andReturnUsing(function ($data) use ($transactions) {
            $transactions->rows[] = $data + ['Is_Active' => 'TRUE'];
            throw new AmbiguousSheetWriteException('ambiguous append');
        });
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $ambiguous);
        try { $service->reconcileVerifiedPaymentLedger('PAY-AMB'); } catch (AmbiguousSheetWriteException) {}

        $noCreate = Mockery::mock(TransactionService::class);
        $noCreate->shouldNotReceive('create');
        $retry = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $noCreate);
        $retry->reconcileVerifiedPaymentLedger('PAY-AMB');
        $this->assertCount(1, $transactions->rows);
    }

    public function test_invoice_reconciliation_failure_retries_without_duplicate_ledger(): void
    {
        $payments = new IntegrityPaymentRepository([['Payment_ID' => 'PAY-INVFAIL', 'Invoice_ID' => 'INV-FAIL', 'Amount_Paid' => 100, 'Payment_Method' => 'CASH', 'Payment_Date' => '2026-08-31', 'Status' => 'Verified', 'Is_Active' => 'TRUE']]);
        $invoices = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-FAIL', 'Amount' => 100, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE']]);
        $invoices->failUpdates = true;
        $transactions = new IntegrityTransactionRepository();
        $creator = Mockery::mock(TransactionService::class);
        $creator->shouldReceive('create')->once()->andReturnUsing(function ($data) use ($transactions) {
            $transactions->rows[] = $data + ['Is_Active' => 'TRUE'];
            return $data;
        });
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $creator);
        try { $service->reconcileVerifiedPaymentLedger('PAY-INVFAIL'); } catch (FinancialIntegrityException) {}
        $this->assertCount(1, $transactions->rows);

        $invoices->failUpdates = false;
        $noDuplicate = Mockery::mock(TransactionService::class);
        $noDuplicate->shouldNotReceive('create');
        $retry = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $noDuplicate);
        $retry->reconcileVerifiedPaymentLedger('PAY-INVFAIL');
        $this->assertCount(1, $transactions->rows);
        $this->assertSame('Paid', $invoices->getById('INV-FAIL')['Status']);
    }

    public function test_existing_payment_ledger_mismatches_fail_closed(): void
    {
        $basePayment = ['Payment_ID' => 'PAY-MM', 'Invoice_ID' => 'INV-MM', 'Amount_Paid' => 100, 'Payment_Method' => 'CASH', 'Payment_Date' => '2026-08-31', 'Status' => 'Verified', 'Is_Active' => 'TRUE'];
        $cases = [
            ['Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-MM', 'Type' => 'Income', 'Amount' => 99, 'Account_ID' => '101'],
            ['Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-MM', 'Type' => 'Expense', 'Amount' => 100, 'Account_ID' => '101'],
            ['Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-MM', 'Type' => 'Income', 'Amount' => 100, 'Account_ID' => 'WRONG'],
            ['Transaction_ID' => 'TRX-PAY-' . strtoupper(substr(hash('sha256', 'PAY-MM'), 0, 20)), 'Reference_Type' => 'Payment', 'Reference_ID' => 'OTHER', 'Type' => 'Income', 'Amount' => 100, 'Account_ID' => '101'],
            ['Reference_Type' => 'Invoice', 'Reference_ID' => 'PAY-MM', 'Type' => 'Income', 'Amount' => 100, 'Account_ID' => '101'],
        ];
        foreach ($cases as $case) {
            $transactions = new IntegrityTransactionRepository();
            $transactions->rows[] = $case + ['Transaction_ID' => 'TRX-X', 'Is_Active' => 'TRUE'];
            $service = new PaymentService(new IntegrityPaymentRepository([$basePayment]), new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-MM', 'Amount' => 100, 'Status' => 'Paid', 'Is_Active' => 'TRUE']]), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), Mockery::mock(TransactionService::class));
            try {
                $service->reconcileVerifiedPaymentLedger('PAY-MM');
                $this->fail('Mismatched ledger must fail closed.');
            } catch (FinancialIntegrityException) {
                $this->assertTrue(true);
            }
        }
    }

    /** @dataProvider unauthorizedFinanceRoles */
    public function test_finance_mutation_services_reject_student_director_and_roleless_actors(string $role): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-BAD-' . ($role ?: 'NONE'), 'User_ID' => 'USR-BAD', 'Role' => $role]));
        $payments = new IntegrityPaymentRepository([['Payment_ID' => 'PAY-AUTH', 'Status' => 'Verified', 'Invoice_ID' => 'INV-AUTH']]);
        $service = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $service->deletePayment('PAY-AUTH');
    }

    public static function unauthorizedFinanceRoles(): array
    {
        return [['STUDENT'], ['DIRECTOR'], ['']];
    }

    public function test_account_resolution_requires_explicit_or_unique_active_asset_identity(): void
    {
        config(['finance.accounts.cash_id' => 'ACC-2']);
        $configuredRepo = Mockery::mock(AccountRepositoryInterface::class);
        $configuredRepo->shouldReceive('fetchAll')->andReturn(collect([
            ['Account_ID' => 'ACC-2', 'Account_Code' => 'CASH-01', 'Account_Name' => 'Kas Cabang', 'Account_Category' => 'ASSET', 'Is_Active' => 'TRUE'],
        ]));
        $service = new PaymentService(new IntegrityPaymentRepository(), new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), $configuredRepo, new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $this->assertSame('CASH-01', $service->resolvePaymentAccount('CASH'));

        config(['finance.accounts.cash_id' => 'MISSING']);
        $this->expectException(FinancialIntegrityException::class);
        $service->resolvePaymentAccount('CASH');
    }

    public function test_account_resolution_fails_closed_for_ambiguous_or_wrong_category_accounts(): void
    {
        config(['finance.accounts.cash_id' => null]);
        $repo = Mockery::mock(AccountRepositoryInterface::class);
        $repo->shouldReceive('fetchAll')->andReturn(collect([
            ['Account_ID' => 'A1', 'Account_Code' => 'C1', 'Account_Name' => 'Kas Satu', 'Account_Category' => 'ASSET', 'Is_Active' => 'TRUE'],
            ['Account_ID' => 'A2', 'Account_Code' => 'C2', 'Account_Name' => 'Kas Dua', 'Account_Category' => 'ASSET', 'Is_Active' => 'TRUE'],
            ['Account_ID' => 'A3', 'Account_Code' => 'C3', 'Account_Name' => 'Kas Liability', 'Account_Category' => 'LIABILITY', 'Is_Active' => 'TRUE'],
        ]));
        $service = new PaymentService(new IntegrityPaymentRepository(), new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), $repo, new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $this->expectException(FinancialIntegrityException::class);
        $service->resolvePaymentAccount('CASH');
    }

    public function test_transaction_allocator_uses_persisted_max_over_stale_counter(): void
    {
        $repo = new class extends ConcreteTransactionRepository {
            public array $rows = [];
            public function fetchAllFresh() { return collect($this->rows); }
        };
        $reflection = new \ReflectionObject($repo);
        foreach (['sheetName' => 'FINANCE_TRANSACTION', 'primaryKey' => 'Transaction_ID'] as $property => $value) {
            $p = $reflection->getParentClass()->getProperty($property);
            $p->setAccessible(true);
            $p->setValue($repo, $value);
        }
        $repo->rows = [['Transaction_ID' => 'TRX-000009']];
        Cache::forever('id_counter_FINANCE_TRANSACTION_TRX', 2);
        $this->assertSame('TRX-000010', $repo->generateNewId());
    }

    public function test_money_forensic_matrix_and_partial_payment_boundaries(): void
    {
        $this->assertSame(Money::cents(10000000), Money::cents(3000000) + Money::cents(4000000) + Money::cents(3000000));
        $this->assertGreaterThan(Money::cents(10000000), Money::cents(3000000) + Money::cents(4000000) + Money::cents(4000000));
        $this->assertTrue(Money::equal('100.00', 100));
        $this->assertSame(100.01, Money::value('100.005'));
        $this->assertSame(100.00, Money::value('100.004'));
        $this->assertSame(100.01, Money::value('100.006'));
        $this->assertSame(0.0, Money::value(0));
        $this->assertSame(1000000000000.0, Money::value('1000000000000'));
        foreach ([null, '', 'abc', -1, INF, -INF, NAN] as $invalid) {
            try {
                Money::value($invalid);
                $this->fail('Invalid money value must be rejected.');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_invoice_cancellation_rejects_verified_partial_and_paid_states(): void
    {
        $payments = new IntegrityPaymentRepository([['Payment_ID' => 'P-INV', 'Invoice_ID' => 'INV-P', 'Amount_Paid' => 1, 'Status' => 'Verified']]);
        $invoiceRepo = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-P', 'Status' => 'Partial Paid', 'Amount' => 10, 'Is_Active' => 'TRUE']]);
        $service = new InvoiceService($invoiceRepo, Mockery::mock(EnterpriseEventService::class), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), $payments);
        $this->expectException(FinancialIntegrityException::class);
        $service->cancel('INV-P');
    }

    public function test_draft_invoice_can_be_cancelled_without_verified_payment(): void
    {
        $invoiceRepo = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-D', 'Status' => 'Draft', 'Amount' => 10, 'Is_Active' => 'TRUE']]);
        $service = new InvoiceService($invoiceRepo, Mockery::mock(EnterpriseEventService::class), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityPaymentRepository());
        $result = $service->cancel('INV-D');
        $this->assertSame('Cancelled', $result['Status']);
    }

    public function test_reversed_payment_with_missing_reversal_is_repaired_idempotently(): void
    {
        $payments = new IntegrityPaymentRepository([['Payment_ID' => 'PAY-RCV', 'Invoice_ID' => 'INV-RCV', 'Amount_Paid' => 100, 'Status' => 'Reversed', 'Is_Active' => 'TRUE']]);
        $invoices = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-RCV', 'Amount' => 100, 'Status' => 'Paid', 'Is_Active' => 'TRUE']]);
        $transactions = new IntegrityTransactionRepository();
        $transactions->rows[] = ['Transaction_ID' => 'TRX-ORIG', 'Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-RCV', 'Type' => 'Income', 'Amount' => 100, 'Account_ID' => 'ACC-1', 'Is_Active' => 'TRUE'];
        $creator = Mockery::mock(TransactionService::class);
        $creator->shouldReceive('create')->once()->andReturnUsing(function ($data) use ($transactions) { $transactions->rows[] = $data + ['Is_Active' => 'TRUE']; return $data; });
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $creator);
        $service->reconcilePaymentReversal('PAY-RCV', 'repair');
        $service->reconcilePaymentReversal('PAY-RCV', 'repair');
        $this->assertCount(2, $transactions->rows);
        $this->assertSame('TRX-REV-' . strtoupper(substr(hash('sha256', 'PAY-RCV'), 0, 20)), $transactions->rows[1]['Transaction_ID']);
    }

    public function test_reversal_mismatch_and_missing_original_fail_closed(): void
    {
        $payments = new IntegrityPaymentRepository([['Payment_ID' => 'PAY-RMM', 'Invoice_ID' => 'INV-RMM', 'Amount_Paid' => 100, 'Status' => 'Reversed', 'Is_Active' => 'TRUE']]);
        $invoices = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-RMM', 'Amount' => 100, 'Status' => 'Paid', 'Is_Active' => 'TRUE']]);
        $transactions = new IntegrityTransactionRepository();
        $transactions->rows[] = ['Transaction_ID' => 'TRX-ORIG', 'Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-RMM', 'Type' => 'Income', 'Amount' => 100, 'Account_ID' => 'ACC-1', 'Is_Active' => 'TRUE'];
        $transactions->rows[] = ['Transaction_ID' => 'TRX-REV-' . strtoupper(substr(hash('sha256', 'PAY-RMM'), 0, 20)), 'Reference_Type' => 'PaymentReversal', 'Reference_ID' => 'PAY-RMM', 'Type' => 'Expense', 'Amount' => 99, 'Account_ID' => 'ACC-1', 'Is_Active' => 'TRUE'];
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), Mockery::mock(TransactionService::class));
        $this->expectException(FinancialIntegrityException::class);
        $service->reconcilePaymentReversal('PAY-RMM', 'repair');
    }

    public function test_reversal_ambiguous_append_and_invoice_failure_are_retry_safe(): void
    {
        $payments = new IntegrityPaymentRepository([['Payment_ID' => 'PAY-RTRY', 'Invoice_ID' => 'INV-RTRY', 'Amount_Paid' => 100, 'Status' => 'Verified', 'Is_Active' => 'TRUE']]);
        $invoices = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-RTRY', 'Amount' => 100, 'Status' => 'Paid', 'Is_Active' => 'TRUE']]);
        $transactions = new IntegrityTransactionRepository();
        $transactions->rows[] = ['Transaction_ID' => 'TRX-ORIG', 'Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-RTRY', 'Type' => 'Income', 'Amount' => 100, 'Account_ID' => 'ACC-1', 'Is_Active' => 'TRUE'];
        $ambiguous = Mockery::mock(TransactionService::class);
        $ambiguous->shouldReceive('create')->once()->andReturnUsing(function ($data) use ($transactions) { $transactions->rows[] = $data + ['Is_Active' => 'TRUE']; throw new AmbiguousSheetWriteException('ambiguous'); });
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $ambiguous);
        $service->reconcilePaymentReversal('PAY-RTRY', 'repair');
        $this->assertCount(2, $transactions->rows);
        $this->assertSame('Reversed', $payments->getById('PAY-RTRY')['Status']);
    }

    public function test_verified_payment_recovery_fails_closed_for_missing_invoice_or_account(): void
    {
        $payment = ['Payment_ID' => 'PAY-MISSING', 'Invoice_ID' => 'INV-MISSING', 'Amount_Paid' => 100, 'Payment_Method' => 'CASH', 'Status' => 'Verified', 'Is_Active' => 'TRUE'];
        $service = new PaymentService(new IntegrityPaymentRepository([$payment]), new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class), Mockery::mock(TransactionService::class));
        $this->expectException(FinancialIntegrityException::class);
        $service->reconcileVerifiedPaymentLedger('PAY-MISSING');
    }

    public function test_verified_payment_recovery_rejects_invalid_account_identity(): void
    {
        $payment = ['Payment_ID' => 'PAY-BAD-ACC', 'Invoice_ID' => 'INV-BAD-ACC', 'Amount_Paid' => 100, 'Payment_Method' => 'CASH', 'Status' => 'Verified', 'Is_Active' => 'TRUE'];
        $accounts = new class implements AccountRepositoryInterface {
            public function fetchAll(){ return collect(); }
            public function findById(string $id){ return null; } public function create(array $data){ return true; } public function update(string $id,array $data){ return true; } public function delete(string $id){ return true; } public function generateNewId(string $prefix='ACC',int $padding=6): string{return 'ACC-1';}
        };
        $service = new PaymentService(new IntegrityPaymentRepository([$payment]), new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-BAD-ACC', 'Amount' => 100, 'Is_Active' => 'TRUE']]), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), $accounts, new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class), Mockery::mock(TransactionService::class));
        $this->expectException(FinancialIntegrityException::class);
        $service->reconcileVerifiedPaymentLedger('PAY-BAD-ACC');
    }

    public function test_reversal_invoice_failure_retries_without_duplicate_reversal(): void
    {
        $payments = new IntegrityPaymentRepository([['Payment_ID' => 'PAY-RINV', 'Invoice_ID' => 'INV-RINV', 'Amount_Paid' => 100, 'Status' => 'Verified', 'Is_Active' => 'TRUE']]);
        $invoices = new IntegrityInvoiceRepository([['Invoice_ID' => 'INV-RINV', 'Amount' => 100, 'Status' => 'Paid', 'Is_Active' => 'TRUE']]);
        $invoices->failUpdates = true;
        $transactions = new IntegrityTransactionRepository();
        $transactions->rows[] = ['Transaction_ID' => 'TRX-ORIG', 'Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-RINV', 'Type' => 'Income', 'Amount' => 100, 'Account_ID' => 'ACC-1', 'Is_Active' => 'TRUE'];
        $creator = Mockery::mock(TransactionService::class);
        $creator->shouldReceive('create')->once()->andReturnUsing(function ($data) use ($transactions) { $transactions->rows[] = $data + ['Is_Active' => 'TRUE']; return $data; });
        $service = new PaymentService($payments, $invoices, new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $creator);
        try { $service->reversePayment('PAY-RINV', 'invoice retry'); } catch (FinancialIntegrityException) { $this->assertTrue(true); }
        $this->assertCount(2, $transactions->rows);
        $invoices->failUpdates = false;
        $service->reconcilePaymentReversal('PAY-RINV', 'invoice retry');
        $this->assertCount(2, $transactions->rows);
    }

    public function test_report_date_boundaries_and_malformed_rows_are_explicitly_accounted(): void
    {
        $txRepo = Mockery::mock(TransactionRepositoryInterface::class);
        $txRepo->shouldReceive('fetchAll')->twice()->andReturn(collect([
            ['Transaction_ID' => 'TX-JAN-END', 'Transaction_Date' => '2026-01-31', 'Type' => 'Income', 'Amount' => 10, 'Is_Active' => 'TRUE'],
            ['Transaction_ID' => 'TX-FEB-START', 'Transaction_Date' => '2026-02-01', 'Type' => 'Income', 'Amount' => 20, 'Is_Active' => 'TRUE'],
            ['Transaction_ID' => 'TX-YEAR-END', 'Transaction_Date' => '2025-12-31', 'Type' => 'Income', 'Amount' => 7, 'Is_Active' => 'TRUE'],
            ['Transaction_ID' => 'TX-YEAR-START', 'Transaction_Date' => '2026-01-01', 'Type' => 'Income', 'Amount' => 5, 'Is_Active' => 'TRUE'],
            ['Transaction_ID' => 'TX-BAD-DATE', 'Transaction_Date' => '2026-99-99', 'Type' => 'Income', 'Amount' => 100, 'Is_Active' => 'TRUE'],
            ['Transaction_ID' => 'TX-FUTURE', 'Transaction_Date' => '2099-01-01', 'Type' => 'Income', 'Amount' => 1000, 'Is_Active' => 'TRUE'],
        ]));
        $accountRepo = Mockery::mock(AccountRepositoryInterface::class);
        $accountRepo->shouldReceive('fetchAll')->twice()->andReturn(collect([['Account_ID' => 'ACC-1', 'Account_Code' => '101', 'Is_Active' => 'TRUE']]));
        $service = new FinanceReportService($txRepo, Mockery::mock(InvoiceRepositoryInterface::class), Mockery::mock(PaymentRepositoryInterface::class), Mockery::mock(StudentRepositoryInterface::class), Mockery::mock(CompanyRepositoryInterface::class), $accountRepo);
        $period = $service->getCashFlow('2026-01-31', '2026-02-01');
        $this->assertSame(30.0, $period['total_income']);
        $this->assertCount(2, $period['transactions']);
        $this->assertGreaterThanOrEqual(1, $period['skipped_transaction_count']);

        $past = $service->getCashFlow('2025-12-31', '2025-12-31');
        $this->assertSame(7.0, $past['total_income']);
        $this->assertSame('repository_cache', $past['snapshot_source']);
    }

    public function test_student_self_service_submission_is_waiting_verification_and_server_owned(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        $payments = new IntegrityPaymentRepository();
        $service = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $result = $service->submitPayment([
            'Self_Service' => true, 'Amount_Paid' => 250,
            'Payment_Method' => 'TRANSFER', 'Sender_Name' => 'Student A', 'Transfer_Date' => '2026-09-01',
            'Idempotency_Key' => '44444444-4444-4444-8444-444444444444',
        ]);
        $this->assertSame('Waiting Verification', $result['Status']);
        $this->assertSame('STU-1', $result['Student_ID']);
        $this->assertSame('', $result['Invoice_ID']);
        $this->assertSame('STUDENT_SELF_SERVICE', $result['Payment_Type']);
        $this->assertArrayNotHasKey('Reference_ID', $result);
    }

    public function test_student_self_service_rejects_identity_tampering(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        $service = new PaymentService(new IntegrityPaymentRepository(), new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $this->expectException(FinancialIntegrityException::class);
        $service->submitPayment([
            'Self_Service' => true, 'Student_ID' => 'STU-OTHER', 'Invoice_ID' => 'INV-OTHER', 'Amount_Paid' => 250,
            'Payment_Method' => 'TRANSFER', 'Sender_Name' => 'Student A', 'Transfer_Date' => '2026-09-01',
            'Idempotency_Key' => '77777777-7777-4777-8777-777777777777',
        ]);
    }

    public function test_student_self_service_duplicate_submission_is_idempotent(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        $payments = new IntegrityPaymentRepository();
        $service = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $payload = ['Self_Service' => true, 'Amount_Paid' => 250, 'Payment_Method' => 'TRANSFER', 'Sender_Name' => 'Student A', 'Transfer_Date' => '2026-09-01', 'Idempotency_Key' => '55555555-5555-4555-8555-555555555555'];
        $first = $service->submitPayment($payload);
        $second = $service->submitPayment($payload);
        $this->assertSame($first['Payment_ID'], $second['Payment_ID']);
        $this->assertCount(1, $payments->rows);
    }

    public function test_self_service_cannot_be_invoked_by_finance_or_client_identity_fields(): void
    {
        $service = new PaymentService(new IntegrityPaymentRepository(), new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), new IntegrityTransactionRepository(), Mockery::mock(EnterpriseEventService::class));
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $service->submitPayment(['Self_Service' => true, 'Amount_Paid' => 10, 'Payment_Method' => 'CASH', 'Idempotency_Key' => '66666666-6666-4666-8666-666666666666']);
    }

    public function test_finance_verification_of_self_service_creates_ledger_only_after_verification(): void
    {
        $payments = new IntegrityPaymentRepository([['Payment_ID' => 'PAY-SELF', 'Invoice_ID' => '', 'Student_ID' => 'STU-1', 'Amount_Paid' => 250, 'Payment_Method' => 'CASH', 'Payment_Date' => '2026-09-01', 'Payment_Type' => 'STUDENT_SELF_SERVICE', 'Status' => 'Waiting Verification', 'Is_Active' => 'TRUE']]);
        $transactions = new IntegrityTransactionRepository();
        $txService = Mockery::mock(TransactionService::class);
        $txService->shouldReceive('create')->once()->andReturnUsing(function ($data) use ($transactions) { $transactions->rows[] = $data; return $data; });
        $this->app->instance(InvoiceService::class, Mockery::mock(InvoiceService::class));
        $service = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), new IntegrityAccountRepository(), $transactions, Mockery::mock(EnterpriseEventService::class), $txService);
        $this->assertCount(0, $transactions->rows);
        $service->verifyPayment('PAY-SELF', 'spoofed', 'Verified');
        $this->assertSame('Verified', $payments->getById('PAY-SELF')['Status']);
        $this->assertCount(1, $transactions->rows);
        $this->assertSame('Payment', $transactions->rows[0]['Reference_Type']);
        $this->assertSame('TRX-PAY-' . strtoupper(substr(hash('sha256', 'PAY-SELF'), 0, 20)), $transactions->rows[0]['Transaction_ID']);
    }

    public function test_verification_rolls_back_status_when_ledger_creation_fails(): void
    {
        $payments = new IntegrityPaymentRepository([[
            'Payment_ID' => 'PAY-GAP', 'Invoice_ID' => '', 'Student_ID' => 'STU-1',
            'Amount_Paid' => 250, 'Payment_Method' => 'CASH', 'Payment_Date' => '2026-09-01',
            'Payment_Type' => 'STUDENT_SELF_SERVICE', 'Status' => 'Waiting Verification', 'Is_Active' => 'TRUE',
        ]]);
        $transactions = new IntegrityTransactionRepository();
        $txService = Mockery::mock(TransactionService::class);
        $txService->shouldReceive('create')->once()->andThrow(new \RuntimeException('ledger unavailable'));
        $this->app->instance(InvoiceService::class, Mockery::mock(InvoiceService::class));

        $service = new PaymentService(
            $payments,
            new IntegrityInvoiceRepository(),
            new IntegrityStudentRepository(),
            new IntegrityCompanyRepository(),
            new IntegrityAccountRepository(),
            $transactions,
            Mockery::mock(EnterpriseEventService::class),
            $txService
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ledger unavailable');
        try {
            $service->verifyPayment('PAY-GAP', 'spoofed', 'Verified');
        } finally {
            $this->assertSame('Waiting Verification', $payments->getById('PAY-GAP')['Status']);
            $this->assertCount(0, $transactions->rows);
        }
    }

    public function test_cash_and_transfer_verified_payments_use_the_same_ledger_engine(): void
    {
        config(['finance.accounts.cash_id' => null, 'finance.accounts.bank_id' => null]);
        $payments = new IntegrityPaymentRepository([
            ['Payment_ID' => 'PAY-CASH', 'Invoice_ID' => '', 'Student_ID' => 'STU-1', 'Amount_Paid' => 100, 'Payment_Method' => 'CASH', 'Payment_Date' => '2026-09-01', 'Payment_Type' => 'STUDENT_SELF_SERVICE', 'Status' => 'Waiting Verification', 'Is_Active' => 'TRUE'],
            ['Payment_ID' => 'PAY-TRANSFER', 'Invoice_ID' => '', 'Student_ID' => 'STU-1', 'Amount_Paid' => 200, 'Payment_Method' => 'TRANSFER', 'Payment_Date' => '2026-09-01', 'Payment_Type' => 'STUDENT_SELF_SERVICE', 'Status' => 'Waiting Verification', 'Is_Active' => 'TRUE'],
        ]);
        $accounts = Mockery::mock(AccountRepositoryInterface::class);
        $accounts->shouldReceive('fetchAll')->andReturn(collect([
            ['Account_ID' => 'ACC-CASH', 'Account_Code' => '101', 'Account_Name' => 'Kas Utama', 'Account_Category' => 'ASSET', 'Is_Active' => 'TRUE'],
            ['Account_ID' => 'ACC-BANK', 'Account_Code' => '102', 'Account_Name' => 'Bank Utama', 'Account_Category' => 'ASSET', 'Is_Active' => 'TRUE'],
        ]));
        $transactions = new IntegrityTransactionRepository();
        $txService = Mockery::mock(TransactionService::class);
        $txService->shouldReceive('create')->twice()->andReturnUsing(function ($data) use ($transactions) { $transactions->rows[] = $data; return $data; });
        $this->app->instance(InvoiceService::class, Mockery::mock(InvoiceService::class));
        $service = new PaymentService($payments, new IntegrityInvoiceRepository(), new IntegrityStudentRepository(), new IntegrityCompanyRepository(), $accounts, $transactions, Mockery::mock(EnterpriseEventService::class), $txService);

        $service->verifyPayment('PAY-CASH', 'spoofed', 'Verified');
        $service->verifyPayment('PAY-TRANSFER', 'spoofed', 'Verified');

        $this->assertCount(2, $transactions->rows);
        $this->assertSame(['101', '102'], collect($transactions->rows)->pluck('Account_ID')->all());
        $this->assertSame(['Payment', 'Payment'], collect($transactions->rows)->pluck('Reference_Type')->all());
    }
}

class IntegrityPaymentRepository implements PaymentRepositoryInterface
{
    public array $rows;
    public function __construct(array $rows = []) { $this->rows = $rows; }
    public function getAll() { return collect($this->rows); }
    public function getAllFresh() { return collect($this->rows); }
    public function getById($id) { return collect($this->rows)->firstWhere('Payment_ID', $id); }
    public function getByIdFresh($id) { return $this->getById($id); }
    public function create(array $data) {
        foreach ($this->rows as $row) {
            if (strcasecmp((string) ($row['Payment_ID'] ?? ''), (string) ($data['Payment_ID'] ?? '')) === 0) {
                throw new DuplicatePrimaryKeyException('duplicate payment id');
            }
        }
        $this->rows[] = $data;
        return $data;
    }
    public function update($id, array $data) { foreach ($this->rows as &$row) if (($row['Payment_ID'] ?? '') === $id) $row = array_merge($row, $data); return true; }
    public function delete($id) { return false; }
    public function clearCache() {}
}

class CountingFreshPaymentRepository implements PaymentRepositoryInterface
{
    public int $freshReads = 0;

    public function __construct(public array $rows = [])
    {
    }

    public function getAll()
    {
        return collect($this->rows);
    }

    public function getAllFresh()
    {
        $this->freshReads++;
        return collect($this->rows);
    }

    public function getById($id)
    {
        return collect($this->rows)->firstWhere('Payment_ID', $id);
    }

    public function create(array $data)
    {
        $this->rows[] = $data;
        return $data;
    }

    public function update($id, array $data)
    {
        foreach ($this->rows as &$row) {
            if (($row['Payment_ID'] ?? '') === $id) {
                $row = array_merge($row, $data);
            }
        }
        return true;
    }

    public function delete($id)
    {
        return false;
    }
}

class IntegrityInvoiceRepository implements InvoiceRepositoryInterface
{
    public array $rows;
    public bool $failUpdates = false;
    public function __construct(array $rows = []) { $this->rows = $rows; }
    public function getAll() { return collect($this->rows); }
    public function getAllFresh() { return collect($this->rows); }
    public function getById($id) { return collect($this->rows)->firstWhere('Invoice_ID', $id); }
    public function findByIdFresh($id) { return $this->getById($id); }
    public function create(array $data) { $this->rows[] = $data; return $data; }
    public function update($id, array $data) { if ($this->failUpdates) return false; foreach ($this->rows as &$row) if (($row['Invoice_ID'] ?? '') === $id) $row = array_merge($row, $data); return true; }
    public function delete($id) { return false; }
    public function clearCache() {}
}

class IntegrityTransactionRepository implements TransactionRepositoryInterface
{
    public array $rows = [];
    public function fetchAll() { return collect($this->rows); }
    public function findById(string $id) { return collect($this->rows)->firstWhere('Transaction_ID', $id); }
    public function create(array $data) { $this->rows[] = $data; return $data; }
    public function update(string $id, array $data) { return true; }
    public function delete(string $id) { return true; }
    public function generateNewId(string $prefix = 'TRX', int $padding = 6): string { return $prefix . '-000001'; }
}

class IntegrityAccountRepository implements AccountRepositoryInterface
{
    public function fetchAll() { return collect([['Account_ID' => 'ACC-1', 'Account_Code' => '101', 'Account_Name' => 'Kas Utama', 'Account_Category' => 'ASSET', 'Is_Active' => 'TRUE']]); }
    public function findById(string $id) { return $id === 'ACC-1' ? $this->fetchAll()->first() : null; }
    public function create(array $data) { return true; }
    public function update(string $id, array $data) { return true; }
    public function delete(string $id) { return true; }
    public function generateNewId(string $prefix = 'ACC', int $padding = 6): string { return $prefix . '-000001'; }
}

class IntegrityStudentRepository implements StudentRepositoryInterface
{
    public function fetchAll() { return collect([['Student_ID' => 'STU-1', 'User_ID' => 'USR-STU', 'Is_Active' => 'TRUE']]); }
    public function findById(string $id) { return $id === 'STU-1' ? ['Student_ID' => 'STU-1', 'Is_Active' => 'TRUE'] : null; }
    public function findByStudentNumber(string $number) { return null; }
    public function findByNationalId(string $nationalId) { return null; }
    public function generateNewId(string $prefix, int $padding = 6): string { return $prefix . '-000001'; }
    public function create(array $data) { return true; }
    public function update(string $id, array $data) { return true; }
    public function softDelete(string $id) { return true; }
    public function clearCache() {}
}

class IntegrityCompanyRepository implements CompanyRepositoryInterface
{
    public function fetchAll() { return collect(); }
    public function findById(string $id) { return null; }
    public function findByCode(string $code) { return null; }
    public function generateNewId(string $prefix, int $padding = 6): string { return $prefix . '-000001'; }
    public function create(array $data) { return true; }
    public function update(string $id, array $data) { return true; }
    public function softDelete(string $id) { return true; }
}

class IntegrityUpdateRepository extends \App\Repositories\GoogleSheets\BaseSheetRepository
{
    public function __construct(IntegrityUpdateResource $resource)
    {
        $this->service = (object) ['spreadsheets_values' => $resource];
        $this->spreadsheetId = 'test';
        $this->sheetName = 'TEST';
        $this->cacheKey = 'integrity_update';
        $this->primaryKey = 'Record_ID';
    }
}

class IntegrityUpdateResource
{
    public int $updateCalls = 0;
    private array $values = [['Record_ID', 'Status'], ['R-1', 'old']];
    public function get($spreadsheetId, $range)
    {
        return new class($this->values) {
            public function __construct(private array $values) {}
            public function getValues(): array { return $this->values; }
        };
    }
    public function update($spreadsheetId, $range, $body, $params)
    {
        $this->updateCalls++;
        $this->values[1] = $body->getValues()[0];
        throw new \RuntimeException('network timeout');
    }
}
