<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\ApprovalHistoryService;
use App\Services\Core\ApprovalService;
use App\Services\Core\RoleService;
use App\Services\Dashboard\DirectorDashboardService;
use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class DirectorDashboardFeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_director_dashboard_renders_verified_sections_and_no_personal_qr_action(): void
    {
        $this->actingAs($this->user('DIRECTOR'));
        $this->mockRole('DIRECTOR');
        $service = Mockery::mock(DirectorDashboardService::class);
        $service->shouldReceive('getDashboardData')->once()->with([
            'start_date' => '2026-10-01', 'end_date' => '2026-10-17',
        ])->andReturn($this->dashboardData());
        $this->app->instance(DirectorDashboardService::class, $service);

        $response = $this->get(route('dashboard.director', ['start_date' => '2026-10-01', 'end_date' => '2026-10-17']));

        $response->assertOk()
            ->assertSee('data-director-dashboard', false)
            ->assertSee('Ringkasan Eksekutif')
            ->assertSee('Kehadiran Pegawai')
            ->assertSee('Antrean Keputusan')
            ->assertSee('Pusat Laporan')
            ->assertDontSee('Pindai QR Pegawai')
            ->assertDontSee(route('hr.attendance.qr.scanner'));
    }

    public function test_report_center_forwards_filters_paginates_and_exports_formula_safe_csv(): void
    {
        $this->actingAs($this->user('DIRECTOR'));
        $this->mockRole('DIRECTOR');
        $filters = [
            'start_date' => '2026-10-01', 'end_date' => '2026-10-17', 'division' => 'finance',
            'search' => 'vendor', 'status' => 'Expense', 'program_id' => '', 'batch_id' => '', 'department_id' => '',
        ];
        $report = [
            'title' => 'Finance', 'source' => 'FINANCE_TRANSACTION',
            'columns' => ['Transaction_ID' => 'ID', 'Description' => 'Keterangan', 'Amount' => 'Nominal'],
            'rows' => collect([['Transaction_ID' => 'TX-1', 'Description' => '=HYPERLINK("bad")', 'Amount' => 500]]),
            'options' => [],
        ];
        $service = Mockery::mock(DirectorDashboardService::class);
        $service->shouldReceive('report')->twice()->with('finance', $filters)->andReturn($report);
        $this->app->instance(DirectorDashboardService::class, $service);

        $query = $filters;
        $this->get(route('director.reports', $query))
            ->assertOk()->assertSee('Pusat Laporan Direktur')->assertSee('TX-1')->assertSee('Rp 500');

        $export = $this->get(route('director.reports.export-csv', $query));
        $export->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $export->streamedContent());
    }

    public function test_director_can_monitor_employee_attendance_but_cannot_scan_or_mutate_finance(): void
    {
        $this->actingAs($this->user('DIRECTOR'));
        $this->mockRole('DIRECTOR');

        $attendance = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendance->shouldReceive('fetchAll')->once()->andReturn(collect());
        $employees = Mockery::mock(EmployeeRepositoryInterface::class);
        // Layout identity composers also resolve employees; the attendance
        // assertion is about authorization and the read-only controller.
        $employees->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect());
        $this->app->instance(AttendanceRepositoryInterface::class, $attendance);
        $this->app->instance(EmployeeRepositoryInterface::class, $employees);

        $this->get(route('hr.attendance.monitoring'))->assertOk();
        $this->get(route('hr.attendance.qr.scanner'))->assertForbidden();
        $this->post(route('hr.attendance.qr.scan'), [])->assertForbidden();
        $this->get(route('transactions.create'))->assertForbidden();
        $this->post(route('transactions.store'), [])->assertForbidden();
    }

    public function test_student_cannot_access_director_dashboard_reports_or_employee_monitoring(): void
    {
        $this->actingAs($this->user('STUDENT'));
        $this->mockRole('STUDENT');

        $this->get(route('dashboard.director'))->assertForbidden();
        $this->get(route('director.reports'))->assertForbidden();
        $this->get(route('director.reports.export-csv'))->assertForbidden();
        $this->get(route('hr.attendance.monitoring'))->assertForbidden();
    }

    public function test_dashboard_rejects_invalid_or_excessive_periods(): void
    {
        $this->actingAs($this->user('DIRECTOR'));
        $this->mockRole('DIRECTOR');
        $service = Mockery::mock(DirectorDashboardService::class);
        $service->shouldNotReceive('getDashboardData');
        $this->app->instance(DirectorDashboardService::class, $service);

        $this->get(route('dashboard.director', ['start_date' => '2026-10-20', 'end_date' => '2026-10-01']))->assertSessionHasErrors('end_date');
        $this->get(route('dashboard.director', ['start_date' => '2024-01-01', 'end_date' => '2026-10-01']))->assertSessionHasErrors('end_date');
    }

    public function test_rejection_requires_a_reason_before_workflow_mutation(): void
    {
        $this->actingAs($this->user('DIRECTOR'));
        $this->mockRole('DIRECTOR');
        $approval = Mockery::mock(ApprovalService::class);
        $approval->shouldNotReceive('getById');
        $approval->shouldNotReceive('reject');
        $this->app->instance(ApprovalService::class, $approval);
        $this->app->instance(ApprovalHistoryService::class, Mockery::mock(ApprovalHistoryService::class));

        $this->from(route('approvals.show', 'APP-1'))
            ->post(route('approvals.reject', 'APP-1'), [])
            ->assertRedirect(route('approvals.show', 'APP-1'))
            ->assertSessionHasErrors('remarks');
    }

    private function dashboardData(): array
    {
        return [
            'filters' => ['start_date' => '2026-10-01', 'end_date' => '2026-10-17'],
            'kpi' => [
                'active_students' => 10, 'new_students' => 2, 'active_employees' => 5,
                'active_teachers' => 3, 'active_companies' => 4, 'income' => 1000,
                'expense' => 250, 'net_cash_flow' => 750, 'outstanding_amount' => 300,
                'overdue_invoices' => 1, 'pending_payments' => 1, 'pending_approvals' => 1,
            ],
            'attendance' => ['total' => 3, 'present' => 1, 'late' => 1, 'sick' => 0, 'permitted' => 1, 'absent' => 0],
            'approvals' => [['id' => 'APP-1', 'reference' => 'PAY-1', 'type' => 'Payroll', 'requester' => 'USR-1', 'priority' => 'High', 'submitted_at' => '2026-10-10', 'url' => route('approvals.show', 'APP-1')]],
            'alerts' => [['level' => 'high', 'title' => 'Keputusan menunggu Direktur', 'description' => '1 pengajuan.', 'url' => route('approvals.index')]],
            'distributions' => ['programs' => [['label' => 'TG', 'value' => 10]], 'batches' => [['label' => '2026', 'value' => 10]]],
            'cash_flow_trend' => [['label' => 'Okt 2026', 'income' => 1000, 'expense' => 250]],
            'recent_activities' => [['module' => 'FINANCE', 'title' => 'CREATE', 'description' => 'Transaksi dibuat', 'occurred_at' => '2026-10-10 10:00:00']],
            'data_updated_at' => Carbon::parse('2026-10-17 10:00:00', 'Asia/Jakarta'),
            'sources' => [],
        ];
    }

    private function user(string $role): GenericUser
    {
        return new GenericUser(['id' => 'USR-'.$role, 'User_ID' => 'USR-'.$role, 'Role_ID' => 'ROLE-'.$role, 'Role' => $role, 'Username' => strtolower($role)]);
    }

    private function mockRole(string $role): void
    {
        $service = Mockery::mock(RoleService::class);
        $service->shouldReceive('getRoleById')->with('ROLE-'.$role)->zeroOrMoreTimes()->andReturn([
            'Role_ID' => 'ROLE-'.$role, 'Role_Name' => $role, 'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $service);
    }
}
