<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\ActivityLogService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\NotificationService;
use App\Services\Core\SystemSettingService;
use App\Services\Dashboard\FinanceDashboardService;
use App\Services\Finance\FinanceReconciliationService;
use App\Services\Finance\FinanceReportService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\TransactionService;
use Mockery;
use Tests\TestCase;

class FinanceDataIntegrityReconciliationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_canonical_invoice_matrix_uses_only_unique_verified_payments(): void
    {
        $payments = collect([
            $this->payment('PAY-PARTIAL', 'INV-PARTIAL', 2_000_000, 'Verified'),
            $this->payment('PAY-PENDING', 'INV-PARTIAL', 1_000_000, 'Waiting Verification'),
            $this->payment('PAY-REJECTED', 'INV-PARTIAL', 500_000, 'Rejected'),
            $this->payment('PAY-MULTI-1', 'INV-MULTI', 2_000_000, 'Verified'),
            $this->payment('PAY-MULTI-2', 'INV-MULTI', 1_500_000, 'Verified'),
            $this->payment('PAY-FULL', 'INV-FULL', 7_500_000, 'Verified'),
            $this->payment('PAY-OVER', 'INV-OVER', 8_000_000, 'Verified'),
            $this->payment('PAY-DUP', 'INV-DUP', 2_000_000, 'Verified'),
            $this->payment('PAY-DUP', 'INV-DUP', 2_000_000, 'Verified'),
        ]);
        $service = $this->invoiceService($payments);

        $none = $service->formatInvoiceRecord($this->invoice('INV-NONE'), null, $payments);
        $partial = $service->formatInvoiceRecord($this->invoice('INV-PARTIAL'), null, $payments);
        $multiple = $service->formatInvoiceRecord($this->invoice('INV-MULTI'), null, $payments);
        $full = $service->formatInvoiceRecord($this->invoice('INV-FULL'), null, $payments);
        $over = $service->formatInvoiceRecord($this->invoice('INV-OVER'), null, $payments);
        $duplicate = $service->formatInvoiceRecord($this->invoice('INV-DUP'), null, $payments);

        $this->assertSame([0.0, 7_500_000.0, 'Waiting Payment'], [$none['Paid_Amount'], $none['Remaining_Amount'], $none['Status']]);
        $this->assertSame([2_000_000.0, 5_500_000.0, 'Partial Paid'], [$partial['Paid_Amount'], $partial['Remaining_Amount'], $partial['Status']]);
        $this->assertSame([3_500_000.0, 4_000_000.0, 'Partial Paid'], [$multiple['Paid_Amount'], $multiple['Remaining_Amount'], $multiple['Status']]);
        $this->assertSame([7_500_000.0, 0.0, 'Paid'], [$full['Paid_Amount'], $full['Remaining_Amount'], $full['Status']]);
        $this->assertSame([8_000_000.0, 0.0, 'Paid'], [$over['Paid_Amount'], $over['Remaining_Amount'], $over['Status']]);
        $this->assertSame([2_000_000.0, 5_500_000.0], [$duplicate['Paid_Amount'], $duplicate['Remaining_Amount']]);
    }

    public function test_education_summary_ignores_stale_invoice_paid_amount(): void
    {
        $payments = collect([$this->payment('PAY-2M', 'INV-7M5', 2_000_000, 'Verified')]);
        $service = $this->invoiceService($payments, true);
        $setting = Mockery::mock(SystemSettingService::class);
        $setting->shouldReceive('getDefaultTuitionFee')->once()->andReturn(7_500_000);
        $this->app->instance(SystemSettingService::class, $setting);

        $staleInvoice = $this->invoice('INV-7M5') + [
            'Student_ID' => 'STU-1',
            'Invoice_Type' => 'STUDENT',
            'Category' => 'Biaya Pendidikan',
            'Paid_Amount' => 0,
            'Remaining_Amount' => 7_500_000,
        ];
        $summary = $service->getStudentEducationBillingSummary(
            'STU-1',
            null,
            collect([$staleInvoice]),
            null,
            ['Student_ID' => 'STU-1']
        );

        $this->assertSame(2_000_000.0, $summary['education_paid']);
        $this->assertSame(5_500_000.0, $summary['remaining_to_pay']);
        $this->assertSame(27.0, (float) $summary['progress']);
    }

    public function test_reconciliation_audit_reports_relationship_and_ledger_anomalies(): void
    {
        $payments = collect([
            $this->payment('PAY-DUP', 'INV-1', 2_000_000, 'Verified'),
            $this->payment('PAY-DUP', 'INV-1', 2_000_000, 'Verified'),
            $this->payment('PAY-CROSS', 'INV-1', 6_000_000, 'Verified', 'STU-2'),
            $this->payment('PAY-ORPHAN', 'INV-MISSING', 100_000, 'Verified'),
            $this->payment('PAY-UNALLOCATED', '', 100_000, 'Verified') + ['Payment_Type' => 'STUDENT_SELF_SERVICE'],
        ]);
        $invoiceService = $this->invoiceService($payments);
        $audit = new FinanceReconciliationService(
            Mockery::mock(InvoiceRepositoryInterface::class),
            Mockery::mock(PaymentRepositoryInterface::class),
            Mockery::mock(TransactionRepositoryInterface::class),
            Mockery::mock(StudentRepositoryInterface::class),
            $invoiceService
        );

        $result = $audit->audit(
            collect([$this->invoice('INV-1') + ['Student_ID' => 'STU-1', 'Invoice_Type' => 'STUDENT']]),
            $payments,
            collect([
                ['Transaction_ID' => 'TRX-1', 'Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-DUP', 'Type' => 'Income', 'Amount' => 1, 'Is_Active' => 'TRUE'],
                ['Transaction_ID' => 'TRX-ORPHAN', 'Reference_Type' => 'Payment', 'Reference_ID' => 'PAY-NOT-FOUND', 'Type' => 'Income', 'Amount' => 1, 'Is_Active' => 'TRUE'],
            ]),
            collect([['Student_ID' => 'STU-1']])
        );
        $codes = collect($result['findings'])->pluck('code');

        $this->assertFalse($result['is_consistent']);
        $this->assertTrue($codes->contains('duplicate_payment_id'));
        $this->assertTrue($codes->contains('accepted_payment_exceeds_invoice_total'));
        $this->assertTrue($codes->contains('payment_student_mismatch'));
        $this->assertTrue($codes->contains('payment_invoice_not_found'));
        $this->assertTrue($codes->contains('verified_payment_ledger_mismatch'));
        $this->assertTrue($codes->contains('verified_payment_ledger_missing'));
        $this->assertTrue($codes->contains('transaction_payment_not_found'));
        $this->assertTrue($codes->contains('verified_self_service_payment_unallocated'));
    }

    public function test_reconciliation_audit_accepts_a_consistent_partial_payment_snapshot(): void
    {
        $payment = $this->payment('PAY-OK', 'INV-OK', 2_000_000, 'Verified');
        $payments = collect([$payment]);
        $invoiceService = $this->invoiceService($payments);
        $audit = new FinanceReconciliationService(
            Mockery::mock(InvoiceRepositoryInterface::class),
            Mockery::mock(PaymentRepositoryInterface::class),
            Mockery::mock(TransactionRepositoryInterface::class),
            Mockery::mock(StudentRepositoryInterface::class),
            $invoiceService
        );

        $result = $audit->audit(
            collect([array_merge($this->invoice('INV-OK'), [
                'Student_ID' => 'STU-1',
                'Invoice_Type' => 'STUDENT',
                'Status' => 'Partial Paid',
            ])]),
            $payments,
            collect([[
                'Transaction_ID' => 'TRX-OK',
                'Reference_Type' => 'Payment',
                'Reference_ID' => 'PAY-OK',
                'Type' => 'Income',
                'Amount' => 2_000_000,
                'Is_Active' => 'TRUE',
            ]]),
            collect([['Student_ID' => 'STU-1']])
        );

        $this->assertTrue($result['is_consistent']);
        $this->assertSame([], $result['findings']);
    }

    public function test_report_and_dashboard_consume_the_same_canonical_remaining_balance(): void
    {
        $payments = collect([$this->payment('PAY-2M', 'INV-7M5', 2_000_000, 'Verified')]);
        $invoiceService = $this->invoiceService($payments);
        $canonical = $invoiceService->formatInvoiceRecord(
            $this->invoice('INV-7M5') + ['Student_ID' => 'STU-1', 'Invoice_Type' => 'STUDENT'],
            null,
            $payments
        );

        $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepo->shouldReceive('fetchAll')->once()->andReturn(collect([$this->invoice('INV-7M5') + ['Student_ID' => 'STU-1', 'Invoice_Type' => 'STUDENT']]));
        $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepo->shouldReceive('fetchAll')->once()->andReturn($payments);
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([['Student_ID' => 'STU-1', 'Full_Name' => 'Siswa Satu']]));
        $companyRepo = Mockery::mock(CompanyRepositoryInterface::class);
        $companyRepo->shouldReceive('fetchAll')->once()->andReturn(collect());
        $report = new FinanceReportService(
            Mockery::mock(TransactionRepositoryInterface::class),
            $invoiceRepo,
            $paymentRepo,
            $studentRepo,
            $companyRepo,
            Mockery::mock(AccountRepositoryInterface::class)
        );
        $this->app->instance(InvoiceService::class, $invoiceService);
        $reportData = $report->getOutstandingInvoices('STUDENT', 'STU-1');

        $dashboardInvoiceService = Mockery::mock(InvoiceService::class);
        $dashboardInvoiceService->shouldReceive('getAll')->once()->andReturn(collect([$canonical]));
        $dashboardPaymentService = Mockery::mock(PaymentService::class);
        $dashboardPaymentService->shouldReceive('getAll')->once()->andReturn(collect());
        $transactionService = Mockery::mock(TransactionService::class);
        $transactionService->shouldReceive('getAll')->once()->andReturn(collect());
        $activity = Mockery::mock(ActivityLogService::class);
        $activity->shouldReceive('getAllLogs')->once()->andReturn(collect());
        $notification = Mockery::mock(NotificationService::class);
        $dashboard = new FinanceDashboardService(
            $dashboardInvoiceService,
            $dashboardPaymentService,
            $transactionService,
            Mockery::mock(FinanceReportService::class),
            $activity,
            $notification
        );
        $dashboardData = $dashboard->getDashboardData();

        $this->assertSame(5_500_000.0, (float) $reportData['total_outstanding']);
        $this->assertSame(2_000_000.0, (float) $reportData['invoices'][0]['Paid_Amount']);
        $this->assertSame(5_500_000.0, (float) $dashboardData['kpi']['outstanding_amount']);
    }

    private function invoice(string $id): array
    {
        return [
            'Invoice_ID' => $id,
            'Amount' => 7_500_000,
            'Status' => 'Waiting Payment',
            'Due_Date' => now()->addYear()->toDateString(),
            'Is_Active' => 'TRUE',
        ];
    }

    private function payment(string $id, string $invoiceId, float $amount, string $status, string $studentId = 'STU-1'): array
    {
        return [
            'Payment_ID' => $id,
            'Invoice_ID' => $invoiceId,
            'Student_ID' => $studentId,
            'Amount_Paid' => $amount,
            'Status' => $status,
            'Is_Active' => 'TRUE',
        ];
    }

    private function invoiceService($payments, bool $expectPaymentRead = false): InvoiceService
    {
        $paymentRepository = Mockery::mock(PaymentRepositoryInterface::class);
        if ($expectPaymentRead) {
            $paymentRepository->shouldReceive('getAll')->once()->andReturn($payments);
        }

        return new InvoiceService(
            Mockery::mock(InvoiceRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class),
            Mockery::mock(StudentRepositoryInterface::class),
            Mockery::mock(CompanyRepositoryInterface::class),
            $paymentRepository
        );
    }
}
