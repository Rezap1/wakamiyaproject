<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\InvoiceController;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\StudentBillingNotificationService;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class InvoiceControllerWorkflowTest extends TestCase
{
    public function test_publish_uses_invoice_publish_workflow_instead_of_raw_status_update(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('update')->never();
        $invoiceService->shouldReceive('publish')->once()->with('INV-DRAFT-001')->andReturn([
            'Invoice_ID' => 'INV-DRAFT-001',
            'Status' => 'Waiting Payment',
        ]);

        $controller = new InvoiceController($invoiceService);
        $response = $controller->publish('INV-DRAFT-001');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('invoices.show', 'INV-DRAFT-001'), $response->getTargetUrl());
        $this->assertStringContainsString('Waiting Payment', session('success'));
    }

    public function test_cancel_uses_invoice_cancel_workflow_instead_of_raw_status_update(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('update')->never();
        $invoiceService->shouldReceive('cancel')->once()->with('INV-WAIT-001')->andReturn([
            'Invoice_ID' => 'INV-WAIT-001',
            'Status' => 'Cancelled',
        ]);

        $controller = new InvoiceController($invoiceService);
        $response = $controller->cancel('INV-WAIT-001');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('invoices.show', 'INV-WAIT-001'), $response->getTargetUrl());
        $this->assertStringContainsString('Cancelled', session('success'));
    }

    public function test_invoice_notification_does_not_fallback_to_all_without_student_target(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldNotReceive('getById');

        $billingNotificationService = Mockery::mock(StudentBillingNotificationService::class);
        $billingNotificationService->shouldReceive('sendReminder')->once()->with('INV-COMPANY-001')
            ->andThrow(new \DomainException(
                'Pengingat pembayaran hanya dapat dikirim untuk invoice siswa yang memiliki Student_ID.',
                StudentBillingNotificationService::ERROR_NOT_STUDENT_INVOICE,
            ));

        $controller = new InvoiceController($invoiceService, $billingNotificationService);
        $request = Request::create('/finance/invoices/INV-COMPANY-001/notify', 'POST', [
            'message' => 'Harap bayar invoice.',
        ]);

        $response = $controller->notify($request, 'INV-COMPANY-001');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue(session()->has('error'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
