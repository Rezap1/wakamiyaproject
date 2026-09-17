<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\RoleService;
use App\Services\Finance\EducationPaymentMonitoringService;
use App\Services\Finance\InvoiceService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class H862MasterEducationPaymentMonitoringTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_projection_groups_active_students_and_uses_only_canonical_education_finance_data(): void
    {
        $service = $this->service(
            students: [
                $this->student('STU-1', 'Budi', 'CLS-A'),
                $this->student('STU-2', 'Citra', 'CLS-A'),
                $this->student('STU-2', 'Citra Duplikat', 'CLS-A'),
                $this->student('STU-3', 'Dewi', 'CLS-B'),
                $this->student('STU-4', 'Eka', 'CLS-B') + ['Tuition_Fee' => 7_500_000],
                array_merge($this->student('STU-ALUMNI', 'Alumni', 'CLS-A'), ['Enrollment_Status' => 'ALUMNI']),
                array_merge($this->student('STU-INACTIVE', 'Nonaktif', 'CLS-A'), ['Is_Active' => 'FALSE']),
                $this->student('STU-OLD-CLASS', 'Kelas Lama', 'CLS-OLD'),
            ],
            classes: [
                $this->classRow('CLS-A', 'Kelas A'),
                $this->classRow('CLS-B', 'Kelas B'),
                array_merge($this->classRow('CLS-OLD', 'Kelas Lama'), ['Is_Active' => 'FALSE']),
            ],
            invoices: [
                $this->invoice('INV-1', 'STU-1', 1_000),
                $this->invoice('INV-2', 'STU-2', 1_000),
                $this->invoice('INV-3', 'STU-3', 1_000),
                $this->invoice('INV-ALUMNI', 'STU-ALUMNI', 1_000),
                array_merge($this->invoice('INV-DRAFT', 'STU-4', 7_500_000), ['Status' => 'Draft']),
                array_merge($this->invoice('INV-INFO', 'STU-4', 500), ['Category' => 'Seragam', 'Description' => 'Seragam']),
            ],
            payments: [
                $this->payment('PAY-VERIFIED', 'INV-2', 'STU-2', 200, 'Verified'),
                $this->payment('PAY-PENDING', 'INV-2', 'STU-2', 300, 'Waiting Verification'),
                $this->payment('PAY-REJECTED', 'INV-2', 'STU-2', 100, 'Rejected'),
                $this->payment('PAY-FULL', 'INV-3', 'STU-3', 1_000, 'Verified'),
                array_merge($this->payment('PAY-SELF', '', 'STU-2', 900, 'Verified'), ['Payment_Type' => 'STUDENT_SELF_SERVICE']),
            ],
        );

        $result = $service->build();
        $rows = $result['groups']->flatMap(fn ($group) => $group['students'])->keyBy('student_id');

        $this->assertSame(['CLS-A', 'CLS-B'], $result['groups']->pluck('class_id')->all());
        $this->assertSame(['STU-1', 'STU-2', 'STU-3', 'STU-4'], $rows->keys()->sort()->values()->all());
        $this->assertSame(['unpaid', 1_000.0, 0.0, 1_000.0], $this->financeTuple($rows['STU-1']));
        $this->assertSame(['partial', 1_000.0, 200.0, 800.0], $this->financeTuple($rows['STU-2']));
        $this->assertSame(['paid', 1_000.0, 1_000.0, 0.0], $this->financeTuple($rows['STU-3']));
        $this->assertSame(['no_invoice', 0.0, 0.0, 0.0], $this->financeTuple($rows['STU-4']));
        $this->assertSame([
            'students' => 4,
            'paid_students' => 1,
            'partial_students' => 1,
            'unpaid_students' => 1,
            'no_invoice_students' => 1,
            'total' => 3_000.0,
            'paid' => 1_200.0,
            'remaining' => 1_800.0,
        ], $result['kpi']);
    }

    public function test_search_class_and_status_filters_are_applied_to_the_server_projection(): void
    {
        $students = [
            $this->student('STU-1', 'Budi Santoso', 'CLS-A'),
            $this->student('STU-2', 'Citra Ayu', 'CLS-B'),
        ];
        $classes = [$this->classRow('CLS-A', 'Kelas A'), $this->classRow('CLS-B', 'Kelas B')];
        $invoices = [$this->invoice('INV-1', 'STU-1', 1_000)];

        $search = $this->service($students, $classes, $invoices, [])->build(['search' => 'bUdI']);
        $this->assertSame(['STU-1'], $this->studentIds($search));

        $class = $this->service($students, $classes, $invoices, [])->build(['class_id' => 'CLS-B']);
        $this->assertSame(['STU-2'], $this->studentIds($class));

        $status = $this->service($students, $classes, $invoices, [])->build(['status' => 'no_invoice']);
        $this->assertSame(['STU-2'], $this->studentIds($status));
    }

    public function test_master_and_administrator_can_open_the_page_with_responsive_rupiah_ui(): void
    {
        foreach (['MASTER', 'ADMINISTRATOR'] as $role) {
            $this->bindSnapshots(
                [$this->student('STU-1', 'Budi Santoso', 'CLS-A')],
                [$this->classRow('CLS-A', 'Kelas A')],
                [$this->invoice('INV-1', 'STU-1', 1_000)],
                [],
            );
            $this->actingAsRole($role);

            $response = $this->get(route('finance.education-payments.index'));

            $response->assertOk()
                ->assertSee('Pembayaran Pendidikan Siswa')
                ->assertSee('Budi Santoso')
                ->assertSee('Belum Bayar')
                ->assertSee('Rp 1.000')
                ->assertSee('md:hidden', false)
                ->assertSee('hidden md:block', false);
        }
    }

    public function test_other_roles_are_denied_before_finance_data_is_read(): void
    {
        $this->actingAsRole('FINANCE');
        $this->mock(StudentRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(ClassRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(InvoiceRepositoryInterface::class)->shouldNotReceive('getAll');
        $this->mock(PaymentRepositoryInterface::class)->shouldNotReceive('getAll');

        $this->get(route('finance.education-payments.index'))->assertForbidden();
    }

    public function test_invalid_status_filter_is_rejected_without_reading_finance_data(): void
    {
        $this->withoutMiddleware(RoleMiddleware::class);
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));
        $this->mock(StudentRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(ClassRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(InvoiceRepositoryInterface::class)->shouldNotReceive('getAll');
        $this->mock(PaymentRepositoryInterface::class)->shouldNotReceive('getAll');

        $this->get(route('finance.education-payments.index', ['status' => 'forged']))
            ->assertSessionHasErrors('status');
    }

    private function service(array $students, array $classes, array $invoices, array $payments): EducationPaymentMonitoringService
    {
        [$studentRepository, $classRepository, $invoiceRepository, $paymentRepository, $invoiceService] =
            $this->snapshotDependencies($students, $classes, $invoices, $payments);

        return new EducationPaymentMonitoringService(
            $studentRepository,
            $classRepository,
            $invoiceRepository,
            $paymentRepository,
            $invoiceService,
        );
    }

    private function bindSnapshots(array $students, array $classes, array $invoices, array $payments): void
    {
        [$studentRepository, $classRepository, $invoiceRepository, $paymentRepository, $invoiceService] =
            $this->snapshotDependencies($students, $classes, $invoices, $payments, false);

        $this->app->instance(StudentRepositoryInterface::class, $studentRepository);
        $this->app->instance(ClassRepositoryInterface::class, $classRepository);
        $this->app->instance(InvoiceRepositoryInterface::class, $invoiceRepository);
        $this->app->instance(PaymentRepositoryInterface::class, $paymentRepository);
        $this->app->instance(InvoiceService::class, $invoiceService);
    }

    private function snapshotDependencies(
        array $students,
        array $classes,
        array $invoices,
        array $payments,
        bool $strictReads = true,
    ): array
    {
        $readExpectation = $strictReads ? 'once' : 'zeroOrMoreTimes';
        $studentRepository = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepository->shouldReceive('fetchAll')->{$readExpectation}()->andReturn(collect($students));
        $classRepository = Mockery::mock(ClassRepositoryInterface::class);
        $classRepository->shouldReceive('fetchAll')->{$readExpectation}()->andReturn(collect($classes));
        $invoiceRepository = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepository->shouldReceive('getAll')->{$readExpectation}()->andReturn(collect($invoices));
        $paymentRepository = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepository->shouldReceive('getAll')->{$readExpectation}()->andReturn(collect($payments));
        $invoiceService = new InvoiceService(
            $invoiceRepository,
            Mockery::mock(EnterpriseEventService::class),
            $studentRepository,
            Mockery::mock(CompanyRepositoryInterface::class),
            $paymentRepository,
        );

        return [$studentRepository, $classRepository, $invoiceRepository, $paymentRepository, $invoiceService];
    }

    private function actingAsRole(string $role): void
    {
        $roleId = 'ROLE-' . $role;
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->zeroOrMoreTimes()->with($roleId)->andReturn([
            'Role_ID' => $roleId,
            'Role_Name' => $role,
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roleService);
        $this->actingAs(new GenericUser([
            'id' => 'USR-' . $role,
            'User_ID' => 'USR-' . $role,
            'Role_ID' => $roleId,
            'Role' => $role,
            'Full_Name' => $role,
        ]));
    }

    private function student(string $id, string $name, string $classId): array
    {
        return [
            'Student_ID' => $id,
            'Student_Number' => 'NIS-' . $id,
            'Full_Name' => $name,
            'Class_ID' => $classId,
            'Enrollment_Status' => 'ACTIVE',
            'Is_Active' => 'TRUE',
        ];
    }

    private function classRow(string $id, string $name): array
    {
        return ['Class_ID' => $id, 'Class_Name' => $name, 'Is_Active' => 'TRUE'];
    }

    private function invoice(string $id, string $studentId, float $amount): array
    {
        return [
            'Invoice_ID' => $id,
            'Student_ID' => $studentId,
            'Invoice_Type' => 'STUDENT',
            'Category' => 'Biaya Pendidikan',
            'Description' => 'Biaya Pendidikan',
            'Amount' => $amount,
            'Status' => 'Waiting Payment',
            'Due_Date' => now()->addYear()->toDateString(),
            'Is_Active' => 'TRUE',
        ];
    }

    private function payment(string $id, string $invoiceId, string $studentId, float $amount, string $status): array
    {
        return [
            'Payment_ID' => $id,
            'Invoice_ID' => $invoiceId,
            'Student_ID' => $studentId,
            'Amount_Paid' => $amount,
            'Status' => $status,
            'Is_Active' => 'TRUE',
        ];
    }

    private function financeTuple(array $row): array
    {
        return [$row['status'], $row['total'], $row['paid'], $row['remaining']];
    }

    private function studentIds(array $result): array
    {
        return $result['groups']
            ->flatMap(fn ($group) => $group['students'])
            ->pluck('student_id')
            ->all();
    }
}
