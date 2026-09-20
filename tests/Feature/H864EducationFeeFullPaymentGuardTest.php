<?php

namespace Tests\Feature;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Exceptions\FinancialIntegrityException;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class H864EducationFeeFullPaymentGuardTest extends TestCase
{
    private H864PaymentRepository $payments;

    private H864InvoiceRepository $invoices;

    private H864StudentRepository $students;

    private H864TransactionRepository $transactions;

    private InvoiceService $invoiceService;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('getDefaultTuitionFee')->zeroOrMoreTimes()->andReturn(7_500_000.0);
        $this->app->instance(SystemSettingService::class, $settings);

        foreach ([ProgramRepositoryInterface::class, BatchRepositoryInterface::class] as $interface) {
            $repository = Mockery::mock($interface);
            $repository->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(null);
            $this->app->instance($interface, $repository);
        }

        $this->payments = new H864PaymentRepository;
        $this->invoices = new H864InvoiceRepository;
        $this->students = new H864StudentRepository;
        $this->transactions = new H864TransactionRepository;
        $events = Mockery::mock(EnterpriseEventService::class)->shouldIgnoreMissing();

        $this->invoiceService = new InvoiceService(
            $this->invoices,
            $events,
            $this->students,
            new H864CompanyRepository,
            $this->payments,
        );
        $this->app->instance(InvoiceService::class, $this->invoiceService);
        $this->paymentService = new PaymentService(
            $this->payments,
            $this->invoices,
            $this->students,
            new H864CompanyRepository,
            new H864AccountRepository,
            $this->transactions,
            $events,
        );

