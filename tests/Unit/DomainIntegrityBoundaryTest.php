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
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class DomainIntegrityBoundaryTest extends TestCase
{
    public function test_mixed_case_student_role_keeps_payment_collection_scoped(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'student']));

        $payments = Mockery::mock(PaymentRepositoryInterface::class);
        $payments->shouldReceive('getAll')->once()->andReturn(collect([
            ['Payment_ID' => 'PAY-MINE', 'Student_ID' => 'STU-001', 'Is_Active' => 'TRUE'],
            ['Payment_ID' => 'PAY-OTHER', 'Student_ID' => 'STU-002', 'Is_Active' => 'TRUE'],
        ]));

        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-001', 'User_ID' => 'USR-STU'],
        ]));

        $service = new PaymentService(
            $payments,
            Mockery::mock(InvoiceRepositoryInterface::class),
            $students,
            Mockery::mock(CompanyRepositoryInterface::class),
            Mockery::mock(AccountRepositoryInterface::class),
            Mockery::mock(TransactionRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        $this->assertSame(['PAY-MINE'], $service->getAll()->pluck('Payment_ID')->all());
    }

    public function test_mixed_case_student_role_keeps_invoice_collection_scoped(): void
    {
        Cache::flush();
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'student']));

        $invoices = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoices->shouldReceive('getAll')->once()->andReturn(collect([
            ['Invoice_ID' => 'INV-MINE', 'Student_ID' => 'STU-001', 'Amount' => 100, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE'],
            ['Invoice_ID' => 'INV-OTHER', 'Student_ID' => 'STU-002', 'Amount' => 200, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE'],
        ]));

        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-001', 'User_ID' => 'USR-STU'],
        ]));

        $payments = Mockery::mock(PaymentRepositoryInterface::class);
        $payments->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());

        $service = new InvoiceService(
            $invoices,
            Mockery::mock(EnterpriseEventService::class),
            $students,
            Mockery::mock(CompanyRepositoryInterface::class),
            $payments
        );

        $this->assertSame(['INV-MINE'], $service->getAll()->pluck('Invoice_ID')->all());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
