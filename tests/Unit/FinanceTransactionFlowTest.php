<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Finance\FinanceReportService;
use App\Services\Finance\TransactionService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class FinanceTransactionFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 'USR-FINANCE', 'User_ID' => 'USR-FINANCE']));
    }

    public function test_manual_expense_is_saved_as_expense_with_free_text_category(): void
    {
        $repository = Mockery::mock(TransactionRepositoryInterface::class);
        $repository->shouldReceive('findById')->once()->with('TRX-MANUAL-001')->andReturn(null);
        $repository->shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            return ($data['Type'] ?? null) === 'Expense'
                && ($data['Category'] ?? null) === 'Beli ATK kantor'
                && (float) ($data['Amount'] ?? 0) === 125000.0
                && ($data['Reference_Type'] ?? null) === 'Other';
        }))->andReturnUsing(fn ($data) => $data);
        $repository->shouldReceive('clearCache')->once();

        $accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);
        $event->shouldReceive('dispatch')->once();

        $service = new TransactionService($repository, $accountRepository, $event);

        $transaction = $service->create([
            'Transaction_ID' => 'TRX-MANUAL-001',
            'Transaction_Date' => '2026-08-23',
            'Account_ID' => 'ACC-CASH',
            'Type' => 'pengeluaran',
            'Category' => '  Beli ATK kantor  ',
            'Amount' => '125000',
            'Reference_Type' => 'Other',
            'Reference_ID' => '',
            'Description' => 'Manual expense',
        ]);

        $this->assertSame('Expense', $transaction['Type']);
        $this->assertSame('Beli ATK kantor', $transaction['Category']);
        $this->assertSame(125000.0, $transaction['Amount']);
    }

    public function test_cash_flow_counts_income_and_expense_case_insensitively_and_filters_account_code(): void
    {
        $transactionRepository = Mockery::mock(TransactionRepositoryInterface::class);
        $transactionRepository->shouldReceive('fetchAll')->once()->andReturn(collect([
            [
                'Transaction_ID' => 'TRX-OPEN',
                'Transaction_Date' => '2026-07-31',
                'Account_ID' => 'ACC-CASH',
                'Type' => 'Expense',
                'Category' => 'Saldo awal',
                'Amount' => '10000',
                'Is_Active' => 'TRUE',
            ],
            [
                'Transaction_ID' => 'TRX-IN',
                'Transaction_Date' => '2026-08-03',
                'Account_ID' => 'ACC-CASH',
                'Type' => ' income ',
                'Category' => 'Pembayaran SPP',
                'Amount' => '100000',
                'Is_Active' => 'TRUE',
            ],
            [
                'Transaction_ID' => 'TRX-OUT',
                'Transaction_Date' => '2026-08-04',
                'Account_ID' => 'ACC-CASH',
                'Type' => 'Pengeluaran',
                'Category' => 'Beli ATK kantor',
                'Amount' => '25000',
                'Is_Active' => 'TRUE',
            ],
        ]));

        $accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $accountRepository->shouldReceive('fetchAll')->once()->andReturn(collect([
            [
                'Account_ID' => 'ACC-CASH',
                'Account_Code' => '101',
                'Account_Name' => 'Kas Utama',
                'Is_Active' => 'TRUE',
            ],
        ]));

        $service = new FinanceReportService(
            $transactionRepository,
            Mockery::mock(InvoiceRepositoryInterface::class),
            Mockery::mock(PaymentRepositoryInterface::class),
            Mockery::mock(StudentRepositoryInterface::class),
            Mockery::mock(CompanyRepositoryInterface::class),
            $accountRepository
        );

        $cashFlow = $service->getCashFlow('2026-08-01', '2026-08-31', '101', 'ALL');

        $this->assertSame(-10000.0, $cashFlow['opening_balance']);
        $this->assertSame(100000.0, $cashFlow['total_income']);
        $this->assertSame(25000.0, $cashFlow['total_expense']);
        $this->assertSame(65000.0, $cashFlow['closing_balance']);
    }

    public function test_transaction_get_all_normalizes_income_and_expense_aliases(): void
    {
        $repository = Mockery::mock(TransactionRepositoryInterface::class);
        $repository->shouldReceive('fetchAll')->once()->andReturn(collect([
            [
                'Transaction_ID' => 'TRX-IN',
                'Type' => 'pemasukan',
                'Amount' => '75000',
                'Is_Active' => 'TRUE',
            ],
            [
                'Transaction_ID' => 'TRX-OUT',
                'Type' => 'keluar',
                'Amount' => '25000',
                'Is_Active' => 'TRUE',
            ],
        ]));

        $service = new TransactionService(
            $repository,
            Mockery::mock(AccountRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        $transactions = $service->getAll();

        $this->assertSame('Income', $transactions[0]['Type']);
        $this->assertSame(75000.0, $transactions[0]['Amount']);
        $this->assertSame('Expense', $transactions[1]['Type']);
        $this->assertSame(25000.0, $transactions[1]['Amount']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