        $this->asStudent();
    }

    public function test_unpaid_student_may_submit_bayar_mandiri(): void
    {
        $created = $this->submitSelfService(500_000, '10000000-0000-4000-8000-000000000001');

        $this->assertSame('Waiting Verification', $created['Status']);
        $this->assertSame('STUDENT_SELF_SERVICE', $created['Payment_Type']);
        $this->assertSame('STU-1', $created['Student_ID']);
        $this->assertSame('', $created['Invoice_ID']);
        $this->assertCount(1, $this->payments->rows);
    }

    public function test_partial_student_may_submit_at_exact_remaining_boundary(): void
    {
        $this->payments->rows[] = $this->payment('PAY-VERIFIED-5M', 5_000_000, 'Verified');

        $created = $this->submitSelfService(2_500_000, '10000000-0000-4000-8000-000000000002');

        $this->assertSame(2_500_000.0, $created['Amount_Paid']);
        $state = $this->state();
        $this->assertSame(5_000_000.0, $state['verified_paid']);
        $this->assertSame(2_500_000.0, $state['pending_reserved']);
        $this->assertSame(0.0, $state['remaining_payable']);
    }

    public function test_partial_student_cannot_submit_more_than_remaining(): void
    {
        $this->payments->rows[] = $this->payment('PAY-VERIFIED-5M', 5_000_000, 'Verified');

        try {
            $this->submitSelfService(2_500_001, '10000000-0000-4000-8000-000000000003');
            $this->fail('Over-remaining Bayar Mandiri must be rejected.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertSame(
                'Nominal pembayaran melebihi sisa Biaya Pendidikan sebesar Rp2.500.000.',
                $exception->getMessage(),
            );
        }
        $this->assertCount(1, $this->payments->rows);
    }

    public function test_fully_paid_student_and_forged_direct_request_are_rejected_without_mutation(): void
    {
        $this->payments->rows[] = $this->payment('PAY-PAID', 7_500_000, 'Verified');

        try {
            $this->submitSelfService(1, '10000000-0000-4000-8000-000000000004');
            $this->fail('Fully paid student must be rejected.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertSame('Biaya Pendidikan Anda sudah lunas.', $exception->getMessage());
        }

        try {
            $this->submitSelfService(1, '10000000-0000-4000-8000-000000000005', ['Student_ID' => 'STU-FORGED']);
            $this->fail('Forged Student_ID must be rejected.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertStringContainsString('tidak sesuai', $exception->getMessage());
        }
        $this->assertCount(1, $this->payments->rows);
        $this->assertSame([], $this->transactions->rows);
    }

    public function test_idempotent_replay_returns_one_payment_and_different_key_respects_reservation(): void
    {
        $key = '10000000-0000-4000-8000-000000000006';
        $first = $this->submitSelfService(5_000_000, $key);
        $replay = $this->submitSelfService(5_000_000, $key);
        $this->assertSame($first['Payment_ID'], $replay['Payment_ID']);
        $this->assertCount(1, $this->payments->rows);

        try {
            $this->submitSelfService(3_000_000, '10000000-0000-4000-8000-000000000007');
            $this->fail('A second pending payment must not over-reserve tuition capacity.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertStringContainsString('Rp2.500.000', $exception->getMessage());
        }
        $this->assertCount(1, $this->payments->rows);
    }

    public function test_pending_is_reserved_but_never_reported_as_verified_paid(): void
    {
        $this->payments->rows = [
            $this->payment('PAY-PENDING', 2_000_000, 'Waiting Verification'),
            $this->payment('PAY-REVISION', 500_000, 'Need Revision'),
            $this->payment('PAY-REJECTED', 9_000_000, 'Rejected'),
        ];

        $state = $this->state();

        $this->assertSame(0.0, $state['verified_paid']);
        $this->assertSame(2_500_000.0, $state['pending_reserved']);
        $this->assertSame(5_000_000.0, $state['remaining_payable']);
        $this->assertSame('unpaid', $state['status']);
    }

    public function test_exact_verified_fee_is_lunas_and_paid_ui_hides_the_form(): void
    {
        $this->payments->rows[] = $this->payment('PAY-EXACT', 7_500_000, 'Verified');
        $state = $this->state();

        $this->assertSame('paid', $state['status']);
        $this->assertSame(0.0, $state['remaining_verified']);
        $html = view('student.billing.self-service', [
            'bank' => [],
            'educationPaymentState' => $state,
        ])->render();
        $this->assertStringContainsString('Biaya Pendidikan Anda sudah lunas.', $html);
        $this->assertStringContainsString('Rp7.500.000', $html);
        $this->assertStringNotContainsString('id="self-service-payment-form"', $html);
    }

    public function test_azka_historical_overpayment_is_read_only_and_blocks_new_payment(): void
    {
        $this->payments->rows[] = $this->payment('PAY-AZKA-HISTORY', 10_000_000, 'Verified');
        $before = $this->payments->rows;
        $state = $this->state();

        $this->assertSame(10_000_000.0, $state['verified_paid']);
        $this->assertSame(0.0, $state['remaining_verified']);
        $this->assertSame('paid', $state['status']);
        try {
            $this->submitSelfService(1, '10000000-0000-4000-8000-000000000008');
            $this->fail('Historical overpayment must block new Bayar Mandiri.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertSame('Biaya Pendidikan Anda sudah lunas.', $exception->getMessage());
        }
        $this->assertSame($before, $this->payments->rows);
    }

    public function test_master_cannot_create_education_invoice_after_full_payment_or_above_partial_remaining(): void
    {
        $this->payments->rows[] = $this->payment('PAY-MASTER-PAID', 7_500_000, 'Verified');
        $this->asFinance();

        try {
            $this->createInvoice('INV-EDU-FULL', 'Biaya Pendidikan', 1);
            $this->fail('Fully paid student must not receive another education invoice.');
        } catch (\Exception $exception) {
            $this->assertSame(
                'Biaya Pendidikan siswa sudah lunas. Invoice Biaya Pendidikan baru tidak dapat dibuat.',
                $exception->getMessage(),
            );
        }
        $this->assertSame([], $this->invoices->rows);

        $this->payments->rows = [$this->payment('PAY-MASTER-PARTIAL', 5_000_000, 'Verified')];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sisa yang boleh dibuat Rp 2.500.000');
        $this->createInvoice('INV-EDU-OVER', 'Biaya Pendidikan', 2_500_001);
    }

    public function test_master_non_education_invoice_and_its_payment_remain_allowed_and_isolated(): void
    {
        $this->payments->rows[] = $this->payment('PAY-TUITION-PAID', 7_500_000, 'Verified');
        $this->asFinance();
        $invoice = $this->createInvoice('INV-JLPT', 'JLPT', 500_000);

        $this->assertSame('JLPT', $invoice['Category']);
        $this->assertCount(1, $this->invoices->rows);
        $this->invoices->update('INV-JLPT', ['Status' => 'Waiting Payment']);
        $created = $this->paymentService->submitPayment([
            'Invoice_ID' => 'INV-JLPT',
            'Student_ID' => 'STU-1',
            'Amount_Paid' => 500_000,
            'Payment_Method' => 'TRANSFER',
            'Idempotency_Key' => '10000000-0000-4000-8000-000000000009',
        ]);

        $this->assertSame('INV-JLPT', $created['Invoice_ID']);
        $state = $this->state();
        $this->assertSame(7_500_000.0, $state['verified_paid']);
        $this->assertSame(0.0, $state['pending_reserved']);
        $this->assertSame('paid', $state['status']);
    }

    public function test_non_education_verified_payment_and_ledger_mirror_are_not_double_counted(): void
    {
        $this->invoices->rows[] = [
            'Invoice_ID' => 'INV-MEDICAL', 'Invoice_Type' => 'STUDENT', 'Student_ID' => 'STU-1',
            'Category' => 'Medical', 'Amount' => 1_000_000, 'Status' => 'Paid', 'Is_Active' => 'TRUE',
        ];
        $this->payments->rows = [
            $this->payment('PAY-EDU', 2_500_000, 'Verified'),
            $this->payment('PAY-MEDICAL', 1_000_000, 'Verified', 'INV-MEDICAL', 'STUDENT'),
        ];
        $this->transactions->rows[] = [
            'Transaction_ID' => 'TRX-PAY-EDU', 'Reference_Type' => 'Payment',
            'Reference_ID' => 'PAY-EDU', 'Type' => 'Income', 'Amount' => 2_500_000,
        ];

        $state = $this->state();

        $this->assertSame(2_500_000.0, $state['verified_paid']);
        $this->assertSame(5_000_000.0, $state['remaining_verified']);
        $this->assertSame('partial', $state['status']);
        $this->assertCount(1, $this->transactions->rows);
    }

    public function test_education_invoice_payment_cannot_bypass_canonical_remaining_at_creation_or_verification(): void
    {
        $this->invoices->rows[] = [
            'Invoice_ID' => 'INV-LEGACY-EDU', 'Invoice_Type' => 'STUDENT', 'Student_ID' => 'STU-1',
            'Category' => 'Biaya Pendidikan', 'Amount' => 7_500_000,
            'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE',
        ];
        $this->payments->rows[] = $this->payment('PAY-STANDALONE-5M', 5_000_000, 'Verified');
        $this->asFinance();

        try {
            $this->paymentService->submitPayment([
                'Invoice_ID' => 'INV-LEGACY-EDU',
                'Student_ID' => 'STU-1',
                'Amount_Paid' => 3_000_000,
                'Idempotency_Key' => '10000000-0000-4000-8000-000000000011',
            ]);
            $this->fail('Education invoice payment must respect canonical remaining.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertStringContainsString('Rp2.500.000', $exception->getMessage());
        }
        $this->assertCount(1, $this->payments->rows);

        $this->payments->rows[] = $this->payment(
            'PAY-PENDING-LEGACY',
            3_000_000,
            'Waiting Verification',
            'INV-LEGACY-EDU',
            'STUDENT',
        );
        try {
            $this->paymentService->verifyPayment('PAY-PENDING-LEGACY', 'ignored', 'Verified');
            $this->fail('Verification must re-check canonical remaining.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertStringContainsString('sisa Biaya Pendidikan Rp2.500.000', $exception->getMessage());
        }
        $this->assertSame('Waiting Verification', $this->payments->getById('PAY-PENDING-LEGACY')['Status']);
        $this->assertSame([], $this->transactions->rows);
    }

    public function test_student_cannot_pay_another_students_forged_invoice(): void
    {
        $this->invoices->rows[] = [
            'Invoice_ID' => 'INV-OTHER', 'Invoice_Type' => 'STUDENT', 'Student_ID' => 'STU-OTHER',
            'Category' => 'Medical', 'Amount' => 500_000, 'Status' => 'Waiting Payment', 'Is_Active' => 'TRUE',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('bukan milik akun Anda');
        try {
            $this->paymentService->submitPayment([
                'Invoice_ID' => 'INV-OTHER',
                'Student_ID' => 'STU-1',
                'Amount_Paid' => 500_000,
                'Idempotency_Key' => '10000000-0000-4000-8000-000000000010',
            ]);
        } finally {
            $this->assertSame([], $this->payments->rows);
        }
    }

    private function state(): array
    {
        return $this->invoiceService->getStudentEducationPaymentState('STU-1');
    }

    private function submitSelfService(float $amount, string $key, array $extra = []): array
    {
        return $this->paymentService->submitPayment(array_merge([
            'Self_Service' => true,
            'Amount_Paid' => $amount,
            'Payment_Method' => 'TRANSFER',
            'Sender_Name' => 'Siswa H8.64',
            'Transfer_Date' => '2026-09-18',
            'Idempotency_Key' => $key,
        ], $extra));
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
            'Payment_Date' => '2026-09-18',
            'Status' => $status,
            'Is_Active' => 'TRUE',
        ];
    }

    private function createInvoice(string $id, string $category, float $amount): array
    {
        return $this->invoiceService->create([
            'Invoice_ID' => $id,
            'Invoice_Type' => 'STUDENT',
            'Student_ID' => 'STU-1',
            'Category' => $category,
            'Description' => "Tagihan {$category}",
            'Due_Date' => '2026-10-18',
            'items' => [[
                'description' => $category,
                'qty' => 1,
                'unit_price' => $amount,
            ]],
        ]);
    }

    private function asStudent(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-STU-1', 'User_ID' => 'USR-STU-1', 'Role' => 'STUDENT',
        ]));
    }

    private function asFinance(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-FIN', 'User_ID' => 'USR-FIN', 'Role' => 'FINANCE',
        ]));
    }
}

