<?php

namespace Tests\Feature;

use App\Exceptions\FinancialIntegrityException;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Services\Core\RoleService;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\EducationPaymentMonitoringService;
use Illuminate\Auth\GenericUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class H863EducationPaymentIntegrityTest extends TestCase
{
    private array $students;

    private array $invoices;

    private array $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->students = [['Student_ID' => 'STU-1', 'Full_Name' => 'Siswa Audit', 'User_ID' => 'USR-1', 'Class_ID' => 'CLS-1', 'Is_Active' => 'TRUE']];
        $this->invoices = [['Invoice_ID' => 'INV-1', 'Student_ID' => 'STU-1', 'Invoice_Type' => 'STUDENT', 'Category' => 'Biaya Pendidikan', 'Amount' => 2_500_000, 'Status' => 'Paid', 'Is_Active' => 'TRUE']];
        $this->payments = [$this->payment('PAY-1', 'INV-1', 2_500_000)];
        foreach ([StudentRepositoryInterface::class => 'students', InvoiceRepositoryInterface::class => 'invoices', PaymentRepositoryInterface::class => 'payments'] as $interface => $property) {
            $repo = Mockery::mock($interface);
            $method = $property === 'students' ? 'fetchAll' : 'getAll';
            $repo->shouldReceive($method)->andReturnUsing(fn () => collect($this->{$property}));
            $this->app->instance($interface, $repo);
        }
        foreach ([ClassRepositoryInterface::class => [['Class_ID' => 'CLS-1', 'Class_Name' => 'Kelas 1'], ['Class_ID' => 'CLS-2', 'Class_Name' => 'Kelas 2']], ProgramRepositoryInterface::class => [], BatchRepositoryInterface::class => []] as $interface => $rows) {
            $this->mock($interface)->shouldReceive('fetchAll')->andReturn(collect($rows));
        }
        $this->mock(SystemSettingService::class)->shouldIgnoreMissing()->shouldReceive('getDefaultTuitionFee')->andReturn(7_500_000);
        $this->mock(TransactionRepositoryInterface::class)->shouldNotReceive('fetchAll');
        $this->mock(UserRepositoryInterface::class)->shouldNotReceive('fetchAll');
    }

    private function payment(string $id, string $invoiceId, float $amount, string $status = 'Verified'): array
    {
        return ['Payment_ID' => $id, 'Student_ID' => 'STU-1', 'Invoice_ID' => $invoiceId, 'Amount_Paid' => $amount,
            'Status' => $status, 'Payment_Date' => '2026-09-16', 'Payment_Method' => 'TRANSFER',
            'Verified_At' => '2026-09-16 08:19:03', 'Created_At' => '2026-09-16 08:18:11'];
    }

    private function detail(): array
    {
        return $this->app->make(EducationPaymentMonitoringService::class)->detail('STU-1');
    }

    public static function lifecycleStates(): array
    {
        return [['Cancelled', 'TRUE'], ['Paid', 'FALSE'], ['Draft', 'FALSE'], ['Waiting Payment', 'TRUE']];
    }

    #[DataProvider('lifecycleStates')]
    public function test_verified_history_survives_invoice_status_and_active_changes(string $status, string $active): void
    {
        $before = $this->detail();
        $this->invoices[0]['Status'] = $status;
        $this->invoices[0]['Is_Active'] = $active;
        $after = $this->detail();
        $this->assertSame(2_500_000.0, $after['student']['paid']);
        $this->assertSame($before['student'], $after['student']);
        $this->assertSame('PAY-1', $after['history']->first()['payment_id']);
        if ($status === 'Cancelled') {
            $this->assertSame('Cancelled', $after['history']->first()['invoice_status']);
        }
    }

    public function test_replacement_invoice_without_new_money_does_not_change_paid(): void
    {
        $this->invoices[0]['Status'] = 'Cancelled';
        $this->invoices[0]['Is_Active'] = 'FALSE';
        $this->invoices[] = array_replace($this->invoices[0], ['Invoice_ID' => 'INV-REPLACEMENT', 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE', 'Amount' => 7_500_000]);
        $this->assertSame(2_500_000.0, $this->detail()['student']['paid']);
        $this->payments[] = $this->payment('PAY-2', 'INV-REPLACEMENT', 1_000_000);
        $this->assertSame(3_500_000.0, $this->detail()['student']['paid']);
    }

    public function test_class_transfer_batch_change_and_missing_login_leave_history_owned_by_student(): void
    {
        $before = $this->detail();
        $this->students[0]['Class_ID'] = 'CLS-2';
        $this->students[0]['Batch_ID'] = 'BAT-OTHER';
        $this->students[0]['User_ID'] = '';
        $after = $this->detail();
        $this->assertSame('Kelas 2', $after['student']['class_name']);
        $this->assertSame($before['student']['paid'], $after['student']['paid']);
        $this->assertSame($before['history']->all(), $after['history']->all());
    }

    public function test_historical_detail_remains_accessible_without_active_enrolment_or_class(): void
    {
        $this->students[0]['Class_ID'] = '';
        $this->students[0]['Enrollment_Status'] = 'ALUMNI';
        $this->students[0]['Is_Active'] = 'FALSE';
        $this->assertSame(2_500_000.0, $this->detail()['student']['paid']);
        $this->assertSame(0, $this->app->make(EducationPaymentMonitoringService::class)->build()['kpi']['students']);
    }

    public function test_duplicate_payment_and_invoice_rows_count_once(): void
    {
        $this->payments[] = array_replace($this->payments[0], ['Payment_ID' => ' pay-1 ']);
        $this->invoices[] = $this->invoices[0];
        $detail = $this->detail();
        $this->assertSame(2_500_000.0, $detail['student']['paid']);
        $this->assertCount(1, $detail['history']);
    }

    public static function conflictingFields(): array
    {
        return [['Status', 'Waiting Verification'], ['Invoice_ID', 'INV-OTHER'], ['Student_ID', 'STU-OTHER'], ['Amount_Paid', 999]];
    }

    #[DataProvider('conflictingFields')]
    public function test_conflicting_duplicate_payment_fails_explicitly_regardless_of_row_order(string $field, mixed $value): void
    {
        array_unshift($this->payments, array_replace($this->payments[0], [$field => $value]));
        $this->expectException(FinancialIntegrityException::class);
        $this->detail();
    }

    public function test_conflicting_invoice_identity_cannot_choose_an_arbitrary_owner(): void
    {
        $this->invoices[] = array_replace($this->invoices[0], ['Student_ID' => 'STU-OTHER']);
        $this->expectException(FinancialIntegrityException::class);
        $this->detail();
    }

    public function test_missing_invoice_is_an_integrity_error_instead_of_a_zero_balance(): void
    {
        $this->invoices = [];
        $this->expectException(FinancialIntegrityException::class);
        $this->expectExceptionMessage('INV-1');
        $this->detail();
    }

    public function test_forged_payment_student_cannot_claim_another_students_invoice(): void
    {
        $this->payments[0]['Student_ID'] = 'STU-FORGED';
        $this->expectException(FinancialIntegrityException::class);
        $this->detail();
    }

    public static function invalidMoney(): array
    {
        return [[-1], ['7,5'], [0], [null]];
    }

    #[DataProvider('invalidMoney')]
    public function test_invalid_verified_amount_cannot_masquerade_as_payment(mixed $amount): void
    {
        $this->payments[0]['Amount_Paid'] = $amount;
        $this->expectException(\InvalidArgumentException::class);
        $this->detail();
    }

    public function test_trimmed_identity_is_consistent_between_total_and_detail(): void
    {
        $this->payments[0]['Student_ID'] = ' STU-1 ';
        $this->payments[0]['Invoice_ID'] = ' INV-1 ';
        $this->assertCount(1, $this->detail()['history']);
        $this->assertSame(2_500_000.0, $this->detail()['student']['paid']);
    }

    public function test_pending_and_rejected_are_never_added_to_mixed_verified_education_money(): void
    {
        foreach (['', 'INV-1'] as $invoiceId) {
            foreach (['Waiting Verification', 'Rejected'] as $status) {
                $this->payments[] = $this->payment($invoiceId.$status, $invoiceId, 10_000_000, $status);
            }
        }
        $this->assertSame(2_500_000.0, $this->detail()['student']['paid']);
        $this->assertCount(5, $this->detail()['history']);
    }

    public function test_azka_arithmetic_and_overpayment_render_on_both_pages(): void
    {
        $this->invoices[0]['Amount'] = 7_500_000;
        $this->payments = [];
        foreach ([2_500_000, 1_000_000, 1_000_000, 1_000_000] as $i => $amount) {
            $this->payments[] = $this->payment('PAY-SELF-'.$i, '', $amount);
        }
        $this->payments[] = $this->payment('PAY-INVOICE', 'INV-1', 4_500_000);
        $detail = $this->detail();
        $this->assertSame([7_500_000.0, 10_000_000.0, 0.0, 2_500_000.0, 'paid'], array_values(array_intersect_key($detail['student'], array_flip(['education_fee', 'paid', 'remaining', 'excess', 'status']))));
        $this->assertSame(5, $detail['student']['verified_payment_count']);
        $this->assertCount(5, $detail['history']);
        $this->assertSame(4, $detail['history']->where('source_label', 'Bayar Mandiri')->count());
        $invoicePayment = $detail['history']->firstWhere('payment_id', 'PAY-INVOICE');
        $this->assertSame('Tagihan dari Master', $invoicePayment['source_label']);
        $this->assertSame('Transfer Bank', $invoicePayment['method_label']);
        $this->assertSame(4_500_000.0, $invoicePayment['amount']);
        $this->assertSame('INV-1', $invoicePayment['invoice_id']);
        $this->assertSame(7_500_000.0, $invoicePayment['invoice_amount']);
        $this->mock(RoleService::class)->shouldReceive('getRoleById')->andReturn(['Role_Name' => 'ADMINISTRATOR', 'Is_Active' => 'TRUE']);
        $this->actingAs(new GenericUser(['id' => 'ADMIN', 'User_ID' => 'ADMIN', 'Role' => 'ADMINISTRATOR', 'Role_ID' => 'ROLE-ADMIN']));
        foreach (['/finance/education-payments', '/finance/education-payments/STU-1'] as $url) {
            $this->get($url)->assertOk()->assertSee('Rp 10.000.000')->assertSee('Kelebihan Bayar')->assertSee('Rp 2.500.000')->assertSee('Lunas');
        }
        $this->get('/finance/education-payments/STU-1')
            ->assertOk()
            ->assertSee('Jumlah Pembayaran Terverifikasi')
            ->assertSee('5 kali');
    }

    public function test_verified_count_is_unique_and_pending_rejected_remain_visible_without_changing_paid(): void
    {
        $this->payments[] = $this->payments[0];
        $this->payments[] = $this->payment('PAY-PENDING', '', 9_000_000, 'Waiting Verification');
        $this->payments[] = $this->payment('PAY-REJECTED', '', 8_000_000, 'Rejected');

        $detail = $this->detail();
        $this->assertSame(2_500_000.0, $detail['student']['paid']);
        $this->assertSame(1, $detail['student']['verified_payment_count']);
        $this->assertCount(3, $detail['history']);
        $this->assertSame(
            ['PAY-1', 'PAY-PENDING', 'PAY-REJECTED'],
            $detail['history']->pluck('payment_id')->sort()->values()->all(),
        );

        $this->mock(RoleService::class)->shouldReceive('getRoleById')->andReturn(['Role_Name' => 'ADMINISTRATOR', 'Is_Active' => 'TRUE']);
        $this->actingAs(new GenericUser(['id' => 'ADMIN', 'User_ID' => 'ADMIN', 'Role' => 'ADMINISTRATOR', 'Role_ID' => 'ROLE-ADMIN']));
        $this->get('/finance/education-payments/STU-1')
            ->assertOk()
            ->assertSee('1 kali')
            ->assertSee('Menunggu Verifikasi')
            ->assertSee('Ditolak')
            ->assertSee('PAY-PENDING')
            ->assertSee('PAY-REJECTED');
    }

    public function test_history_order_is_latest_first_with_all_deterministic_tie_breakers(): void
    {
        $this->payments = [
            array_replace($this->payment('PAY-OLDER-DATE', '', 100), ['Payment_Date' => '2026-09-15']),
            array_replace($this->payment('PAY-OLDER-VERIFIED', '', 100), ['Verified_At' => '2026-09-16 08:00:00']),
            array_replace($this->payment('PAY-OLDER-CREATED', '', 100), ['Created_At' => '2026-09-16 08:17:00']),
            $this->payment('PAY-A', '', 100),
            $this->payment('PAY-B', '', 100),
        ];

        $this->assertSame([
            'PAY-B',
            'PAY-A',
            'PAY-OLDER-CREATED',
            'PAY-OLDER-VERIFIED',
            'PAY-OLDER-DATE',
        ], $this->detail()['history']->pluck('payment_id')->all());
    }

    public function test_empty_history_has_zero_verified_count_and_exact_responsive_empty_state(): void
    {
        $this->payments = [];
        $detail = $this->detail();
        $this->assertSame(0.0, $detail['student']['paid']);
        $this->assertSame(0, $detail['student']['verified_payment_count']);
        $this->assertCount(0, $detail['history']);

        $this->mock(RoleService::class)->shouldReceive('getRoleById')->andReturn(['Role_Name' => 'MASTER', 'Is_Active' => 'TRUE']);
        $this->actingAs(new GenericUser(['id' => 'MASTER', 'User_ID' => 'MASTER', 'Role' => 'MASTER', 'Role_ID' => 'ROLE-MASTER']));
        $this->get('/finance/education-payments/STU-1')
            ->assertOk()
            ->assertSee('0 kali')
            ->assertSee('Belum ada riwayat pembayaran Biaya Pendidikan.')
            ->assertSee('lg:hidden', false)
            ->assertSee('hidden lg:block', false);
    }

    public function test_student_cannot_read_other_students_payment_detail(): void
    {
        $this->mock(RoleService::class)->shouldReceive('getRoleById')->andReturn(['Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE']);
        $this->actingAs(new GenericUser(['id' => 'USR-OTHER', 'User_ID' => 'USR-OTHER', 'Role' => 'STUDENT', 'Role_ID' => 'ROLE-STUDENT']));
        $this->get('/finance/education-payments/STU-1')->assertForbidden();
    }

    public function test_projection_is_read_only_and_does_not_require_or_mutate_schema(): void
    {
        // Every repository double permits reads only; append/update/delete or
        // any schema operation would fail this test immediately.
        $schema = config('finance.schema');
        $before = [$this->students, $this->invoices, $this->payments];
        $this->app->make(EducationPaymentMonitoringService::class)->build();
        $this->detail();
        $this->assertSame($before, [$this->students, $this->invoices, $this->payments]);
        $this->assertSame($schema, config('finance.schema'));
    }

    public function test_missing_payment_identity_does_not_contribute_untraceable_money(): void
    {
        $this->payments[0]['Payment_ID'] = '';
        $this->expectException(FinancialIntegrityException::class);
        $this->detail();
    }

    public function test_verified_payment_with_invalid_student_anchor_fails_explicitly(): void
    {
        $this->payments[0]['Invoice_ID'] = '';
        $this->payments[0]['Student_ID'] = 'STU-MISSING';
        $this->expectException(FinancialIntegrityException::class);
        $this->detail();
    }

    public function test_inactive_verified_payment_is_flagged_instead_of_silently_returning_zero(): void
    {
        $this->payments[0]['Is_Active'] = 'FALSE';
        $this->expectException(FinancialIntegrityException::class);
        $this->detail();
    }
}
