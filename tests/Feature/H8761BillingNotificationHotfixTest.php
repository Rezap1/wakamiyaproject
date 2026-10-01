<?php

namespace Tests\Feature;

use App\Http\Controllers\Core\NotificationController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Models\User;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\NotificationRetentionService;
use App\Services\Core\NotificationService;
use App\Services\Dashboard\StudentDashboardService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\StudentBillingNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class H8761BillingNotificationHotfixTest extends TestCase
{
    public function test_one_notify_action_persists_exactly_one_canonical_student_notification(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 15:52:00', 'Asia/Jakarta'));
        $user = new User;
        $user->User_ID = 'USR-MASTER-1';
        $user->Role = 'ADMINISTRATOR';
        $this->actingAs($user);

        $invoice = $this->invoice();
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->with('INV-STU-2026-000004')->andReturn($invoice);
        $studentRepository = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepository->shouldReceive('findById')->once()->with('STD-1')->andReturn($this->student());
        $repository = new H8761NotificationRepository;
        $billing = new StudentBillingNotificationService(
            $invoiceService,
            new NotificationService($repository),
            $studentRepository,
        );

        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->never();
        $events->shouldReceive('dispatchAudit')->once()->andReturnTrue();
        $this->app->instance(EnterpriseEventService::class, $events);

        $response = (new InvoiceController($invoiceService, $billing))->notify(
            Request::create('/finance/invoices/INV-STU-2026-000004/notify', 'POST'),
            'INV-STU-2026-000004',
        );

        $this->assertSame(route('invoices.index'), $response->getTargetUrl());
        $this->assertCount(1, $repository->rows);
        $this->assertSame('Tagihan Biaya Pendidikan', $repository->rows[0]['Title']);
        $this->assertStringContainsString('notification_type=FINANCE_INVOICE_REMINDER', $repository->rows[0]['Link']);
        $this->assertNotSame('TAGIHAN DIKIRIMKAN', $repository->rows[0]['Title']);
    }

    public function test_rapid_repeat_is_not_permanently_blocked_but_is_deduplicated_during_cooldown(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 15:52:00', 'Asia/Jakarta'));
        $user = new User;
        $user->User_ID = 'USR-MASTER-1';
        $user->Role = 'ADMINISTRATOR';
        $this->actingAs($user);
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->times(3)->andReturn($this->invoice());
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('findById')->times(3)->andReturn($this->student());
        $repository = new H8761NotificationRepository;
        $service = new StudentBillingNotificationService($invoiceService, new NotificationService($repository), $students);

        $this->assertTrue($service->sendReminder('INV-STU-2026-000004')['created']);
        $this->assertTrue($service->sendReminder('INV-STU-2026-000004')['duplicate']);
        $this->assertCount(1, $repository->rows);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 15:54:01', 'Asia/Jakarta'));
        $this->assertTrue($service->sendReminder('INV-STU-2026-000004')['created']);
        $this->assertCount(2, $repository->rows);
    }

    public function test_dashboard_billing_dependency_is_required_so_container_cannot_inject_null(): void
    {
        $constructor = new \ReflectionMethod(StudentDashboardService::class, '__construct');
        $parameter = collect($constructor->getParameters())->firstWhere('name', 'billingNotificationService');

        $this->assertNotNull($parameter);
        $this->assertFalse($parameter->isOptional());
        $this->assertFalse($parameter->allowsNull());
    }

    public function test_global_retention_uses_strict_cutoff_skips_malformed_and_is_idempotent(): void
    {
        $repository = new H8761NotificationRepository([
            $this->notification('N-29', '2026-09-02 12:00:00', 'TRUE'),
            $this->notification('N-CUTOFF', '2026-09-01 12:00:00', 'FALSE'),
            $this->notification('N-OLD-READ', '2026-09-01 11:59:59', 'TRUE'),
            $this->notification('N-OLD-UNREAD', '2026-08-01 00:00:00', 'FALSE'),
            $this->notification('N-BAD', 'not-a-date', 'FALSE'),
            $this->notification('N-EMPTY', '', 'FALSE'),
        ]);
        $service = new NotificationRetentionService($repository);
        $now = CarbonImmutable::parse('2026-10-01 12:00:00', 'Asia/Jakarta');

        $first = $service->prune($now);
        $second = $service->prune($now);

        $this->assertSame('2026-09-01 12:00:00', $first['cutoff']);
        $this->assertSame(2, $first['deleted']);
        $this->assertSame(2, $first['malformed_skipped']);
        $this->assertSame(0, $second['deleted']);
        $this->assertSame(['N-29', 'N-CUTOFF', 'N-BAD', 'N-EMPTY'], array_column($repository->rows, 'Notification_ID'));
    }

    public function test_student_navigation_has_five_equal_tabs_and_scan_is_an_independent_same_route_fab(): void
    {
        $nav = file_get_contents(resource_path('views/components/mobile-bottom-nav.blade.php'));
        $start = strpos($nav, "if (\$role === 'STUDENT')");
        $end = strpos($nav, "elseif (\$role === 'TEACHER')");
        $studentBlock = substr($nav, $start, $end - $start);
        $fab = file_get_contents(resource_path('views/components/floating-qr-button.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertSame(5, substr_count($studentBlock, "['label' =>"));
        $this->assertStringNotContainsString('barcode-scan', $studentBlock);
        $this->assertStringContainsString("route('attendances.student.scanner')", $fab);
        $this->assertStringContainsString('left-1/2', $fab);
        $this->assertStringContainsString('-translate-x-1/2', $fab);
        $this->assertStringContainsString('lg:hidden', $fab);
        $this->assertStringContainsString("\$role === 'STUDENT' ? 'lg:hidden' : 'md:hidden'", $nav);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $fab);
        $this->assertStringContainsString('.wms-student-shell .wms-page-content', $css);
        $this->assertStringContainsString('10.5rem + env(safe-area-inset-bottom', $css);
    }

    public function test_billing_views_use_structured_live_context_and_hide_internal_enum(): void
    {
        $index = file_get_contents(resource_path('views/notifications/index.blade.php'));
        $detail = file_get_contents(resource_path('views/notifications/show.blade.php'));
        $dashboard = file_get_contents(resource_path('views/dashboard/student.blade.php'));

        $this->assertStringContainsString('$billingContexts', $index);
        $this->assertStringContainsString('Sisa Pembayaran', $index);
        $this->assertStringContainsString('Tagihan telah lunas', $index);
        $this->assertStringContainsString('Tagihan dibatalkan', $index);
        $this->assertStringNotContainsString("billingContext['type']", $detail);
        $this->assertStringContainsString('if (!response.ok)', $dashboard);
        $this->assertStringContainsString('z-[70]', $dashboard);
    }

    public function test_notification_center_batch_enrichment_uses_current_invoice_state_without_n_plus_one(): void
    {
        $user = new User;
        $user->User_ID = 'USR-STUDENT-1';
        $user->Role = 'STUDENT';
        $this->actingAs($user);

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getAll')->once()->andReturn(collect([
            array_merge($this->invoice(), ['Paid_Amount' => 5500000, 'Remaining_Amount' => 2000000]),
        ]));
        $invoiceService->shouldNotReceive('getById');
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->once()->andReturn(collect([$this->student()]));
        $service = new StudentBillingNotificationService(
            $invoiceService,
            Mockery::mock(NotificationService::class),
            $students,
        );
        $notification = [
            'Notification_ID' => 'N-LIVE',
            'User_ID' => 'USR-STUDENT-1',
            'Title' => 'Tagihan Biaya Pendidikan',
            'Message' => 'Snapshot lama Rp5.000.000',
            'Link' => '/student/billing/INV-STU-2026-000004?notification_type=FINANCE_INVOICE_REMINDER',
            'Is_Read' => 'FALSE',
            'Created_At' => '2026-10-01 15:52:00',
        ];

        $contexts = $service->contextsForNotifications([$notification]);

        $this->assertSame(2000000.0, $contexts['N-LIVE']['remaining']);
        $this->assertSame(5500000.0, $contexts['N-LIVE']['paid']);
    }

    public function test_acknowledgement_persists_hides_popup_keeps_row_and_payment_redirect_is_exact(): void
    {
        $user = new User;
        $user->User_ID = 'USR-STUDENT-1';
        $user->Role = 'STUDENT';
        $this->actingAs($user);
        $row = [
            'Notification_ID' => 'N-ACK',
            'User_ID' => 'USR-STUDENT-1',
            'Title' => 'Tagihan Biaya Pendidikan',
            'Message' => 'Snapshot',
            'Link' => '/student/billing/INV-STU-2026-000004?notification_type=FINANCE_INVOICE_REMINDER',
            'Is_Read' => 'FALSE',
            'Created_At' => '2026-10-01 15:52:00',
        ];
        $repository = new H8761NotificationRepository([$row]);
        $notifications = new NotificationService($repository);
        $billing = new StudentBillingNotificationService(
            Mockery::mock(InvoiceService::class),
            $notifications,
            Mockery::mock(StudentRepositoryInterface::class),
        );
        $controller = new NotificationController($notifications, $billing);
        $request = Request::create('/notifications/N-ACK/read', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $controller->markRead($request, 'N-ACK');
        $popup = $billing->popupFromSnapshots($repository->rows, [$this->invoice()], $this->student());
        $redirect = $controller->readAndRedirect('N-ACK');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('TRUE', $repository->rows[0]['Is_Read']);
        $this->assertCount(1, $repository->rows);
        $this->assertNull($popup['current']);
        $this->assertSame(
            url('/student/billing/INV-STU-2026-000004?notification_type=FINANCE_INVOICE_REMINDER'),
            $redirect->getTargetUrl(),
        );
    }

    private function invoice(): array
    {
        return [
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
        ];
    }

    private function student(): array
    {
        return ['Student_ID' => 'STD-1', 'User_ID' => 'USR-STUDENT-1', 'Full_Name' => 'Neng Yuli Amalia'];
    }

    private function notification(string $id, string $createdAt, string $isRead): array
    {
        return [
            'Notification_ID' => $id,
            'User_ID' => 'USR-1',
            'Title' => 'System',
            'Message' => 'Global retention fixture',
            'Is_Read' => $isRead,
            'Link' => '/',
            'Created_At' => $createdAt,
            'Updated_At' => '',
        ];
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Mockery::close();
        parent::tearDown();
    }
}

class H8761NotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(public array $rows = []) {}

    public function getAll()
    {
        return collect($this->rows);
    }

    public function getAllFresh()
    {
        return collect($this->rows);
    }

    public function getById($id)
    {
        return collect($this->rows)->firstWhere('Notification_ID', $id);
    }

    public function create(array $data)
    {
        $this->rows[] = $data;

        return true;
    }

    public function update($id, array $data)
    {
        foreach ($this->rows as $index => $row) {
            if (($row['Notification_ID'] ?? null) === $id) {
                $this->rows[$index] = array_merge($row, $data);

                return true;
            }
        }

        return false;
    }

    public function delete($id)
    {
        return false;
    }

    public function hardDeleteMany(array $ids): int
    {
        $ids = array_unique($ids);
        $before = count($this->rows);
        $this->rows = array_values(array_filter(
            $this->rows,
            fn ($row) => ! in_array($row['Notification_ID'] ?? null, $ids, true),
        ));

        return $before - count($this->rows);
    }

    public function clearCache(): void {}
}
