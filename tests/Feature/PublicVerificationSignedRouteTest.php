<?php

namespace Tests\Feature;

use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\HR\LeaveService;
use App\Services\HR\OvertimeService;
use App\Services\HR\PayrollService;
use Illuminate\Support\Facades\URL;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicVerificationSignedRouteTest extends TestCase
{
    #[DataProvider('publicVerificationRoutes')]
    public function test_raw_public_verification_id_is_denied(string $routeName, string $id): void
    {
        $this->get(route($routeName, ['id' => $id]))
            ->assertForbidden();
    }

    #[DataProvider('publicVerificationRoutes')]
    public function test_invalid_public_verification_signature_is_denied(string $routeName, string $id): void
    {
        $this->get(route($routeName, [
            'id' => $id,
            'expires' => now()->addMinutes(5)->timestamp,
            'signature' => 'invalid-signature',
        ]))->assertForbidden();
    }

    #[DataProvider('publicVerificationRoutes')]
    public function test_expired_public_verification_signature_is_denied(string $routeName, string $id): void
    {
        $url = URL::temporarySignedRoute($routeName, now()->subMinute(), ['id' => $id]);

        $this->get($url)->assertForbidden();
    }

    public function test_signature_for_one_document_cannot_be_reused_for_another_document(): void
    {
        $url = URL::temporarySignedRoute('invoices.verify-public', now()->addMinutes(5), ['id' => 'INV-A']);
        $tamperedUrl = str_replace('/verify-invoice/INV-A?', '/verify-invoice/INV-B?', $url);

        $this->get($tamperedUrl)->assertForbidden();
    }

    public function test_valid_signed_public_verification_routes_are_allowed(): void
    {
        $this->mockDocumentServices();

        $cases = [
            ['payments.verify-receipt-public', 'PAY-1'],
            ['invoices.verify-public', 'INV-1'],
            ['payrolls.verify-public', 'PAYROLL-1'],
            ['leaves.verify-public', 'LEAVE-1'],
            ['overtimes.verify-public', 'OT-1'],
        ];

        foreach ($cases as [$routeName, $id]) {
            $url = URL::temporarySignedRoute($routeName, now()->addMinutes(5), ['id' => $id]);

            $this->get($url)
                ->assertOk()
                ->assertSee('Dokumen Valid');
        }
    }

    public static function publicVerificationRoutes(): array
    {
        return [
            'receipt' => ['payments.verify-receipt-public', 'PAY-1'],
            'invoice' => ['invoices.verify-public', 'INV-1'],
            'payslip' => ['payrolls.verify-public', 'PAYROLL-1'],
            'leave' => ['leaves.verify-public', 'LEAVE-1'],
            'overtime' => ['overtimes.verify-public', 'OT-1'],
        ];
    }

    private function mockDocumentServices(): void
    {
        $payment = Mockery::mock(PaymentService::class);
        $payment->shouldReceive('getReceiptDocumentData')->andReturn([
            'payment' => ['Status' => 'Verified', 'Receipt_Number' => 'REC-TEST', 'Payment_Date' => '2026-01-01'],
            'customer' => ['name' => 'Syahwal Test'],
        ]);
        $this->app->instance(PaymentService::class, $payment);

        $invoice = Mockery::mock(InvoiceService::class);
        $invoice->shouldReceive('getInvoiceDocumentData')->andReturn([
            'invoice' => ['Status' => 'Paid', 'Document_Number' => 'INV-TEST', 'Category' => 'Education'],
            'customer' => ['name' => 'Syahwal Test'],
        ]);
        $this->app->instance(InvoiceService::class, $invoice);

        $payroll = Mockery::mock(PayrollService::class);
        $payroll->shouldReceive('getPayslipDocumentData')->andReturn([
            'payroll' => ['Status' => 'Paid', 'Document_Number' => 'PAYROLL-TEST', 'Payroll_Period' => '2026-01'],
            'employee' => ['Full_Name' => 'Employee Test'],
        ]);
        $this->app->instance(PayrollService::class, $payroll);

        $leave = Mockery::mock(LeaveService::class);
        $leave->shouldReceive('getLeaveDocumentData')->andReturn([
            'leave' => ['Status' => 'APPROVED', 'Document_Number' => 'LEAVE-TEST', 'Leave_Type' => 'SAKIT'],
            'employee' => ['Full_Name' => 'Employee Test'],
        ]);
        $this->app->instance(LeaveService::class, $leave);

        $overtime = Mockery::mock(OvertimeService::class);
        $overtime->shouldReceive('getOvertimeDocumentData')->andReturn([
            'overtime' => ['Status' => 'APPROVED', 'Document_Number' => 'OT-TEST', 'Date' => '2026-01-01'],
            'employee' => ['Full_Name' => 'Employee Test'],
        ]);
        $this->app->instance(OvertimeService::class, $overtime);
    }
}
