<?php

namespace Tests\Unit;

use Tests\TestCase;

class DocumentAccessFailClosedTest extends TestCase
{
    public function test_sensitive_document_services_require_explicit_public_verification_mode(): void
    {
        $services = [
            'app/Services/Finance/InvoiceService.php' => 'getInvoiceDocumentData',
            'app/Services/Finance/PaymentService.php' => 'getReceiptDocumentData',
            'app/Services/HR/PayrollService.php' => 'getPayslipDocumentData',
            'app/Services/HR/LeaveService.php' => 'getLeaveDocumentData',
            'app/Services/HR/OvertimeService.php' => 'getOvertimeDocumentData',
        ];

        foreach ($services as $path => $method) {
            $source = file_get_contents(base_path($path));

            $this->assertStringContainsString(
                "{$method}(string \$" . ($method === 'getInvoiceDocumentData' ? 'invoiceId' : (
                    $method === 'getReceiptDocumentData' ? 'paymentId' : (
                        $method === 'getPayslipDocumentData' ? 'payrollId' : (
                            $method === 'getLeaveDocumentData' ? 'leaveId' : 'overtimeId'
                        )
                    )
                )) . ", bool \$allowPublicVerification = false)",
                $source
            );
        }
    }

    public function test_only_signed_public_controllers_enable_public_document_mode(): void
    {
        $controllers = [
            'app/Http/Controllers/Finance/InvoiceController.php' => 'getInvoiceDocumentData($id, true)',
            'app/Http/Controllers/Finance/PaymentController.php' => 'getReceiptDocumentData($id, true)',
            'app/Http/Controllers/Hr/PayrollController.php' => 'getPayslipDocumentData($id, true)',
            'app/Http/Controllers/Hr/LeaveController.php' => 'getLeaveDocumentData($id, true)',
            'app/Http/Controllers/Hr/OvertimeController.php' => 'getOvertimeDocumentData($id, true)',
        ];

        foreach ($controllers as $path => $call) {
            $source = file_get_contents(base_path($path));
            $this->assertSame(1, substr_count($source, $call), "Mode publik harus hanya dipakai sekali di {$path}.");
        }
    }

    public function test_personal_payroll_identity_has_no_employee_id_fallback(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Dashboard/PersonalPayrollController.php'));

        $this->assertStringContainsString("firstWhere('User_ID', \$userId)", $source);
        $this->assertStringNotContainsString('!empty($user->Employee_ID)', $source);
        $this->assertStringNotContainsString("firstWhere('Employee_ID', \$user->Employee_ID)", $source);
    }
}
