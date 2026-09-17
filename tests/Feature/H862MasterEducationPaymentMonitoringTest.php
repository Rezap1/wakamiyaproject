<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\RoleService;
use App\Services\Core\SystemSettingService;
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

    public function test_syahwal_regression_counts_verified_invoice_less_self_service_payments(): void
    {
        $service = $this->service(
            students: [$this->student('STD000003', 'SYAHWAL DJIKRIYA HIMAWAN', 'CLS-D')],
            classes: [$this->classRow('CLS-D', 'Kelas D')],
            payments: [
                $this->payment('RCT-STU-2026-000001', '', 'STD000003', 500_000, 'Verified'),
                $this->payment('RCT-STU-2026-000003', '', 'STD000003', 2_000_000, 'Verified'),
            ],
            defaultFee: 7_500_000,
        );

        $detail = $service->detail('STD000003');
        $this->assertNotNull($detail);
        $row = $detail['student'];

        $this->assertSame(7_500_000.0, $row['education_fee']);
        $this->assertSame(2_500_000.0, $row['paid']);
        $this->assertSame(5_000_000.0, $row['remaining']);
        $this->assertSame('partial', $row['status']);

        $this->assertSame('Cicilan', $detail['student']['status_label']);
        $this->assertSame(
            ['Bayar Mandiri', 'Bayar Mandiri'],
            $detail['history']->pluck('source_label')->all(),
        );
        $this->assertSame([null, null], $detail['history']->pluck('invoice_id')->all());
    }

    public function test_pending_and_rejected_self_service_payments_are_visible_but_not_counted(): void
    {
        $service = $this->service(
            students: [$this->student('STU-1', 'Budi', 'CLS-A')],
            classes: [$this->classRow('CLS-A', 'Kelas A')],
            payments: [
                $this->payment('PAY-VERIFIED', '', 'STU-1', 500, 'Verified'),
                $this->payment('PAY-PENDING', '', 'STU-1', 700, 'Waiting Verification'),
                $this->payment('PAY-REJECTED', '', 'STU-1', 900, 'Rejected'),
            ],
            defaultFee: 2_000,
        );

        $detail = $service->detail('STU-1');

        $this->assertSame(500.0, $detail['student']['paid']);
        $this->assertSame(1_500.0, $detail['student']['remaining']);
        $this->assertSame(
            ['PAY-PENDING', 'PAY-REJECTED', 'PAY-VERIFIED'],
            $detail['history']->pluck('payment_id')->sort()->values()->all(),
        );
    }

    public function test_only_verified_payments_for_official_education_invoices_are_counted(): void
    {
        $service = $this->service(
            students: [$this->student('STU-1', 'Budi', 'CLS-A')],
            classes: [$this->classRow('CLS-A', 'Kelas A')],
            invoices: [
                $this->invoice('INV-EDU', 'STU-1', 1_500, 'Biaya Pendidikan', 'Paid'),
                $this->invoice('INV-MED', 'STU-1', 400, 'Medical', 'Paid'),
                $this->invoice('INV-DRAFT', 'STU-1', 600, 'Biaya Pendidikan', 'Draft'),
                array_merge(
                    $this->invoice('INV-INACTIVE', 'STU-1', 700, 'Biaya Pendidikan', 'Paid'),
                    ['Is_Active' => 'FALSE'],
                ),
            ],
            payments: [
                $this->payment('PAY-EDU', 'INV-EDU', 'STU-1', 1_500, 'Verified'),
                $this->payment('PAY-MED', 'INV-MED', 'STU-1', 400, 'Verified'),
                $this->payment('PAY-DRAFT', 'INV-DRAFT', 'STU-1', 600, 'Verified'),
                $this->payment('PAY-INACTIVE', 'INV-INACTIVE', 'STU-1', 700, 'Verified'),
            ],
            defaultFee: 7_500,
        );

        $detail = $service->detail('STU-1');

        $this->assertSame(1_500.0, $detail['student']['paid']);
        $this->assertSame(['PAY-EDU'], $detail['history']->pluck('payment_id')->all());
        $this->assertSame('Tagihan dari Master', $detail['history']->first()['source_label']);
    }

    public function test_paid_invoice_does_not_make_overall_education_status_paid(): void
    {
        $service = $this->service(
            students: [$this->student('STU-1', 'Budi', 'CLS-A')],
            classes: [$this->classRow('CLS-A', 'Kelas A')],
            invoices: [$this->invoice('INV-EDU', 'STU-1', 2_500, 'Biaya Pendidikan', 'Paid')],
            payments: [$this->payment('PAY-EDU', 'INV-EDU', 'STU-1', 2_500, 'Verified')],
            defaultFee: 7_500,
        );

        $detail = $service->detail('STU-1');

        $this->assertSame('partial', $detail['student']['status']);
        $this->assertSame('Cicilan', $detail['student']['status_label']);
        $this->assertSame('Lunas', $detail['history']->first()['invoice_status_label']);
    }

    public function test_mixed_payment_sources_are_counted_once_and_non_education_is_excluded(): void
    {
        $student = $this->student('STU-1', 'Budi', 'CLS-A');
        $service = $this->service(
            students: [$student],
            classes: [$this->classRow('CLS-A', 'Kelas A')],
            invoices: [
                $this->invoice('INV-EDU', 'STU-1', 1_500_000, 'Biaya Pendidikan', 'Paid'),
                $this->invoice('INV-MED', 'STU-1', 400_000, 'Medical', 'Paid'),
            ],
            payments: [
                $this->payment('PAY-MASTER', 'INV-EDU', 'STU-1', 1_500_000, 'Verified', 'TRANSFER'),
                $this->payment('PAY-MASTER', 'INV-EDU', 'STU-1', 1_500_000, 'Verified', 'TRANSFER'),
                $this->payment('PAY-SELF', '', 'STU-1', 1_000_000, 'Verified', 'CASH'),
                $this->payment('PAY-PENDING', '', 'STU-1', 2_000_000, 'Waiting Verification', 'TRANSFER'),
                $this->payment('PAY-REJECTED', '', 'STU-1', 3_000_000, 'Rejected', 'QRIS'),
                $this->payment('PAY-MEDICAL', 'INV-MED', 'STU-1', 400_000, 'Verified', 'TRANSFER'),
            ],
            defaultFee: 7_500_000,
        );

        $detail = $service->detail('STU-1');
        $this->assertNotNull($detail);
        $row = $detail['student'];
        $this->assertSame(2_500_000.0, $row['paid']);
        $this->assertSame(5_000_000.0, $row['remaining']);
        $this->assertSame('partial', $row['status']);

        $this->assertCount(4, $detail['history']);
        $this->assertFalse($detail['history']->contains('payment_id', 'PAY-MEDICAL'));
        $this->assertSame('Tagihan dari Master', $detail['history']->firstWhere('payment_id', 'PAY-MASTER')['source_label']);
        $this->assertSame('Bayar Mandiri', $detail['history']->firstWhere('payment_id', 'PAY-SELF')['source_label']);
        $this->assertSame('Tunai', $detail['history']->firstWhere('payment_id', 'PAY-SELF')['method_label']);
        $this->assertNull($detail['history']->firstWhere('payment_id', 'PAY-SELF')['invoice_id']);
        $this->assertSame('INV-EDU', $detail['history']->firstWhere('payment_id', 'PAY-MASTER')['invoice_id']);
        $this->assertSame(1_500_000.0, $detail['history']->firstWhere('payment_id', 'PAY-MASTER')['invoice_amount']);
    }

    public function test_fee_resolver_uses_default_then_program_then_batch_without_hardcoding(): void
    {
        $service = $this->service(
            students: [
                $this->student('STU-DEFAULT', 'Default', 'CLS-A'),
                array_merge($this->student('STU-PROGRAM', 'Program', 'CLS-A'), ['Program_ID' => 'PRG-1']),
                array_merge($this->student('STU-BATCH', 'Batch', 'CLS-A'), ['Program_ID' => 'PRG-1', 'Batch_ID' => 'BAT-1']),
            ],
            classes: [$this->classRow('CLS-A', 'Kelas A')],
            programs: [['Program_ID' => 'PRG-1', 'Program_Name' => 'Program Jepang', 'Tuition_Fee' => 7_100_000]],
            batches: [['Batch_ID' => 'BAT-1', 'Batch_Name' => 'Batch September', 'Tuition_Fee' => 8_200_000]],
            defaultFee: 6_300_000,
        );

        $rows = $this->rows($service->build())->keyBy('student_id');
        $this->assertSame(6_300_000.0, $rows['STU-DEFAULT']['education_fee']);
        $this->assertSame(7_100_000.0, $rows['STU-PROGRAM']['education_fee']);
        $this->assertSame(8_200_000.0, $rows['STU-BATCH']['education_fee']);
        $this->assertSame('Belum Bayar', $rows['STU-DEFAULT']['status_label']);
    }

    public function test_status_boundaries_overpayment_and_unset_fee_are_fail_safe(): void
    {
        $students = [
            $this->student('STU-NONE', 'Belum Bayar', 'CLS-A'),
            $this->student('STU-FULL', 'Lunas', 'CLS-A'),
            $this->student('STU-OVER', 'Lebih Bayar', 'CLS-A'),
        ];
        $payments = [
            $this->payment('PAY-FULL', '', 'STU-FULL', 1_000, 'Verified'),
            $this->payment('PAY-OVER', '', 'STU-OVER', 1_200, 'Verified'),
        ];
        $rows = $this->rows($this->service(
            students: $students,
            classes: [$this->classRow('CLS-A', 'Kelas A')],
            payments: $payments,
            defaultFee: 1_000,
        )->build())->keyBy('student_id');

        $this->assertSame('unpaid', $rows['STU-NONE']['status']);
        $this->assertSame('paid', $rows['STU-FULL']['status']);
        $this->assertSame('paid', $rows['STU-OVER']['status']);
        $this->assertSame(0.0, $rows['STU-OVER']['remaining']);
        $this->assertSame(200.0, $rows['STU-OVER']['excess']);

        $unset = $this->rows($this->service(
            students: [$this->student('STU-X', 'Tanpa Tarif', 'CLS-A')],
            classes: [$this->classRow('CLS-A', 'Kelas A')],
            defaultFee: 0,
        )->build())->first();
        $this->assertSame('fee_unset', $unset['status']);
        $this->assertSame('Biaya Belum Ditetapkan', $unset['status_label']);
    }

    public function test_filters_kpi_deduplication_and_alumni_follow_new_semantics(): void
    {
        $students = [
            $this->student('STU-1', 'Budi Santoso', 'CLS-A'),
            $this->student('STU-1', 'Budi Duplikat', 'CLS-A'),
            $this->student('STU-2', 'Citra Ayu', 'CLS-B'),
            array_merge($this->student('STU-ALUMNI', 'Alumni', 'CLS-A'), ['Enrollment_Status' => 'ALUMNI']),
            array_merge($this->student('STU-INACTIVE', 'Nonaktif', 'CLS-A'), ['Is_Active' => 'FALSE']),
            $this->student('STU-OLD-CLASS', 'Kelas Lama', 'CLS-OLD'),
        ];
        $classes = [
            $this->classRow('CLS-A', 'Kelas A'),
            $this->classRow('CLS-B', 'Kelas B'),
            array_merge($this->classRow('CLS-OLD', 'Kelas Lama'), ['Is_Active' => 'FALSE']),
        ];
        $payments = [$this->payment('PAY-1', '', 'STU-1', 400, 'Verified')];

        $all = $this->service($students, $classes, payments: $payments, defaultFee: 1_000)->build();
        $this->assertSame(2, $all['kpi']['students']);
        $this->assertSame(['STU-1', 'STU-2'], $this->rows($all)->pluck('student_id')->sort()->values()->all());
        $this->assertSame(2_000.0, $all['kpi']['education_fee']);
        $this->assertSame(400.0, $all['kpi']['paid']);
        $this->assertSame(1_600.0, $all['kpi']['remaining']);

        $search = $this->service($students, $classes, payments: $payments, defaultFee: 1_000)->build(['search' => 'bUdI']);
        $this->assertSame(['STU-1'], $this->rows($search)->pluck('student_id')->all());
        $class = $this->service($students, $classes, payments: $payments, defaultFee: 1_000)->build(['class_id' => 'CLS-B']);
        $this->assertSame(['STU-2'], $this->rows($class)->pluck('student_id')->all());
        $status = $this->service($students, $classes, payments: $payments, defaultFee: 1_000)->build(['status' => 'partial']);
        $this->assertSame(['STU-1'], $this->rows($status)->pluck('student_id')->all());
    }

    public function test_master_and_administrator_can_open_index_and_detail_with_responsive_ui(): void
    {
        foreach (['MASTER', 'ADMINISTRATOR'] as $role) {
            $this->bindSnapshots(
                [$this->student('STU-1', 'Budi Santoso', 'CLS-A')],
                [$this->classRow('CLS-A', 'Kelas A')],
                [],
                [],
                [$this->invoice('INV-1', 'STU-1', 1_000, 'Biaya Pendidikan', 'Paid')],
                [$this->payment('PAY-1', 'INV-1', 'STU-1', 1_000, 'Verified', 'TRANSFER')],
                7_500,
            );
            $this->actingAsRole($role);

            $this->get(route('finance.education-payments.index'))
                ->assertOk()
                ->assertSee('Biaya Pendidikan')
                ->assertSee('Rp 7.500')
                ->assertSee('Cicilan')
                ->assertSee('Lihat Detail')
                ->assertSee('md:hidden', false)
                ->assertSee('hidden md:block', false);

            $this->get(route('finance.education-payments.show', 'STU-1'))
                ->assertOk()
                ->assertSee('Budi Santoso')
                ->assertSee('Tagihan dari Master')
                ->assertSee('Transfer Bank')
                ->assertSee('PAY-1')
                ->assertSee('INV-1')
                ->assertSee('Tanggal Pembayaran')
                ->assertSee('Tanggal Verifikasi')
                ->assertSee('Nominal Invoice')
                ->assertSee('Status Invoice')
                ->assertSee('Catatan')
                ->assertSee('Status keseluruhan, terpisah dari status setiap invoice.')
                ->assertSee('lg:hidden', false)
                ->assertSee('hidden lg:block', false);
        }
    }

    public function test_detail_distinguishes_self_service_and_master_invoice_sources(): void
    {
        $this->bindSnapshots(
            [$this->student('STU-1', 'Budi', 'CLS-A')],
            [$this->classRow('CLS-A', 'Kelas A')],
            [],
            [],
            [$this->invoice('INV-1', 'STU-1', 500, 'Biaya Pendidikan', 'Paid')],
            [
                $this->payment('PAY-SELF', '', 'STU-1', 250, 'Verified', 'CASH'),
                $this->payment('PAY-MASTER', 'INV-1', 'STU-1', 500, 'Verified', 'TRANSFER'),
            ],
            1_000,
        );
        $this->actingAsRole('ADMINISTRATOR');

        $this->get(route('finance.education-payments.show', 'STU-1'))
            ->assertOk()
            ->assertSee('Bayar Mandiri')
            ->assertSee('Tagihan dari Master')
            ->assertSee('PAY-SELF')
            ->assertSee('PAY-MASTER');
    }

    public function test_invalid_student_is_404_and_other_roles_are_forbidden_before_sheet_reads(): void
    {
        $this->bindSnapshots(
            [$this->student('STU-1', 'Budi', 'CLS-A')],
            [$this->classRow('CLS-A', 'Kelas A')],
            [], [], [], [], 1_000,
        );
        $this->actingAsRole('ADMINISTRATOR');
        $this->get(route('finance.education-payments.show', 'FORGED-STUDENT'))->assertNotFound();

        $this->mock(StudentRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(ClassRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(ProgramRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(BatchRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(InvoiceRepositoryInterface::class)->shouldNotReceive('getAll');
        $this->mock(PaymentRepositoryInterface::class)->shouldNotReceive('getAll');
        $this->actingAsRole('FINANCE');
        $this->get(route('finance.education-payments.index'))->assertForbidden();
        $this->get(route('finance.education-payments.show', 'STU-1'))->assertForbidden();
    }

    public function test_invalid_filter_is_rejected_before_any_sheet_read(): void
    {
        $this->withoutMiddleware(RoleMiddleware::class);
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));
        $this->mock(StudentRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(ClassRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(ProgramRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(BatchRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(InvoiceRepositoryInterface::class)->shouldNotReceive('getAll');
        $this->mock(PaymentRepositoryInterface::class)->shouldNotReceive('getAll');

        $this->get(route('finance.education-payments.index', ['status' => 'forged']))
            ->assertSessionHasErrors('status');
    }

    public function test_finance_transaction_ledger_is_never_read_or_double_counted(): void
    {
        $transactionRepository = Mockery::mock(TransactionRepositoryInterface::class);
        $transactionRepository->shouldNotReceive('fetchAll');
        $transactionRepository->shouldNotReceive('getAll');
        $this->app->instance(TransactionRepositoryInterface::class, $transactionRepository);

        $row = $this->rows($this->service(
            students: [$this->student('STU-1', 'Budi', 'CLS-A')],
            classes: [$this->classRow('CLS-A', 'Kelas A')],
            payments: [$this->payment('PAY-1', '', 'STU-1', 500, 'Verified')],
            defaultFee: 1_000,
        )->build())->first();

        $this->assertSame(500.0, $row['paid']);
    }

    private function service(
        array $students,
        array $classes,
        array $programs = [],
        array $batches = [],
        array $invoices = [],
        array $payments = [],
        float $defaultFee = 7_500_000,
    ): EducationPaymentMonitoringService {
        $dependencies = $this->snapshotDependencies(
            $students, $classes, $programs, $batches, $invoices, $payments, $defaultFee,
        );

        return new EducationPaymentMonitoringService(...$dependencies);
    }

    private function bindSnapshots(
        array $students,
        array $classes,
        array $programs,
        array $batches,
        array $invoices,
        array $payments,
        float $defaultFee,
    ): void {
        $dependencies = $this->snapshotDependencies(
            $students, $classes, $programs, $batches, $invoices, $payments, $defaultFee, false,
        );
        [$studentRepo, $classRepo, $programRepo, $batchRepo, $invoiceRepo, $paymentRepo, $invoiceService] = $dependencies;

        $this->app->instance(StudentRepositoryInterface::class, $studentRepo);
        $this->app->instance(ClassRepositoryInterface::class, $classRepo);
        $this->app->instance(ProgramRepositoryInterface::class, $programRepo);
        $this->app->instance(BatchRepositoryInterface::class, $batchRepo);
        $this->app->instance(InvoiceRepositoryInterface::class, $invoiceRepo);
        $this->app->instance(PaymentRepositoryInterface::class, $paymentRepo);
        $this->app->instance(InvoiceService::class, $invoiceService);
    }

    private function snapshotDependencies(
        array $students,
        array $classes,
        array $programs,
        array $batches,
        array $invoices,
        array $payments,
        float $defaultFee,
        bool $strictReads = true,
    ): array {
        $read = $strictReads ? 'once' : 'zeroOrMoreTimes';
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->{$read}()->andReturn(collect($students));
        $classRepo = Mockery::mock(ClassRepositoryInterface::class);
        $classRepo->shouldReceive('fetchAll')->{$read}()->andReturn(collect($classes));
        $programRepo = Mockery::mock(ProgramRepositoryInterface::class);
        $programRepo->shouldReceive('fetchAll')->{$read}()->andReturn(collect($programs));
        $batchRepo = Mockery::mock(BatchRepositoryInterface::class);
        $batchRepo->shouldReceive('fetchAll')->{$read}()->andReturn(collect($batches));
        $invoiceRepo = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepo->shouldReceive('getAll')->{$read}()->andReturn(collect($invoices));
        $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
        $paymentRepo->shouldReceive('getAll')->{$read}()->andReturn(collect($payments));

        $settings = Mockery::mock(SystemSettingService::class)->shouldIgnoreMissing();
        $settings->shouldReceive('getDefaultTuitionFee')->zeroOrMoreTimes()->andReturn($defaultFee);
        $this->app->instance(SystemSettingService::class, $settings);
        $invoiceService = new InvoiceService(
            $invoiceRepo,
            Mockery::mock(EnterpriseEventService::class),
            $studentRepo,
            Mockery::mock(CompanyRepositoryInterface::class),
            $paymentRepo,
        );

        return [$studentRepo, $classRepo, $programRepo, $batchRepo, $invoiceRepo, $paymentRepo, $invoiceService];
    }

    private function actingAsRole(string $role): void
    {
        $roleId = 'ROLE-'.$role;
        $roles = Mockery::mock(RoleService::class);
        $roles->shouldReceive('getRoleById')->zeroOrMoreTimes()->with($roleId)->andReturn([
            'Role_ID' => $roleId, 'Role_Name' => $role, 'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roles);
        $this->actingAs(new GenericUser([
            'id' => 'USR-'.$role,
            'User_ID' => 'USR-'.$role,
            'Role_ID' => $roleId,
            'Role' => $role,
            'Full_Name' => $role,
        ]));
    }

    private function rows(array $result)
    {
        return $result['groups']->flatMap(fn ($group) => $group['students']);
    }

    private function student(string $id, string $name, string $classId): array
    {
        return [
            'Student_ID' => $id,
            'Student_Number' => 'NIS-'.$id,
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

    private function invoice(
        string $id,
        string $studentId,
        float $amount,
        string $category,
        string $status = 'Waiting Payment',
    ): array {
        return [
            'Invoice_ID' => $id,
            'Student_ID' => $studentId,
            'Invoice_Type' => 'STUDENT',
            'Category' => $category,
            'Description' => $category,
            'Amount' => $amount,
            'Status' => $status,
            'Due_Date' => now()->addYear()->toDateString(),
            'Is_Active' => 'TRUE',
        ];
    }

    private function payment(
        string $id,
        string $invoiceId,
        string $studentId,
        float $amount,
        string $status,
        string $method = 'TRANSFER',
    ): array {
        return [
            'Payment_ID' => $id,
            'Invoice_ID' => $invoiceId,
            'Student_ID' => $studentId,
            'Amount_Paid' => $amount,
            'Payment_Date' => '2026-09-12',
            'Payment_Method' => $method,
            'Reference_Number' => 'REF-'.$id,
            'Status' => $status,
            'Verified_At' => $status === 'Verified' ? '2026-09-12 10:00:00' : '',
            'Notes' => 'Catatan '.$id,
            'Is_Active' => 'TRUE',
        ];
    }
}