class H864PaymentRepository implements PaymentRepositoryInterface
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
        return collect($this->rows)->firstWhere('Payment_ID', $id);
    }

    public function getByIdFresh($id)
    {
        return $this->getById($id);
    }

    public function create(array $data)
    {
        if ($this->getById($data['Payment_ID'] ?? '') !== null) {
            throw new DuplicatePrimaryKeyException('duplicate payment');
        }
        $this->rows[] = $data;

        return $data;
    }

    public function update($id, array $data)
    {
        foreach ($this->rows as &$row) {
            if (($row['Payment_ID'] ?? '') === $id) {
                $row = array_merge($row, $data);

                return true;
            }
        }

        return false;
    }

    public function delete($id)
    {
        return false;
    }

    public function clearCache(): void {}
}

class H864InvoiceRepository implements InvoiceRepositoryInterface
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
        return collect($this->rows)->firstWhere('Invoice_ID', $id);
    }

    public function findByIdFresh($id)
    {
        return $this->getById($id);
    }

    public function create(array $data)
    {
        if ($this->getById($data['Invoice_ID'] ?? '') !== null) {
            throw new DuplicatePrimaryKeyException('duplicate invoice');
        }
        $this->rows[] = $data;

        return $data;
    }

    public function update($id, array $data)
    {
        foreach ($this->rows as &$row) {
            if (($row['Invoice_ID'] ?? '') === $id) {
                $row = array_merge($row, $data);

                return true;
            }
        }

        return false;
    }

    public function delete($id)
    {
        return false;
    }

    public function clearCache(): void {}
}

