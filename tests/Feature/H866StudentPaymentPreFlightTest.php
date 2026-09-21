<?php

namespace Tests\Feature;

require_once __DIR__.'/H864EducationFeeFullPaymentGuardTest.php';

use App\Exceptions\FinancialIntegrityException;
use App\Http\Middleware\RoleMiddleware;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\TransactionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class H866StudentPaymentPreFlightTest extends TestCase
{
    private H864PaymentRepository $payments;

    private H864InvoiceRepository $invoices;

    private H866StudentRepository $students;

    private H866TransactionRepository $transactions;

    private InvoiceService $invoiceService;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('getDefaultTuitionFee')->zeroOrMoreTimes()->andReturn(7_500_000.0);
        $settings->shouldReceive('getCompanyProfile')->zeroOrMoreTimes()->andReturn(['bank' => []]);
        $this->app->instance(SystemSettingService::class, $settings);

        foreach ([ProgramRepositoryInterface::class, BatchRepositoryInterface::class] as $interface) {
            $repository = Mockery::mock($interface);
            $repository->shouldReceive('findById')->zeroOrMoreTimes()->andReturn(null);
            $repository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect());
            $this->app->instance($interface, $repository);
        }

        $this->payments = new H864PaymentRepository;
        $this->invoices = new H864InvoiceRepository;
        $this->students = new H866StudentRepository;
        $this->transactions = new H866TransactionRepository;
        $events = Mockery::mock(EnterpriseEventService::class)->shouldIgnoreMissing();
        $accounts = new H864AccountRepository;

        $this->invoiceService = new InvoiceService(
            $this->invoices,
            $events,
            $this->students,
            new H864CompanyRepository,
            $this->payments,
        );
        $this->paymentService = new PaymentService(
            $this->payments,
            $this->invoices,
            $this->students,
            new H864CompanyRepository,
            $accounts,
            $this->transactions,
            $events,
            new TransactionService(
                $this->transactions,
                $accounts,
                $events,
                $this->invoices,
                $this->payments,
            ),
        );
        $this->app->instance(InvoiceService::class, $this->invoiceService);
        $this->app->instance(PaymentService::class, $this->paymentService);
        $this->app->instance(PaymentRepositoryInterface::class, $this->payments);
        $this->app->instance(StudentRepositoryInterface::class, $this->students);

        $this->asStudent('USR-STU-1');
    }

    public function test_complete_three_payment_lifecycle_reaches_exact_fee_with_one_ledger_per_payment(): void
    {
        $first = $this->submit(2_000_000, '66000000-0000-4000-8000-000000000001');
        $this->assertState('STU-1', 0, 2_000_000, 5_500_000, 'unpaid');
        $this->verify($first['Payment_ID']);
        $this->assertState('STU-1', 2_000_000, 0, 5_500_000, 'partial');

        $this->asStudent('USR-STU-1');
        $second = $this->submit(3_000_000, '66000000-0000-4000-8000-000000000002');
        $this->assertState('STU-1', 2_000_000, 3_000_000, 2_500_000, 'partial');
        $this->verify($second['Payment_ID']);
        $this->assertState('STU-1', 5_000_000, 0, 2_500_000, 'partial');

        $this->asStudent('USR-STU-1');
        $final = $this->submit(2_500_000, '66000000-0000-4000-8000-000000000003');
        $this->verify($final['Payment_ID']);
        $this->assertState('STU-1', 7_500_000, 0, 0, 'paid');

        $this->assertCount(3, $this->payments->rows);
        $this->assertCount(3, $this->transactions->rows);
        foreach ($this->transactions->rows as $ledger) {
            $this->assertSame('Payment', $ledger['Reference_Type']);
            $this->assertSame('Income', $ledger['Type']);
            $this->assertSame('Payment Receipt', $ledger['Category']);
            $this->assertSame('101', $ledger['Account_ID']);
            $this->assertNotEmpty($ledger['Reference_ID']);
            $this->assertNotEmpty($ledger['Transaction_Date']);
        }

        $this->asStudent('USR-STU-1');
        $this->expectException(FinancialIntegrityException::class);
        $this->expectExceptionMessage('sudah lunas');
        $this->submit(1, '66000000-0000-4000-8000-000000000004');
    }

    public function test_clean_amount_boundaries_and_malformed_amounts_fail_closed(): void
    {
        foreach ([500_000, 1_000_000, 2_500_000, 7_500_000] as $index => $amount) {
            $this->payments->rows = [];
            $created = $this->submit($amount, sprintf('66000000-0000-4000-8000-%012d', 100 + $index));
            $this->assertSame((float) $amount, $created['Amount_Paid']);
            $this->assertSame('Waiting Verification', $created['Status']);
        }

        foreach ([7_500_001, 8_000_000, -1, 0, 'invalid', '7.5.0'] as $index => $amount) {
            $this->payments->rows = [];
            try {
                $this->submit($amount, sprintf('66000000-0000-4000-8000-%012d', 200 + $index));
                $this->fail('Invalid or over-capacity amount was accepted: '.var_export($amount, true));
            } catch (\Throwable $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
            $this->assertSame([], $this->payments->rows);
        }
    }

    public function test_pending_reservation_rejection_release_and_cancelled_rows_do_not_consume_capacity(): void
    {
        $this->payments->rows = [
            $this->payment('PAY-VERIFIED', 'STU-1', 5_000_000, 'Verified'),
            $this->payment('PAY-PENDING', 'STU-1', 2_000_000, 'Waiting Verification'),
            $this->payment('PAY-REJECTED', 'STU-1', 1_000_000, 'Rejected'),
            $this->payment('PAY-CANCELLED', 'STU-1', 1_000_000, 'Cancelled'),
        ];
        $this->assertState('STU-1', 5_000_000, 2_000_000, 500_000, 'partial');

        try {
            $this->submit(1_000_000, '66000000-0000-4000-8000-000000000301');
            $this->fail('Pending reservation must reject an over-capacity second request.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertStringContainsString('Rp500.000', $exception->getMessage());
        }

        $accepted = $this->submit(500_000, '66000000-0000-4000-8000-000000000302');
        $this->assertSame(500_000.0, $accepted['Amount_Paid']);
        $this->assertState('STU-1', 5_000_000, 2_500_000, 0, 'partial');
    }

    public function test_replay_and_student_scoped_capacity_do_not_duplicate_or_cross_contaminate(): void
    {
        $key = '66000000-0000-4000-8000-000000000401';
        $first = $this->submit(7_000_000, $key, 'proofs/same.jpg');
        $replay = $this->submit(7_000_000, $key, 'proofs/same.jpg');
        $this->assertSame($first['Payment_ID'], $replay['Payment_ID']);
        $this->assertCount(1, $this->payments->rows);

        $this->asStudent('USR-STU-2');
        $other = $this->submit(7_500_000, '66000000-0000-4000-8000-000000000402');
        $this->assertSame('STU-2', $other['Student_ID']);
        $this->assertState('STU-1', 0, 7_000_000, 500_000, 'unpaid');
        $this->assertState('STU-2', 0, 7_500_000, 0, 'unpaid');

        $this->asStudent('USR-STU-1');
        $this->expectException(FinancialIntegrityException::class);
        $this->submit(1_000_000, '66000000-0000-4000-8000-000000000403');
    }

    public function test_need_revision_reupload_keeps_same_payment_and_same_reservation(): void
    {
        $payment = $this->submit(2_000_000, '66000000-0000-4000-8000-000000000501', 'payments/original.jpg');
        $this->asFinance();
        $this->paymentService->verifyPayment($payment['Payment_ID'], 'ignored', 'Need Revision', 'Bukti buram');
        $this->assertState('STU-1', 0, 2_000_000, 5_500_000, 'unpaid');

        $this->asStudent('USR-STU-1');
        $result = $this->paymentService->replaceSelfServiceProof($payment['Payment_ID'], 'payments/replacement.pdf');
        $this->assertSame('payments/original.jpg', $result['previous_proof']);
        $this->assertSame($payment['Payment_ID'], $result['payment']['Payment_ID']);
        $this->assertSame('Waiting Verification', $result['payment']['Status']);
        $this->assertSame('payments/replacement.pdf', $result['payment']['Proof_File']);
        $this->assertCount(1, $this->payments->rows);
        $this->assertState('STU-1', 0, 2_000_000, 5_500_000, 'unpaid');
    }

    public function test_need_revision_reupload_rejects_wrong_owner_wrong_state_and_unsafe_path(): void
    {
        $this->payments->rows[] = $this->payment('PAY-REVISION', 'STU-1', 500_000, 'Need Revision');

        $this->asStudent('USR-STU-2');
        try {
            $this->paymentService->replaceSelfServiceProof('PAY-REVISION', 'payments/new.jpg');
            $this->fail('Another student must not replace proof.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('bukan milik', $exception->getMessage());
        }

        $this->asStudent('USR-STU-1');
        try {
            $this->paymentService->replaceSelfServiceProof('PAY-REVISION', '../escape.php');
            $this->fail('Unsafe proof path must fail closed.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertStringContainsString('tidak valid', $exception->getMessage());
        }

        $this->payments->update('PAY-REVISION', ['Status' => 'Verified']);
        $this->expectException(FinancialIntegrityException::class);
        $this->expectExceptionMessage('Need Revision');
        $this->paymentService->replaceSelfServiceProof('PAY-REVISION', 'payments/new.jpg');
    }

    public function test_student_cannot_verify_and_repeated_finance_verification_creates_no_second_ledger(): void
    {
        $payment = $this->submit(500_000, '66000000-0000-4000-8000-000000000601');
        try {
            $this->paymentService->verifyPayment($payment['Payment_ID'], 'forged', 'Verified');
            $this->fail('Student verification must fail.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('mutasi keuangan', $exception->getMessage());
        }

        $this->verify($payment['Payment_ID']);
        try {
            $this->paymentService->verifyPayment($payment['Payment_ID'], 'ignored', 'Verified');
            $this->fail('Repeated verification must fail.');
        } catch (FinancialIntegrityException $exception) {
            $this->assertStringContainsString('sudah terverifikasi', $exception->getMessage());
        }
        $this->assertCount(1, $this->transactions->rows);
        $this->assertState('STU-1', 500_000, 0, 7_000_000, 'partial');
    }

    public function test_http_upload_validation_persistence_and_retry_cleanup(): void
    {
        $this->withoutMiddleware(RoleMiddleware::class);
        Storage::fake('local');
        $key = '66000000-0000-4000-8000-000000000701';
        $payload = [
            'Amount_Paid' => 500_000,
            'Sender_Name' => 'Student One',
            'Transfer_Date' => '2026-09-21',
            'Payment_Method' => 'TRANSFER',
            'Idempotency_Key' => $key,
            'Proof_File' => UploadedFile::fake()->image('proof.jpg'),
        ];

        $this->post(route('student.billing.self-service.pay'), $payload)->assertRedirect(route('student.billing.index'));
        $this->assertCount(1, $this->payments->rows);
        $storedPath = $this->payments->rows[0]['Proof_File'];
        Storage::disk('local')->assertExists($storedPath);

        $payload['Proof_File'] = UploadedFile::fake()->image('retry.jpg');
        $this->post(route('student.billing.self-service.pay'), $payload)->assertSessionHas('error');
        $this->assertCount(1, $this->payments->rows);
        $this->assertCount(1, Storage::disk('local')->allFiles('payments'));

        $paymentId = $this->payments->rows[0]['Payment_ID'];
        $this->payments->update($paymentId, ['Status' => 'Need Revision', 'Notes' => 'Ganti bukti']);
        $this->post(route('student.billing.payment-proof.replace', $paymentId), [
            'Proof_File' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('student.billing.index'));
        $replacementPath = $this->payments->rows[0]['Proof_File'];
        $this->assertNotSame($storedPath, $replacementPath);
        Storage::disk('local')->assertMissing($storedPath);
        Storage::disk('local')->assertExists($replacementPath);
        $this->assertSame('Waiting Verification', $this->payments->rows[0]['Status']);
        $this->assertCount(1, $this->payments->rows);

        $invalid = $payload;
        $invalid['Idempotency_Key'] = '66000000-0000-4000-8000-000000000702';
        $invalid['Proof_File'] = UploadedFile::fake()->create('payload.php', 10, 'application/x-php');
        $this->post(route('student.billing.self-service.pay'), $invalid)->assertSessionHasErrors('Proof_File');
        $this->assertCount(1, $this->payments->rows);
    }

    public function test_need_revision_ui_exposes_mobile_safe_reupload_on_same_payment_id(): void
    {
        $payment = $this->payment('PAY-REVISION-UI', 'STU-1', 500_000, 'Need Revision');
        $payment['Notes'] = 'Mohon unggah ulang bukti yang jelas.';
        $html = view('student.billing.index', [
            'myInvoices' => collect(),
            'myPayments' => collect([$payment]),
            'selfServicePayments' => collect([$payment]),
            'totalOutstanding' => 0,
            'totalPaid' => 0,
            'totalBilled' => 0,
            'categoryBreakdown' => collect(),
            'bank' => [],
            'educationPaymentState' => [
                'status' => 'unpaid', 'remaining_payable' => 7_000_000,
            ],
        ])->render();

        $this->assertStringContainsString('PAY-REVISION-UI', $html);
        $this->assertStringContainsString('Kirim Ulang Bukti', $html);
        $this->assertStringContainsString('Mohon unggah ulang bukti yang jelas.', $html);
        $this->assertStringContainsString('multipart/form-data', $html);
    }

    private function submit(mixed $amount, string $key, string $proof = 'payments/proof.jpg'): array
    {
        return $this->paymentService->submitPayment([
            'Self_Service' => true,
            'Amount_Paid' => $amount,
            'Payment_Method' => 'TRANSFER',
            'Sender_Name' => 'Student One',
            'Transfer_Date' => '2026-09-21',
            'Idempotency_Key' => $key,
            'Proof_File' => $proof,
            'Proof_Image' => $proof,
        ]);
    }

    private function verify(string $paymentId): void
    {
        $this->asFinance();
        $this->paymentService->verifyPayment($paymentId, 'ignored', 'Verified');
    }

    private function assertState(string $studentId, float $verified, float $pending, float $remaining, string $status): void
    {
        $state = $this->invoiceService->getStudentEducationPaymentState($studentId);
        $this->assertSame($verified, $state['verified_paid']);
        $this->assertSame($pending, $state['pending_reserved']);
        $this->assertSame($remaining, $state['remaining_payable']);
        $this->assertSame($status, $state['status']);
    }

    private function payment(string $id, string $studentId, float $amount, string $status): array
    {
        return [
            'Payment_ID' => $id,
            'Student_ID' => $studentId,
            'Invoice_ID' => '',
            'Payment_Type' => 'STUDENT_SELF_SERVICE',
            'Amount_Paid' => $amount,
            'Payment_Method' => 'TRANSFER',
            'Payment_Date' => '2026-09-21',
            'Proof_File' => 'payments/proof.jpg',
            'Status' => $status,
            'Is_Active' => 'TRUE',
        ];
    }

    private function asStudent(string $userId): void
    {
        $this->actingAs(new GenericUser([
            'id' => $userId, 'User_ID' => $userId, 'Role' => 'STUDENT',
        ]));
    }

    private function asFinance(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-FIN', 'User_ID' => 'USR-FIN', 'Role' => 'FINANCE',
        ]));
    }
}

class H866StudentRepository implements StudentRepositoryInterface
{
    private array $rows = [
        ['Student_ID' => 'STU-1', 'User_ID' => 'USR-STU-1', 'Full_Name' => 'Student One', 'Program_ID' => '', 'Batch_ID' => '', 'Is_Active' => 'TRUE'],
        ['Student_ID' => 'STU-2', 'User_ID' => 'USR-STU-2', 'Full_Name' => 'Student Two', 'Program_ID' => '', 'Batch_ID' => '', 'Is_Active' => 'TRUE'],
    ];

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

class H866TransactionRepository extends H864TransactionRepository
{
    public function clearCache(): void {}
}
