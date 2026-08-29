<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\DocumentAutomationService;
use App\Services\Core\EnterpriseEventService;
use App\Services\HR\PayrollCalculationEngine;
use App\Services\HR\PayrollService;
use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PayrollStatusTransitionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 'USR-HR', 'User_ID' => 'USR-HR']));
        Cache::flush();
    }

    public function test_draft_payroll_cannot_be_marked_paid_directly(): void
    {
        $payrollRepository = Mockery::mock(PayrollRepositoryInterface::class);
        $payrollRepository->shouldReceive('getById')->once()->with('PRL-DRAFT')->andReturn([
            'Payroll_ID' => 'PRL-DRAFT',
            'Employee_ID' => 'EMP-001',
            'Payroll_Period' => '2026-08',
            'Net_Salary' => 5000000,
            'Status' => 'Draft',
        ]);
        $payrollRepository->shouldReceive('update')->never();

        $service = new PayrollService(
            $payrollRepository,
            Mockery::mock(SalaryComponentRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class),
            Mockery::mock(PayrollCalculationEngine::class)
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('tidak valid');

        $service->updateStatus('PRL-DRAFT', 'Paid', 'USR-FINANCE');
    }

    public function test_persistence_failure_does_not_emit_payroll_success_event(): void
    {
        $payrollRepository = Mockery::mock(PayrollRepositoryInterface::class);
        $payrollRepository->shouldReceive('getById')->once()->with('PRL-WAITING')->andReturn([
            'Payroll_ID' => 'PRL-WAITING',
            'Employee_ID' => 'EMP-001',
            'Status' => 'Waiting Approval',
        ]);
        $payrollRepository->shouldReceive('update')->once()->andReturn(false);
        $payrollRepository->shouldNotReceive('clearCache');

        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldNotReceive('dispatch');

        $service = new PayrollService(
            $payrollRepository,
            Mockery::mock(SalaryComponentRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            $event,
            Mockery::mock(PayrollCalculationEngine::class)
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Gagal menyimpan perubahan status');

        $service->updateStatus('PRL-WAITING', 'Approved', 'USR-HR');
    }

    public function test_paid_payslip_uses_the_persisted_payroll_snapshot(): void
    {
        $payroll = [
            'Payroll_ID' => 'PRL-APPROVED',
            'Employee_ID' => 'EMP-001',
            'Payroll_Period' => '2026-08',
            'Net_Salary' => 5000000,
            'Status' => 'Approved',
        ];

        $payrollRepository = Mockery::mock(PayrollRepositoryInterface::class);
        $payrollRepository->shouldReceive('getById')->once()->with('PRL-APPROVED')->andReturn($payroll);
        $payrollRepository->shouldReceive('update')->once()->with('PRL-APPROVED', Mockery::on(
            fn ($row) => ($row['Status'] ?? '') === 'Paid' && !empty($row['Paid_Date'])
        ))->andReturn(true);
        $payrollRepository->shouldReceive('clearCache')->once();

        $transactionRepository = Mockery::mock(TransactionRepositoryInterface::class);
        $transactionRepository->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Reference_Type' => 'Payroll', 'Reference_ID' => 'PRL-APPROVED', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(TransactionRepositoryInterface::class, $transactionRepository);

        $employeeRepository = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepository->shouldReceive('findById')->once()->with('EMP-001')->andReturn([
            'Employee_ID' => 'EMP-001',
            'Full_Name' => 'Employee Test',
        ]);

        $documentAutomation = Mockery::mock(DocumentAutomationService::class);
        $documentAutomation->shouldReceive('generateDocument')->once()->with(
            'Payroll',
            'Payroll',
            'PRL-APPROVED',
            Mockery::on(fn ($payload) =>
                ($payload['payroll']['Status'] ?? '') === 'Paid'
                && !empty($payload['payroll']['Paid_Date'])
            ),
            'pdf.official_payslip',
            'USR-HR'
        )->andReturn(['Document_ID' => 'PAY-2600001']);
        $this->app->instance(DocumentAutomationService::class, $documentAutomation);

        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldReceive('dispatch')->once();

        $service = new PayrollService(
            $payrollRepository,
            Mockery::mock(SalaryComponentRepositoryInterface::class),
            $employeeRepository,
            $event,
            Mockery::mock(PayrollCalculationEngine::class)
        );

        $this->assertTrue($service->updateStatus('PRL-APPROVED', 'Paid', 'SPOOFED-ACTOR'));
    }

    public function test_unknown_role_cannot_read_arbitrary_payslip(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-UNKNOWN',
            'User_ID' => 'USR-UNKNOWN',
            'Role' => 'UNKNOWN',
        ]));

        $payrollRepository = Mockery::mock(PayrollRepositoryInterface::class);
        $payrollRepository->shouldReceive('getById')->once()->with('PRL-PRIVATE')->andReturn([
            'Payroll_ID' => 'PRL-PRIVATE',
            'Employee_ID' => 'EMP-001',
            'Status' => 'Paid',
        ]);

        $service = new PayrollService(
            $payrollRepository,
            Mockery::mock(SalaryComponentRepositoryInterface::class),
            Mockery::mock(EmployeeRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class),
            Mockery::mock(PayrollCalculationEngine::class)
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Role pengguna tidak diizinkan');

        $service->getPayslipDocumentData('PRL-PRIVATE');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