class H864StudentRepository implements StudentRepositoryInterface
{
    private array $rows = [[
        'Student_ID' => 'STU-1', 'User_ID' => 'USR-STU-1', 'Full_Name' => 'Siswa H8.64',
        'Program_ID' => '', 'Batch_ID' => '', 'Is_Active' => 'TRUE',
    ]];

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function fetchAllFresh()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Student_ID', $id);
    }

    public function findByStudentNumber(string $number)
    {
        return null;
    }

    public function findByNationalId(string $nationalId)
    {
        return null;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.'-1';
    }

    public function create(array $data)
    {
        return false;
    }

    public function update(string $id, array $data)
    {
        return false;
    }

    public function softDelete(string $id)
    {
        return false;
    }

    public function clearCache(): void {}
}

class H864CompanyRepository implements CompanyRepositoryInterface
{
    public function fetchAll()
    {
        return collect();
    }

    public function findById(string $id)
    {
        return null;
    }

    public function findByCode(string $code)
    {
        return null;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.'-1';
    }

    public function create(array $data)
    {
        return false;
    }

    public function update(string $id, array $data)
    {
        return false;
    }

    public function softDelete(string $id)
    {
        return false;
    }
}

class H864AccountRepository implements AccountRepositoryInterface
{
    private array $account = [
        'Account_ID' => 'ACC-1', 'Account_Code' => '101', 'Account_Name' => 'Kas',
        'Account_Category' => 'ASSET', 'Is_Active' => 'TRUE',
    ];

    public function fetchAll()
    {
        return collect([$this->account]);
    }

    public function findById(string $id)
    {
        return $id === 'ACC-1' ? $this->account : null;
    }

    public function create(array $data)
    {
        return false;
    }

    public function update(string $id, array $data)
    {
        return false;
    }

    public function delete(string $id)
    {
        return false;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.'-1';
    }
}

class H864TransactionRepository implements TransactionRepositoryInterface
{
    public array $rows = [];

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Transaction_ID', $id);
    }

    public function create(array $data)
    {
        $this->rows[] = $data;

        return $data;
    }

    public function update(string $id, array $data)
    {
        return false;
    }

    public function delete(string $id)
    {
        return false;
    }

    public function generateNewId(string $prefix = 'TRX', int $padding = 6): string
    {
        return $prefix.'-1';
    }
}
