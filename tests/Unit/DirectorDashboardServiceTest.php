<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface;
use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\DepartmentRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Services\Dashboard\DirectorDashboardService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class DirectorDashboardServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_kpis_follow_verified_sources_period_and_status_rules(): void
    {
        Carbon::setTestNow('2026-10-17 10:00:00');
        $service = $this->service([
            StudentRepositoryInterface::class => [
                ['Student_ID' => 'STU-1', 'Registration_Date' => '2026-10-02', 'Program_ID' => 'P1', 'Batch_ID' => 'B1', 'Enrollment_Status' => 'Aktif Belajar', 'Is_Active' => 'TRUE'],
                ['Student_ID' => 'STU-2', 'Registration_Date' => '2026-09-20', 'Program_ID' => 'P1', 'Batch_ID' => 'B1', 'Enrollment_Status' => 'Aktif Belajar', 'Is_Active' => 'TRUE'],
                ['Student_ID' => 'STU-3', 'Registration_Date' => '2026-10-03', 'Enrollment_Status' => 'ALUMNI', 'Is_Active' => 'TRUE'],
            ],
            EmployeeRepositoryInterface::class => [
                ['Employee_ID' => 'EMP-1', 'Employment_Status' => 'ACTIVE', 'Is_Active' => 'TRUE'],
                ['Employee_ID' => 'EMP-2', 'Employment_Status' => 'RESIGNED', 'Is_Active' => 'TRUE'],
            ],
            TeacherRepositoryInterface::class => [['Teacher_ID' => 'T-1', 'Is_Active' => 'TRUE']],
            CompanyRepositoryInterface::class => [['Company_ID' => 'C-1', 'Is_Active' => 'TRUE']],
            ProgramRepositoryInterface::class => [['Program_ID' => 'P1', 'Program_Name' => 'TG', 'Is_Active' => 'TRUE']],
            BatchRepositoryInterface::class => [['Batch_ID' => 'B1', 'Batch_Name' => '2026', 'Is_Active' => 'TRUE']],
            TransactionRepositoryInterface::class => [
                ['Transaction_ID' => 'TX-1', 'Transaction_Date' => '2026-10-03', 'Type' => 'Income', 'Amount' => 1500, 'Is_Active' => 'TRUE'],
                ['Transaction_ID' => 'TX-2', 'Transaction_Date' => '2026-10-04', 'Type' => 'Expense', 'Amount' => 400, 'Is_Active' => 'TRUE'],
                ['Transaction_ID' => 'TX-OLD', 'Transaction_Date' => '2026-09-30', 'Type' => 'Income', 'Amount' => 9999, 'Is_Active' => 'TRUE'],
                ['Transaction_ID' => 'TX-DELETED', 'Transaction_Date' => '2026-10-05', 'Type' => 'Income', 'Amount' => 8000, 'Is_Active' => 'FALSE'],
            ],
            InvoiceRepositoryInterface::class => [
                ['Invoice_ID' => 'INV-1', 'Status' => 'Waiting Payment', 'Remaining_Amount' => 750, 'Due_Date' => '2026-10-01', 'Is_Active' => 'TRUE'],
                ['Invoice_ID' => 'INV-2', 'Status' => 'Cancelled', 'Remaining_Amount' => 900, 'Due_Date' => '2026-10-01', 'Is_Active' => 'TRUE'],
            ],
            PaymentRepositoryInterface::class => [
                ['Payment_ID' => 'PAY-1', 'Payment_Date' => '2026-10-08', 'Status' => 'Waiting Verification', 'Is_Active' => 'TRUE'],
                ['Payment_ID' => 'PAY-OLD', 'Payment_Date' => '2026-09-08', 'Status' => 'Waiting Verification', 'Is_Active' => 'TRUE'],
            ],
            ApprovalRepositoryInterface::class => [
                ['Approval_ID' => 'APP-1', 'Current_Approver' => 'DIRECTOR', 'Status' => 'Waiting Approval', 'Submitted_At' => '2026-10-10'],
                ['Approval_ID' => 'APP-2', 'Current_Approver' => 'FINANCE', 'Status' => 'Waiting Approval', 'Submitted_At' => '2026-10-10'],
            ],
            AttendanceRepositoryInterface::class => [
                ['Attendance_ID' => 'ATT-1', 'Employee_ID' => 'EMP-1', 'Attendance_Date' => '2026-10-02', 'Status' => 'Hadir', 'Is_Active' => 'TRUE'],
                ['Attendance_ID' => 'ATT-2', 'Employee_ID' => 'EMP-1', 'Attendance_Date' => '2026-10-03', 'Status' => 'Terlambat', 'Is_Active' => 'TRUE'],
                ['Attendance_ID' => 'ATT-STUDENT', 'Student_ID' => 'STU-1', 'Attendance_Date' => '2026-10-03', 'Status' => 'Hadir', 'Is_Active' => 'TRUE'],
            ],
        ]);

        $data = $service->getDashboardData(['start_date' => '2026-10-01', 'end_date' => '2026-10-17']);

        $this->assertSame(2, $data['kpi']['active_students']);
        $this->assertSame(1, $data['kpi']['new_students']);
        $this->assertSame(1500.0, $data['kpi']['income']);
        $this->assertSame(400.0, $data['kpi']['expense']);
        $this->assertSame(1100.0, $data['kpi']['net_cash_flow']);
        $this->assertSame(750.0, $data['kpi']['outstanding_amount']);
        $this->assertSame(1, $data['kpi']['overdue_invoices']);
        $this->assertSame(1, $data['kpi']['pending_payments']);
        $this->assertSame(1, $data['kpi']['pending_approvals']);
        $this->assertSame(['total' => 2, 'present' => 1, 'late' => 1, 'sick' => 0, 'permitted' => 0, 'absent' => 0], $data['attendance']);
    }

    public function test_reports_apply_relevant_filters_and_never_mix_student_attendance(): void
    {
        $service = $this->service([
            EmployeeRepositoryInterface::class => [
                ['Employee_ID' => 'EMP-1', 'Full_Name' => 'Ayu', 'Department_ID' => 'DEP-1', 'Employment_Status' => 'ACTIVE', 'Is_Active' => 'TRUE'],
            ],
            DepartmentRepositoryInterface::class => [['Department_ID' => 'DEP-1', 'Department_Name' => 'Operasional', 'Is_Active' => 'TRUE']],
            AttendanceRepositoryInterface::class => [
                ['Attendance_ID' => 'A-1', 'Employee_ID' => 'EMP-1', 'Attendance_Date' => '2026-10-02', 'Status' => 'LATE', 'Is_Active' => 'TRUE'],
                ['Attendance_ID' => 'A-2', 'Student_ID' => 'STU-1', 'Attendance_Date' => '2026-10-02', 'Status' => 'LATE', 'Is_Active' => 'TRUE'],
                ['Attendance_ID' => 'A-3', 'Employee_ID' => 'EMP-1', 'Attendance_Date' => '2026-09-02', 'Status' => 'LATE', 'Is_Active' => 'TRUE'],
            ],
        ]);

        $report = $service->report('hr', $this->filters(['status' => 'TERLAMBAT', 'department_id' => 'DEP-1', 'search' => 'ayu']));

        $this->assertCount(1, $report['rows']);
        $this->assertSame('A-1', $report['rows']->first()['Attendance_ID']);
        $this->assertSame('Terlambat', $report['rows']->first()['Status_Label']);
    }

    public function test_invalid_money_does_not_become_a_successful_zero(): void
    {
        $service = $this->service([
            TransactionRepositoryInterface::class => [[
                'Transaction_ID' => 'TX-BROKEN', 'Transaction_Date' => '2026-10-03',
                'Type' => 'Income', 'Amount' => 'not-a-number', 'Is_Active' => 'TRUE',
            ]],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $service->getDashboardData(['start_date' => '2026-10-01', 'end_date' => '2026-10-17']);
    }

    public function test_approval_report_includes_completed_decisions_only_when_actor_was_director(): void
    {
        $service = $this->service([
            RoleRepositoryInterface::class => [
                ['Role_ID' => 'R-DIR', 'Role_Name' => 'DIRECTOR', 'Is_Active' => 'TRUE'],
                ['Role_ID' => 'R-FIN', 'Role_Name' => 'FINANCE', 'Is_Active' => 'TRUE'],
            ],
            UserRepositoryInterface::class => [
                ['User_ID' => 'USR-DIR', 'Role_ID' => 'R-DIR', 'Is_Active' => 'TRUE'],
                ['User_ID' => 'USR-FIN', 'Role_ID' => 'R-FIN', 'Is_Active' => 'TRUE'],
            ],
            ApprovalRepositoryInterface::class => [
                ['Approval_ID' => 'APP-DIR', 'Status' => 'Approved', 'Reference_ID' => 'PAYROLL-1'],
                ['Approval_ID' => 'APP-FIN', 'Status' => 'Rejected', 'Reference_ID' => 'BILL-1'],
            ],
            ApprovalHistoryRepositoryInterface::class => [
                ['History_ID' => 'H-1', 'Approval_ID' => 'APP-DIR', 'Action' => 'Approve', 'Acted_By' => 'USR-DIR', 'Created_At' => '2026-10-10'],
                ['History_ID' => 'H-2', 'Approval_ID' => 'APP-FIN', 'Action' => 'Reject', 'Acted_By' => 'USR-FIN', 'Created_At' => '2026-10-11'],
            ],
        ]);

        $report = $service->report('approvals', $this->filters(['division' => 'approvals']));

        $this->assertCount(1, $report['rows']);
        $this->assertSame('APP-DIR', $report['rows']->first()['Approval_ID']);
        $this->assertSame('USR-DIR', $report['rows']->first()['Decision_By']);
    }

    private function filters(array $overrides = []): array
    {
        return array_merge([
            'start_date' => '2026-10-01', 'end_date' => '2026-10-17', 'division' => 'hr',
            'search' => '', 'status' => '', 'program_id' => '', 'batch_id' => '', 'department_id' => '',
        ], $overrides);
    }

    private function service(array $rowsByContract): DirectorDashboardService
    {
        $contracts = [
            EmployeeRepositoryInterface::class => 'fetchAll',
            TeacherRepositoryInterface::class => 'fetchAll',
            StudentRepositoryInterface::class => 'fetchAll',
            CompanyRepositoryInterface::class => 'fetchAll',
            ActivityLogRepositoryInterface::class => 'fetchAll',
            ProgramRepositoryInterface::class => 'fetchAll',
            BatchRepositoryInterface::class => 'fetchAll',
            DepartmentRepositoryInterface::class => 'fetchAll',
            TransactionRepositoryInterface::class => 'fetchAll',
            InvoiceRepositoryInterface::class => 'getAll',
            PaymentRepositoryInterface::class => 'getAll',
            ApprovalRepositoryInterface::class => 'getAll',
            ApprovalHistoryRepositoryInterface::class => 'getAll',
            UserRepositoryInterface::class => 'fetchAll',
            RoleRepositoryInterface::class => 'fetchAll',
            AttendanceRepositoryInterface::class => 'fetchAll',
        ];
        $mocks = [];
        foreach ($contracts as $contract => $method) {
            $mock = Mockery::mock($contract);
            $mock->shouldReceive($method)->zeroOrMoreTimes()->andReturn(collect($rowsByContract[$contract] ?? []));
            $mocks[$contract] = $mock;
        }

        return new DirectorDashboardService(
            $mocks[EmployeeRepositoryInterface::class],
            $mocks[TeacherRepositoryInterface::class],
            $mocks[StudentRepositoryInterface::class],
            $mocks[CompanyRepositoryInterface::class],
            $mocks[ActivityLogRepositoryInterface::class],
            $mocks[ProgramRepositoryInterface::class],
            $mocks[BatchRepositoryInterface::class],
            $mocks[DepartmentRepositoryInterface::class],
            $mocks[TransactionRepositoryInterface::class],
            $mocks[InvoiceRepositoryInterface::class],
            $mocks[PaymentRepositoryInterface::class],
            $mocks[ApprovalRepositoryInterface::class],
            $mocks[ApprovalHistoryRepositoryInterface::class],
            $mocks[UserRepositoryInterface::class],
            $mocks[RoleRepositoryInterface::class],
            $mocks[AttendanceRepositoryInterface::class],
        );
    }
}
