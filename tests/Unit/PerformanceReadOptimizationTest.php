<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\NotificationService;
use App\Services\Core\RoleService;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PerformanceReadOptimizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_role_service_reuses_role_lookup_within_request(): void
    {
        $repo = Mockery::mock(RoleRepositoryInterface::class);
        $repo->shouldReceive('findById')->once()->with('ROLE-FIN')->andReturn([
            'Role_ID' => 'ROLE-FIN',
            'Role_Name' => 'FINANCE',
            'Is_Active' => 'TRUE',
        ]);

        $service = new RoleService($repo);

        $first = $service->getRoleById('ROLE-FIN');
        $second = $service->getRoleById('ROLE-FIN');

        $this->assertSame('FINANCE', $first['Role_Name']);
        $this->assertSame('FINANCE', $second['Role_Name']);
    }

    public function test_notification_service_reuses_notification_snapshot_for_badge_and_list(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-1', 'User_ID' => 'USR-1', 'Role' => 'STUDENT']));

        $repo = Mockery::mock(NotificationRepositoryInterface::class);
        $repo->shouldReceive('getAll')->once()->andReturn(collect([
            ['Notification_ID' => 'N-1', 'User_ID' => 'USR-1', 'Title' => 'A', 'Message' => 'A', 'Status' => 'Read', 'Is_Read' => 'TRUE', 'Created_At' => now()->subMinute()->toDateTimeString()],
            ['Notification_ID' => 'N-2', 'User_ID' => 'USR-1', 'Title' => 'B', 'Message' => 'B', 'Status' => 'Pending', 'Is_Read' => 'FALSE', 'Created_At' => now()->toDateTimeString()],
        ]));

        $service = new NotificationService($repo);

        $unread = $service->UnreadCount();
        $recent = $service->RecentNotification(null, null, 5);

        $this->assertSame(1, $unread);
        $this->assertCount(2, $recent);
    }

    public function test_invoice_summary_reuses_provided_snapshots_without_fresh_payment_reads(): void
    {
        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('getDefaultTuitionFee')->once()->andReturn(1000);
        $this->app->instance(SystemSettingService::class, $settings);

        $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepo->shouldNotReceive('getAllFresh');

        $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepo->shouldNotReceive('getAllFresh');

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldNotReceive('findById');

        $companyRepo = Mockery::mock(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class);
        $events = Mockery::mock(EnterpriseEventService::class);

        $service = new InvoiceService($invoiceRepo, $events, $studentRepo, $companyRepo, $paymentRepo);

        $summary = $service->getStudentEducationBillingSummary(
            'STU-1',
            null,
            collect([
                ['Invoice_ID' => 'INV-1', 'Student_ID' => 'STU-1', 'Amount' => 600, 'Paid_Amount' => 400, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE', 'Category' => 'Biaya Pendidikan'],
                ['Invoice_ID' => 'INV-2', 'Student_ID' => 'STU-1', 'Amount' => 300, 'Paid_Amount' => 300, 'Status' => 'Paid', 'Is_Active' => 'TRUE', 'Category' => 'Biaya Pendidikan'],
            ]),
            null,
            ['Student_ID' => 'STU-1']
        );

        $this->assertSame(1000.0, $summary['tuition_fee']);
        $this->assertSame(900.0, $summary['education_billed']);
        $this->assertSame(700.0, $summary['education_paid']);
        $this->assertSame(100.0, $summary['remaining_to_bill']);
        $this->assertSame(300.0, $summary['remaining_to_pay']);
    }

    public function test_verified_invoice_and_self_service_payments_share_one_student_summary(): void
    {
        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('getDefaultTuitionFee')->once()->andReturn(1000);
        $this->app->instance(SystemSettingService::class, $settings);

        $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepo->shouldNotReceive('getAllFresh');
        $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepo->shouldNotReceive('getAllFresh');
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldNotReceive('findById');
        $companyRepo = Mockery::mock(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class);
        $service = new InvoiceService($invoiceRepo, Mockery::mock(EnterpriseEventService::class), $studentRepo, $companyRepo, $paymentRepo);

        $summary = $service->getStudentEducationBillingSummary(
            'STU-1',
            null,
            collect([
                ['Invoice_ID' => 'INV-1', 'Student_ID' => 'STU-1', 'Amount' => 1000, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE', 'Category' => 'Biaya Pendidikan'],
            ]),
            collect([
                ['Payment_ID' => 'PAY-LINKED', 'Invoice_ID' => 'INV-1', 'Student_ID' => 'STU-1', 'Amount_Paid' => 200, 'Payment_Type' => 'STUDENT', 'Status' => 'Verified', 'Is_Active' => 'TRUE'],
                ['Payment_ID' => 'PAY-SELF', 'Invoice_ID' => '', 'Student_ID' => 'STU-1', 'Amount_Paid' => 300, 'Payment_Type' => 'STUDENT_SELF_SERVICE', 'Status' => 'Verified', 'Is_Active' => 'TRUE'],
                ['Payment_ID' => 'PAY-WAITING', 'Invoice_ID' => '', 'Student_ID' => 'STU-1', 'Amount_Paid' => 400, 'Payment_Type' => 'STUDENT_SELF_SERVICE', 'Status' => 'Waiting Verification', 'Is_Active' => 'TRUE'],
                ['Payment_ID' => 'PAY-OTHER', 'Invoice_ID' => '', 'Student_ID' => 'STU-2', 'Amount_Paid' => 500, 'Payment_Type' => 'STUDENT_SELF_SERVICE', 'Status' => 'Verified', 'Is_Active' => 'TRUE'],
            ]),
            ['Student_ID' => 'STU-1']
        );

        $this->assertSame(500.0, $summary['education_paid']);
        $this->assertSame(500.0, $summary['remaining_to_pay']);
    }

    public function test_unknown_self_service_status_fails_closed(): void
    {
        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('getDefaultTuitionFee')->once()->andReturn(1000);
        $this->app->instance(SystemSettingService::class, $settings);

        $service = new InvoiceService(
            Mockery::mock(InvoiceRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class),
            Mockery::mock(StudentRepositoryInterface::class),
            Mockery::mock(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class),
            Mockery::mock(PaymentRepositoryInterface::class)
        );

        $this->expectException(\App\Exceptions\FinancialIntegrityException::class);
        $service->getStudentEducationBillingSummary(
            'STU-1',
            null,
            collect([['Invoice_ID' => 'INV-1', 'Student_ID' => 'STU-1', 'Amount' => 1000, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE', 'Category' => 'Biaya Pendidikan']]),
            collect([['Payment_ID' => 'PAY-X', 'Invoice_ID' => '', 'Student_ID' => 'STU-1', 'Amount_Paid' => 1, 'Payment_Type' => 'STUDENT_SELF_SERVICE', 'Status' => 'UNKNOWN', 'Is_Active' => 'TRUE']]),
            ['Student_ID' => 'STU-1']
        );
    }
}
