<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\TransactionController;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Services\Finance\AccountService;
use App\Services\Finance\TransactionPresentationService;
use App\Services\Finance\TransactionService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class FinanceTransactionPresentationHardeningTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_regular_invoice_linked_payment_transaction_detail_is_readable(): void
    {
        $detail = $this->presenter()->presentDetail($this->paymentTransaction());

        $this->assertSame('RCT-2026-0001', $detail['source']['label']);
        $this->assertSame('Cita Maharani', $detail['party']['name']);
        $this->assertSame('Rp 1.000.000', $detail['amount_label']);
        $this->assertSame('Transfer Bank', $detail['payment']['method_label']);
        $this->assertSame('Terverifikasi', $detail['payment']['status_label']);
        $this->assertSame('INV-2026-001', $detail['invoice']['number']);
        $this->assertTrue($detail['evidence']['available']);
        $this->assertFalse($detail['evidence']['is_pdf']);
    }

    public function test_self_service_payment_without_invoice_renders_without_invoice_dereference(): void
    {
        $detail = $this->presenter(
            transactions: [$this->selfServiceTransaction()],
            payments: [$this->selfServicePayment(['Proof_File' => 'payments/self-service.pdf'])],
            invoices: []
        )->presentDetail($this->selfServiceTransaction());

        $html = view('finance.transactions.show', [
            'presentation' => $detail,
            'canAccessPayments' => true,
            'canAccessInvoices' => true,
            'canMutateTransactions' => true,
        ])->render();

        $this->assertSame('Pembayaran Mandiri Siswa', $detail['source']['type_label']);
        $this->assertTrue($detail['payment']['invoice_optional']);
        $this->assertNull($detail['invoice']);
        $this->assertStringContainsString('Pembayaran mandiri ini valid tanpa invoice', $html);
        $this->assertStringContainsString('Buka PDF', $html);
        $this->assertStringNotContainsString('Undefined variable $title', $html);
    }

    public function test_payment_proof_pdf_image_and_absent_fallbacks_are_safe(): void
    {
        $presenter = $this->presenter();

        $image = $presenter->paymentEvidence($this->payment(['Proof_File' => 'payments/proof.jpg']));
        $pdf = $presenter->paymentEvidence($this->payment(['Proof_File' => 'payments/proof.pdf']));
        $none = $presenter->paymentEvidence($this->payment(['Proof_File' => '']));
        $unsafe = $presenter->paymentEvidence($this->payment(['Proof_File' => '../secret.txt']));

        $this->assertTrue($image['available']);
        $this->assertFalse($image['is_pdf']);
        $this->assertStringContainsString('/finance/payments/PAY-INV/proof', $image['download_url']);
        $this->assertTrue($pdf['available']);
        $this->assertTrue($pdf['is_pdf']);
        $this->assertFalse($none['available']);
        $this->assertSame('Bukti pembayaran tidak tersedia.', $none['message']);
        $this->assertFalse($unsafe['available']);
    }

    public function test_missing_invoice_student_and_account_do_not_crash_detail(): void
    {
        $detail = $this->presenter(
            transactions: [$this->paymentTransaction(['Account_ID' => 'ACC-MISSING'])],
            payments: [$this->payment(['Invoice_ID' => 'INV-MISSING', 'Student_ID' => 'STU-MISSING', 'Proof_File' => ''])],
            invoices: [],
            students: [],
            accounts: []
        )->presentDetail($this->paymentTransaction(['Account_ID' => 'ACC-MISSING']));

        $html = view('finance.transactions.show', [
            'presentation' => $detail,
            'canAccessPayments' => true,
            'canAccessInvoices' => true,
            'canMutateTransactions' => true,
        ])->render();

        $this->assertSame('Data siswa tidak ditemukan', $detail['party']['name']);
        $this->assertSame('Akun tidak ditemukan', $detail['account']['label']);
        $this->assertTrue($detail['payment']['invoice_missing']);
        $this->assertStringContainsString('Invoice terkait tidak ditemukan', $html);
        $this->assertStringContainsString('Bukti pembayaran tidak tersedia.', $html);
    }

    public function test_missing_payment_and_legacy_transaction_remain_readable(): void
    {
        $missingPayment = $this->presenter(
            transactions: [$this->paymentTransaction(['Reference_ID' => 'PAY-MISSING'])],
            payments: []
        )->presentDetail($this->paymentTransaction(['Reference_ID' => 'PAY-MISSING']));

        $manual = $this->presenter(
            transactions: [$this->manualTransaction()],
            payments: []
        )->presentDetail($this->manualTransaction());

        $this->assertSame('Sumber pembayaran tidak ditemukan. Transaksi inti tetap ditampilkan sebagai data ledger.', $missingPayment['legacy_warning']);
        $this->assertSame('Transaksi Manual', $manual['source']['type_label']);
        $this->assertSame('Transaksi legacy/manual tanpa nomor referensi sumber.', $manual['legacy_warning']);
    }

    public function test_reversal_and_original_transactions_show_audit_linkage(): void
    {
        $original = $this->paymentTransaction();
        $reversal = $this->paymentTransaction([
            'Transaction_ID' => 'TRX-REV-1',
            'Type' => 'Expense',
            'Reference_Type' => 'PaymentReversal',
            'Description' => 'Koreksi pembayaran',
        ]);
        $presenter = $this->presenter(transactions: [$original, $reversal]);

        $originalDetail = $presenter->presentDetail($original);
        $reversalDetail = $presenter->presentDetail($reversal);

        $this->assertTrue($originalDetail['reversal']['has_reversal']);
        $this->assertSame('TRX-REV-1', $originalDetail['reversal']['transaction']['id']);
        $this->assertTrue($reversalDetail['reversal']['is_reversal']);
        $this->assertSame('TRX-PAY-1', $reversalDetail['reversal']['transaction']['id']);
        $this->assertSame('Pembalikan/Koreksi', $reversalDetail['type_label']);
    }

    public function test_history_presentations_use_human_context_not_raw_ids_as_primary_fields(): void
    {
        $items = $this->presenter()->presentCollection([$this->paymentTransaction(), $this->manualTransaction()]);

        $this->assertSame('Cita Maharani', $items[0]['party']['name']);
        $this->assertSame('RCT-2026-0001', $items[0]['source']['label']);
        $this->assertSame('Pemasukan', $items[0]['type_label']);
        $this->assertSame('Rp 1.000.000', $items[0]['amount_label']);
        $this->assertNotSame('STU-1', $items[0]['party']['name']);
        $this->assertSame('Transaksi Manual', $items[1]['source']['label']);
    }

    public function test_unknown_transaction_returns_404_not_redirect_or_500(): void
    {
        $controller = new TransactionController(
            Mockery::mock(TransactionService::class)->shouldReceive('getById')->once()->with('TRX-MISSING')->andReturn(null)->getMock(),
            Mockery::mock(AccountService::class),
            Mockery::mock(TransactionPresentationService::class)
        );

        $this->expectException(NotFoundHttpException::class);

        $controller->show('TRX-MISSING');
    }

    public function test_transaction_history_presenter_uses_bounded_snapshots(): void
    {
        $transactions = [];
        for ($i = 1; $i <= 100; $i++) {
            $transactions[] = $this->paymentTransaction([
                'Transaction_ID' => 'TRX-PAY-' . $i,
                'Reference_ID' => 'PAY-INV',
            ]);
        }

        $presenter = $this->presenter(
            transactions: $transactions,
            payments: [$this->payment()],
            exactReads: true
        );

        $presented = $presenter->presentCollection($transactions);

        $this->assertCount(100, $presented);
        $this->assertSame('Cita Maharani', $presented->first()['party']['name']);
    }

    public function test_unauthorized_student_cannot_open_finance_transaction_route(): void
    {
        $roleService = Mockery::mock(\App\Services\Core\RoleService::class);
        $roleService->shouldReceive('getRoleById')->with('ROLE-STUDENT')->andReturn([
            'Role_ID' => 'ROLE-STUDENT',
            'Role_Name' => 'STUDENT',
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(\App\Services\Core\RoleService::class, $roleService);

        $this->actingAs(new GenericUser([
            'id' => 'USR-STUDENT',
            'User_ID' => 'USR-STUDENT',
            'Role_ID' => 'ROLE-STUDENT',
        ]));

        $this->get(route('transactions.show', 'TRX-PAY-1'))->assertForbidden();
    }

    private function presenter(
        array $transactions = [],
        array $payments = [],
        array $invoices = [],
        array $students = [],
        array $companies = [],
        array $accounts = [],
        array $payrolls = [],
        array $users = [],
        array $employees = [],
        bool $exactReads = false
    ): TransactionPresentationService {
        $transactions = $transactions ?: [$this->paymentTransaction()];
        $payments = $payments ?: [$this->payment()];
        $invoices = $invoices ?: [$this->invoice()];
        $students = $students ?: [$this->student()];
        $accounts = $accounts ?: [$this->account()];
        $users = $users ?: [
            ['User_ID' => 'USR-FIN', 'Full_Name' => 'Finance WMS'],
        ];

        return new TransactionPresentationService(
            $this->repo(PaymentRepositoryInterface::class, 'getAll', $payments, $exactReads),
            $this->repo(InvoiceRepositoryInterface::class, 'getAll', $invoices, $exactReads),
            $this->repo(StudentRepositoryInterface::class, 'fetchAll', $students, $exactReads),
            $this->repo(CompanyRepositoryInterface::class, 'fetchAll', $companies, $exactReads),
            $this->repo(AccountRepositoryInterface::class, 'fetchAll', $accounts, $exactReads),
            $this->repo(PayrollRepositoryInterface::class, 'getAll', $payrolls, $exactReads),
            $this->repo(TransactionRepositoryInterface::class, 'fetchAll', $transactions, $exactReads),
            $this->repo(UserRepositoryInterface::class, 'fetchAll', $users, $exactReads),
            $this->repo(EmployeeRepositoryInterface::class, 'fetchAll', $employees, $exactReads)
        );
    }

    private function repo(string $interface, string $method, array $rows, bool $exactReads)
    {
        $repo = Mockery::mock($interface);
        $expectation = $repo->shouldReceive($method)->andReturn(collect($rows));
        $exactReads ? $expectation->once() : $expectation->zeroOrMoreTimes();

        return $repo;
    }

    private function paymentTransaction(array $overrides = []): array
    {
        return array_merge([
            'Transaction_ID' => 'TRX-PAY-1',
            'Transaction_Date' => '2026-09-05',
            'Account_ID' => 'ACC-1',
            'Type' => 'Income',
            'Category' => 'Payment Receipt',
            'Amount' => 1000000,
            'Reference_Type' => 'Payment',
            'Reference_ID' => 'PAY-INV',
            'Description' => 'Pembayaran pendidikan',
            'Created_By' => 'USR-FIN',
            'Updated_By' => 'USR-FIN',
            'Created_At' => '2026-09-05 01:13:00',
            'Updated_At' => '2026-09-05 01:15:00',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }

    private function selfServiceTransaction(array $overrides = []): array
    {
        return $this->paymentTransaction(array_merge([
            'Transaction_ID' => 'TRX-PAY-SELF',
            'Reference_ID' => 'PAY-SELF',
            'Description' => 'Pembayaran mandiri terverifikasi',
        ], $overrides));
    }

    private function manualTransaction(array $overrides = []): array
    {
        return array_merge([
            'Transaction_ID' => 'TRX-MANUAL',
            'Transaction_Date' => '2026-09-05',
            'Account_ID' => 'ACC-1',
            'Type' => 'Expense',
            'Category' => 'Operasional',
            'Amount' => 250000,
            'Reference_Type' => 'Other',
            'Reference_ID' => '',
            'Description' => 'Pembelian ATK',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }

    private function payment(array $overrides = []): array
    {
        return array_merge([
            'Payment_ID' => 'PAY-INV',
            'Invoice_ID' => 'INV-2026-001',
            'Student_ID' => 'STU-1',
            'Payment_Type' => 'STUDENT',
            'Amount_Paid' => 1000000,
            'Payment_Date' => '2026-09-05',
            'Payment_Method' => 'Transfer',
            'Sender_Name' => 'Orang Tua Cita',
            'Receipt_Number' => 'RCT-2026-0001',
            'Reference_Number' => 'BNK-001',
            'Proof_File' => 'payments/proof.jpg',
            'Status' => 'Verified',
            'Verified_By' => 'USR-FIN',
            'Verified_At' => '2026-09-05 01:15:00',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }

    private function selfServicePayment(array $overrides = []): array
    {
        return $this->payment(array_merge([
            'Payment_ID' => 'PAY-SELF',
            'Invoice_ID' => '',
            'Payment_Type' => 'STUDENT_SELF_SERVICE',
            'Receipt_Number' => 'RCT-SELF-2026-0001',
        ], $overrides));
    }

    private function invoice(array $overrides = []): array
    {
        return array_merge([
            'Invoice_ID' => 'INV-2026-001',
            'Invoice_Number' => 'INV-2026-001',
            'Student_ID' => 'STU-1',
            'Amount' => 1500000,
            'Remaining_Amount' => 500000,
            'Status' => 'Partial Paid',
            'Due_Date' => '2026-09-30',
        ], $overrides);
    }

    private function student(array $overrides = []): array
    {
        return array_merge([
            'Student_ID' => 'STU-1',
            'Full_Name' => 'Cita Maharani',
            'Student_Number' => 'NIS-001',
            'Class_Name' => 'Kelas Sakura',
            'Program_Name' => 'Bahasa Jepang',
        ], $overrides);
    }

    private function account(array $overrides = []): array
    {
        return array_merge([
            'Account_ID' => 'ACC-1',
            'Account_Code' => '101',
            'Account_Name' => 'Kas Utama',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }
}
