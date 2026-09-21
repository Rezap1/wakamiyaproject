<?php

namespace Tests\Feature;

require_once __DIR__.'/H864EducationFeeFullPaymentGuardTest.php';

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Services\Academic\AnnouncementService;
use App\Services\Academic\AttendanceService as AcademicAttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\AssignmentService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\NotificationService;
use App\Services\Core\StudentService;
use App\Services\Core\SystemSettingService;
use App\Services\Dashboard\StudentDashboardService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class H867StudentDashboardEducationSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('getDefaultTuitionFee')->zeroOrMoreTimes()->andReturn(7_500_000.0);
        $this->app->instance(SystemSettingService::class, $settings);

        foreach ([ProgramRepositoryInterface::class, BatchRepositoryInterface::class] as $interface) {
            $repository = Mockery::mock($interface);
            $repository->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(null);
            $this->app->instance($interface, $repository);
        }

        $assignmentService = Mockery::mock(AssignmentService::class);
        $assignmentService->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());
        $this->app->instance(AssignmentService::class, $assignmentService);

        $this->actingAs(new GenericUser([
            'id' => 'USR-STU-1',
            'User_ID' => 'USR-STU-1',
            'Role' => 'STUDENT',
            'Full_Name' => 'Student Dashboard',
        ]));
    }

    public function test_no_invoice_and_no_payment_still_show_canonical_fee_and_remaining(): void
    {
        $data = $this->dashboardData();

        $this->assertEducationSummary($data, 7_500_000, 0, 7_500_000, 'BELUM BAYAR');
    }

    public function test_verified_bayar_mandiri_without_invoice_is_counted(): void
    {
        $data = $this->dashboardData([
            $this->payment('PAY-VERIFIED-2M', 2_000_000, 'Verified'),
        ]);

        $this->assertEducationSummary($data, 7_500_000, 2_000_000, 5_500_000, 'CICILAN');
    }

    public function test_pending_payment_does_not_increase_dashboard_paid_or_reduce_verified_remaining(): void
    {
        $data = $this->dashboardData([
            $this->payment('PAY-VERIFIED-2M', 2_000_000, 'Verified'),
            $this->payment('PAY-PENDING-1M', 1_000_000, 'Waiting Verification'),
        ]);

        $this->assertEducationSummary($data, 7_500_000, 2_000_000, 5_500_000, 'CICILAN');
    }

    public function test_exact_verified_education_fee_is_lunas(): void
    {
        $data = $this->dashboardData([
            $this->payment('PAY-VERIFIED-FULL', 7_500_000, 'Verified'),
        ]);

        $this->assertEducationSummary($data, 7_500_000, 7_500_000, 0, 'LUNAS');
    }

    public function test_verified_non_education_invoice_payment_is_excluded(): void
    {
        $data = $this->dashboardData(
            [
                $this->payment('PAY-EDUCATION', 2_000_000, 'Verified'),
                $this->payment('PAY-MEDICAL', 1_000_000, 'Verified', 'INV-MEDICAL', 'STUDENT'),
            ],
            [[
                'Invoice_ID' => 'INV-MEDICAL',
                'Invoice_Type' => 'STUDENT',
                'Student_ID' => 'STU-1',
                'Category' => 'Medical',
                'Amount' => 1_000_000,
                'Status' => 'Paid',
                'Is_Active' => 'TRUE',
            ]],
        );

        $this->assertEducationSummary($data, 7_500_000, 2_000_000, 5_500_000, 'CICILAN');
    }

    public function test_ledger_mirror_is_not_a_dashboard_payment_source(): void
    {
        $data = $this->dashboardData([
            $this->payment('PAY-VERIFIED-2M', 2_000_000, 'Verified'),
        ]);
        $source = file_get_contents(app_path('Services/Dashboard/StudentDashboardService.php'));

        $this->assertSame(2_000_000.0, $data['kpi']['sudah_dibayar']);
        $this->assertStringNotContainsString('TransactionService', $source);
        $this->assertStringNotContainsString('FINANCE_TRANSACTION', $source);
    }

    public function test_dashboard_render_contains_all_three_canonical_education_values(): void
    {
        $data = $this->dashboardData([
            $this->payment('PAY-VERIFIED-2M', 2_000_000, 'Verified'),
        ]);
        $html = view('dashboard.student', $data)->render();

        $this->assertStringContainsString('Biaya Pendidikan', $html);
        $this->assertStringContainsString('Sudah Dibayar', $html);
        $this->assertStringContainsString('Sisa Biaya Pendidikan', $html);
        $this->assertStringContainsString('Rp 7.500.000', $html);
        $this->assertStringContainsString('Rp 2.000.000', $html);
        $this->assertStringContainsString('Rp 5.500.000', $html);
    }

    public function test_unmapped_authenticated_student_fails_closed(): void
    {
        try {
            $this->dashboardData([], [], collect());
            $this->fail('Unmapped student dashboard must fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_master_invoice_kpi_uses_active_student_invoice_remaining_and_excludes_bayar_mandiri(): void
    {
        $data = $this->dashboardData(
            [$this->payment('PAY-SELF-SERVICE', 2_000_000, 'Verified')],
            [[
                'Invoice_ID' => 'INV-MEDICAL',
                'Invoice_Type' => 'STUDENT',
                'Student_ID' => 'STU-1',
                'Category' => 'Medical',
                'Amount' => 500_000,
                'Status' => 'Waiting Payment',
                'Is_Active' => 'TRUE',
            ]],
        );

        $this->assertSame(500_000.0, $data['kpi']['tagihan_master']);
        $this->assertSame(2_000_000.0, $data['kpi']['sudah_dibayar']);
        $this->assertSame(5_500_000.0, $data['kpi']['sisa_biaya_pendidikan']);
    }

    public function test_master_invoice_kpi_is_zero_without_own_active_invoice_and_excludes_other_student(): void
    {
        $data = $this->dashboardData([], [[
            'Invoice_ID' => 'INV-OTHER-STUDENT',
            'Invoice_Type' => 'STUDENT',
            'Student_ID' => 'STU-OTHER',
            'Category' => 'Medical',
            'Amount' => 900_000,
            'Status' => 'Waiting Payment',
            'Is_Active' => 'TRUE',
        ]]);

        $this->assertSame(0.0, $data['kpi']['tagihan_master']);
    }

    private function dashboardData(array $payments = [], array $invoices = [], ?iterable $students = null): array
    {
        $paymentRepository = new H864PaymentRepository($payments);
        $invoiceRepository = new H864InvoiceRepository($invoices);
        $studentRepository = new H864StudentRepository;
        $events = Mockery::mock(EnterpriseEventService::class)->shouldIgnoreMissing();
        $invoiceService = new InvoiceService(
            $invoiceRepository,
            $events,
            $studentRepository,
            new H864CompanyRepository,
            $paymentRepository,
        );

        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect($payments));
        $studentService = Mockery::mock(StudentService::class);
        $studentService->shouldReceive('getAllStudents')->once()->andReturn($students ?? collect([[
            'Student_ID' => 'STU-1',
            'User_ID' => 'USR-STU-1',
            'Full_Name' => 'Student Dashboard',
            'Class_ID' => 'CLS-1',
            'Program_ID' => '',
            'Batch_ID' => '',
        ]]));

        $announcementService = Mockery::mock(AnnouncementService::class);
        $announcementService->shouldReceive('getActiveAnnouncements')->zeroOrMoreTimes()->andReturn(collect());
        $attendanceRequests = Mockery::mock(AttendanceRequestService::class);
        $attendanceRequests->shouldReceive('getStudentRequests')->zeroOrMoreTimes()->andReturn(collect());
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('UnreadCount')->zeroOrMoreTimes()->andReturn(0);
        $activityLogs = Mockery::mock(ActivityLogService::class);
        $activityLogs->shouldReceive('getAllLogs')->zeroOrMoreTimes()->andReturn(collect());

        $service = new StudentDashboardService(
            $this->emptyGetAllMock(ScoreService::class),
            $this->emptyGetAllMock(ScheduleService::class),
            $this->emptyGetAllMock(AcademicAttendanceService::class),
            $invoiceService,
            $paymentService,
            $studentService,
            $activityLogs,
            $notifications,
            $attendanceRequests,
            $announcementService,
        );

        return $service->getDashboardData();
    }

    private function emptyGetAllMock(string $class)
    {
        $service = Mockery::mock($class);
        $service->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());

        return $service;
    }

    private function assertEducationSummary(array $data, float $fee, float $paid, float $remaining, string $status): void
    {
        $this->assertSame($fee, $data['kpi']['biaya_pendidikan']);
        $this->assertSame($paid, $data['kpi']['sudah_dibayar']);
        $this->assertSame($remaining, $data['kpi']['sisa_biaya_pendidikan']);
        $this->assertSame($status, $data['kpi']['status_biaya_pendidikan']);
    }

    private function payment(
        string $id,
        float $amount,
        string $status,
        string $invoiceId = '',
        string $type = 'STUDENT_SELF_SERVICE',
    ): array {
        return [
            'Payment_ID' => $id,
            'Student_ID' => 'STU-1',
            'Invoice_ID' => $invoiceId,
            'Payment_Type' => $type,
            'Amount_Paid' => $amount,
            'Payment_Method' => 'TRANSFER',
            'Payment_Date' => '2026-09-21',
            'Status' => $status,
            'Is_Active' => 'TRUE',
        ];
    }
}
