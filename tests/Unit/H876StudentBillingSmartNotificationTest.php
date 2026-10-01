<?php

namespace Tests\Unit;

use App\Http\Controllers\Core\NotificationController;
use App\Http\Controllers\Finance\StudentBillingController;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Models\User;
use App\Services\Core\NotificationService;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\StudentBillingNotificationService;
use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class H876StudentBillingSmartNotificationTest extends TestCase
{
    public function test_partial_invoice_message_is_canonical_professional_and_invoice_scoped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 14:36:55', 'Asia/Jakarta'));
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('getAllFresh')->once()->andReturn(collect());
        $notificationService->shouldReceive('CreateNotification')->once()->with(Mockery::on(function (array $payload) {
            $message = $payload['Message'] ?? '';

            return ($payload['User_ID'] ?? '') === 'USR-STUDENT-1'
                && ($payload['Title'] ?? '') === 'Tagihan Biaya Pendidikan'
                && ($payload['Link'] ?? '') === '/student/billing/INV-STU-2026-000004?notification_type=FINANCE_INVOICE_REMINDER'
                && str_contains($message, 'Neng Yuli Amalia')
                && str_contains($message, 'Biaya Pendidikan')
                && str_contains($message, 'Rp7.500.000')
                && str_contains($message, 'Rp2.500.000')
                && str_contains($message, 'Rp5.000.000')
                && str_contains($message, '5 Oktober 2026')
                && str_contains($message, 'Silakan melakukan pembayaran angsuran biaya pendidikan')
                && ! str_contains($message, 'USR000001');
        }))->andReturn(true);

        $result = $this->service($notificationService)->sendReminder('INV-STU-2026-000004');

        $this->assertTrue($result['created']);
        $this->assertFalse($result['duplicate']);
    }

    public function test_paid_invoice_cannot_create_payment_reminder(): void
    {
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldNotReceive('CreateNotification');
        $notificationService->shouldNotReceive('getAllFresh');
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->andReturn($this->invoice([
            'Status' => 'Paid',
            'Paid_Amount' => 7500000,
            'Remaining_Amount' => 0,
        ]));

        $service = new StudentBillingNotificationService(
            $invoiceService,
            $notificationService,
            Mockery::mock(StudentRepositoryInterface::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tagihan siswa sudah lunas dan tidak memerlukan notifikasi pembayaran.');
        $service->sendReminder('INV-STU-2026-000004');
    }

    public function test_cancelled_invoice_cannot_create_payment_reminder(): void
    {
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldNotReceive('CreateNotification');
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->andReturn($this->invoice([
            'Status' => 'Cancelled',
        ]));
        $service = new StudentBillingNotificationService(
            $invoiceService,
            $notificationService,
            Mockery::mock(StudentRepositoryInterface::class),
        );

        $this->expectException(\DomainException::class);
        $service->sendReminder('INV-STU-2026-000004');
    }

    public function test_void_invoice_cannot_create_payment_reminder(): void
    {
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldNotReceive('CreateNotification');
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->andReturn($this->invoice(['Status' => 'Void']));
        $service = new StudentBillingNotificationService(
            $invoiceService,
            $notificationService,
            Mockery::mock(StudentRepositoryInterface::class),
        );

        $this->expectException(\DomainException::class);
        $service->sendReminder('INV-STU-2026-000004');
    }

    public function test_unpaid_invoice_copy_asks_for_payment_not_installment(): void
    {
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('getAllFresh')->once()->andReturn(collect());
        $notificationService->shouldReceive('CreateNotification')->once()->with(Mockery::on(function (array $payload) {
            return str_contains($payload['Message'], 'Silakan melakukan pembayaran biaya pendidikan')
                && ! str_contains($payload['Message'], 'pembayaran angsuran');
        }))->andReturn(true);
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->andReturn($this->invoice([
            'Status' => 'Waiting Payment',
            'Paid_Amount' => 0,
            'Remaining_Amount' => 7500000,
        ]));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('findById')->once()->andReturn($this->student());
        $service = new StudentBillingNotificationService($invoiceService, $notificationService, $studentRepo);

        $this->assertTrue($service->sendReminder('INV-STU-2026-000004')['created']);
    }

    public function test_recent_same_invoice_reminder_is_not_created_twice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 14:36:55', 'Asia/Jakarta'));
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('getAllFresh')->once()->andReturn(collect([[
            'User_ID' => 'USR-STUDENT-1',
            'Title' => 'Tagihan Biaya Pendidikan',
            'Link' => '/student/billing/INV-STU-2026-000004?notification_type=FINANCE_INVOICE_REMINDER',
            'Created_At' => '2026-10-01 14:36:00',
        ]]));
        $notificationService->shouldNotReceive('CreateNotification');

        $result = $this->service($notificationService)->sendReminder('INV-STU-2026-000004');

        $this->assertTrue($result['duplicate']);
        $this->assertFalse($result['created']);
    }

    public function test_notification_detail_refreshes_live_remaining_balance_and_paid_state(): void
    {
        $user = new User;
        $user->User_ID = 'USR-STUDENT-1';
        $user->Role = 'STUDENT';
        $this->actingAs($user);

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->with('INV-STU-2026-000004')->andReturn($this->invoice([
            'Paid_Amount' => 4500000,
            'Remaining_Amount' => 3000000,
        ]));
        $notificationService = Mockery::mock(NotificationService::class);
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([$this->student()]));
        $service = new StudentBillingNotificationService($invoiceService, $notificationService, $studentRepo);

        $context = $service->contextForNotification($this->notification());

        $this->assertSame(3000000.0, $context['remaining']);
        $this->assertSame(4500000.0, $context['paid']);
        $this->assertTrue($context['actionable']);
    }

    public function test_popup_uses_newest_actionable_notification_without_stacking(): void
    {
        $user = new User;
        $user->User_ID = 'USR-STUDENT-1';
        $user->Role = 'STUDENT';
        $user->resolved_student_id = 'STD-1';
        $user->resolved_employee_id = '';
        $this->actingAs($user);

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('isForUser')->twice()->andReturn(true);
        $service = new StudentBillingNotificationService(
            Mockery::mock(InvoiceService::class),
            $notificationService,
            Mockery::mock(StudentRepositoryInterface::class),
        );
        $older = $this->notification(['Notification_ID' => 'N-OLD', 'Created_At' => '2026-10-01 09:00:00']);
        $newer = $this->notification(['Notification_ID' => 'N-NEW', 'Link' => '/student/billing/INV-2?notification_type=FINANCE_INVOICE_REMINDER', 'Created_At' => '2026-10-01 10:00:00']);
        $invoices = [
            $this->invoice(),
            $this->invoice(['Invoice_ID' => 'INV-2', 'Status' => 'Partial Paid']),
        ];

        $popup = $service->popupFromSnapshots([$older, $newer], $invoices, $this->student());

        $this->assertSame('N-NEW', $popup['current']['notification']['Notification_ID']);
        $this->assertSame(1, $popup['other_count']);
    }

    public function test_paid_invoice_notification_is_suppressed_from_payment_popup(): void
    {
        $user = new User;
        $user->User_ID = 'USR-STUDENT-1';
        $user->Role = 'STUDENT';
        $user->resolved_student_id = 'STD-1';
        $user->resolved_employee_id = '';
        $this->actingAs($user);
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('isForUser')->once()->andReturn(true);
        $service = new StudentBillingNotificationService(
            Mockery::mock(InvoiceService::class),
            $notificationService,
            Mockery::mock(StudentRepositoryInterface::class),
        );

        $popup = $service->popupFromSnapshots(
            [$this->notification()],
            [$this->invoice(['Status' => 'Paid', 'Paid_Amount' => 7500000, 'Remaining_Amount' => 0])],
            $this->student(),
        );

        $this->assertNull($popup['current']);
        $this->assertSame(0, $popup['other_count']);
    }

    public function test_popup_and_detail_mobile_contract_uses_persistent_read_endpoint(): void
    {
        $dashboard = file_get_contents(resource_path('views/dashboard/student.blade.php'));
        $detail = file_get_contents(resource_path('views/notifications/show.blade.php'));

        $this->assertStringContainsString("route('notifications.markRead'", $dashboard);
        $this->assertStringContainsString('Bayar Sekarang', $dashboard);
        $this->assertStringContainsString('Nanti', $dashboard);
        $this->assertStringContainsString('max-w-md', $dashboard);
        $this->assertStringContainsString('max-h-[calc(100dvh-7rem)]', $dashboard);
        $this->assertStringNotContainsString('localStorage', $dashboard);
        $this->assertStringContainsString('Rp{{ number_format($billingContext', $detail);
        $this->assertStringContainsString('break-words', $detail);
    }

    public function test_forged_notification_id_returns_403(): void
    {
        $user = new User;
        $user->User_ID = 'USR-STUDENT-B';
        $user->Role = 'STUDENT';
        $this->actingAs($user);
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('getById')->once()->with('N-A')->andReturn([
            'Notification_ID' => 'N-A',
            'User_ID' => 'USR-STUDENT-A',
        ]);
        $notificationService->shouldReceive('isForUser')->once()->andReturn(false);
        $controller = new NotificationController($notificationService, Mockery::mock(StudentBillingNotificationService::class));

        try {
            $controller->show('N-A');
            $this->fail('Forged notification was accessible.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_forged_invoice_id_returns_403_from_existing_payment_flow(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STUDENT-B', 'User_ID' => 'USR-STUDENT-B', 'Role' => 'STUDENT']));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STD-B', 'User_ID' => 'USR-STUDENT-B'],
        ]));
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->with('INV-STUDENT-A')->andReturn([
            'Invoice_ID' => 'INV-STUDENT-A', 'Student_ID' => 'STD-A', 'Status' => 'Waiting Payment',
        ]);
        $controller = new StudentBillingController(
            $invoiceService,
            Mockery::mock(PaymentService::class),
            Mockery::mock(SystemSettingService::class),
            $studentRepo,
            Mockery::mock(ProgramRepositoryInterface::class),
            Mockery::mock(BatchRepositoryInterface::class),
        );

        try {
            $controller->show('INV-STUDENT-A');
            $this->fail('Forged invoice was accessible.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function service(NotificationService $notificationService): StudentBillingNotificationService
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->with('INV-STU-2026-000004')->andReturn($this->invoice());
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('findById')->once()->with('STD-1')->andReturn($this->student());

        return new StudentBillingNotificationService($invoiceService, $notificationService, $studentRepo);
    }

    private function invoice(array $overrides = []): array
    {
        return array_merge([
            'Invoice_ID' => 'INV-STU-2026-000004',
            'Invoice_Type' => 'STUDENT',
            'Student_ID' => 'STD-1',
            'Category' => 'Biaya Pendidikan',
            'Grand_Total' => 7500000,
            'Amount' => 7500000,
            'Paid_Amount' => 2500000,
            'Remaining_Amount' => 5000000,
            'Due_Date' => '2026-10-05',
            'Status' => 'Partial Paid',
        ], $overrides);
    }

    private function student(): array
    {
        return ['Student_ID' => 'STD-1', 'User_ID' => 'USR-STUDENT-1', 'Full_Name' => 'Neng Yuli Amalia'];
    }

    private function notification(array $overrides = []): array
    {
        return array_merge([
            'Notification_ID' => 'N-1',
            'User_ID' => 'USR-STUDENT-1',
            'Title' => 'Tagihan Biaya Pendidikan',
            'Message' => 'Snapshot lama Rp5.000.000',
            'Link' => '/student/billing/INV-STU-2026-000004?notification_type=FINANCE_INVOICE_REMINDER',
            'Is_Read' => 'FALSE',
            'Created_At' => '2026-10-01 09:00:00',
        ], $overrides);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }
}
