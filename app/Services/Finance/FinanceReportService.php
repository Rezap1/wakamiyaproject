<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use Carbon\Carbon;
use Exception;
use App\Support\Finance\Money;
use App\Exceptions\FinancialIntegrityException;
use App\Support\Finance\PaymentStatus;
use App\Support\Reporting\HumanReadableResolver;
use Illuminate\Support\Facades\Log;

class FinanceReportService
{
    protected $transactionRepo, $invoiceRepo, $paymentRepo;
    protected $studentRepo, $companyRepo, $accountRepo;

    public function __construct(
        TransactionRepositoryInterface $transactionRepo,
        InvoiceRepositoryInterface $invoiceRepo,
        PaymentRepositoryInterface $paymentRepo,
        StudentRepositoryInterface $studentRepo,
        CompanyRepositoryInterface $companyRepo,
        AccountRepositoryInterface $accountRepo
    ) {
        $this->transactionRepo = $transactionRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->studentRepo = $studentRepo;
        $this->companyRepo = $companyRepo;
        $this->accountRepo = $accountRepo;
    }

    public function getCashFlow($startDate = null, $endDate = null, $accountId = 'ALL', $category = 'ALL')
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();
        $accountId = $accountId ?? 'ALL';
        $category = $category ?? 'ALL';

        $startDate = $this->normaliseDate($startDate, 'Mulai Tanggal');
        $endDate = $this->normaliseDate($endDate, 'Sampai Tanggal');

        // Server-side Date Boundary Validation
        if ($startDate > $endDate) {
            throw new Exception("Mulai Tanggal ({$startDate}) tidak boleh lebih besar dari Sampai Tanggal ({$endDate}).");
        }

        $allAccounts = collect($this->accountRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');
        
        // Account Filter Validation
        $selectedAccountKeys = [];
        if ($accountId !== 'ALL') {
            $validAcc = $allAccounts->firstWhere('Account_ID', $accountId) ?? $allAccounts->firstWhere('Account_Code', $accountId);
            if (!$validAcc) {
                throw new Exception("Akun Keuangan dengan ID/Kode #{$accountId} tidak ditemukan.");
            }
            $selectedAccountKeys = array_values(array_filter([
                $validAcc['Account_ID'] ?? null,
                $validAcc['Account_Code'] ?? null,
            ]));
        }

        $allTransactions = collect($this->transactionRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');

        // Available Categories list
        $categories = $allTransactions->pluck('Category')->filter()->unique()->values();

        // Base filter helper for Account & Category
        $applyAccountAndCategoryFilter = function($collection) use ($accountId, $category, $selectedAccountKeys) {
            if ($accountId !== 'ALL') {
                $collection = $collection->filter(function($t) use ($selectedAccountKeys) {
                    $acc = trim((string) ($t['Account_ID'] ?? ''));
                    return in_array($acc, $selectedAccountKeys, true);
                });
            }
            if ($category !== 'ALL') {
                $collection = $collection->filter(function($t) use ($category) {
                    return strcasecmp($t['Category'] ?? '', $category) === 0;
                });
            }
            return $collection;
        };

        // 1. OPENING BALANCE: Sum of all transactions BEFORE startDate
        $skippedTransactionCount = 0;
        $priorTransactions = $allTransactions->filter(function($t) use ($startDate, &$skippedTransactionCount) {
            try {
                $date = $this->normaliseDate($t['Transaction_Date'] ?? '', 'Transaction_Date');
                return $date < $startDate;
            } catch (Exception) {
                $skippedTransactionCount++;
                Log::warning('finance.report_skipped_malformed_transaction_date', ['transaction_id' => (string) ($t['Transaction_ID'] ?? 'UNKNOWN')]);
                return false;
            }
        });
        $priorTransactions = $applyAccountAndCategoryFilter($priorTransactions);

        $priorIncome = $this->sumByType($priorTransactions, 'Income');
        $priorExpense = $this->sumByType($priorTransactions, 'Expense');
        $openingBalance = $priorIncome - $priorExpense;

        // 2. PERIOD TRANSACTIONS: startDate <= Transaction_Date <= endDate
        $periodTransactions = $allTransactions->filter(function($t) use ($startDate, $endDate, &$skippedTransactionCount) {
            try {
                $date = $this->normaliseDate($t['Transaction_Date'] ?? '', 'Transaction_Date');
                return $date >= $startDate && $date <= $endDate;
            } catch (Exception) {
                $skippedTransactionCount++;
                Log::warning('finance.report_skipped_malformed_transaction_date', ['transaction_id' => (string) ($t['Transaction_ID'] ?? 'UNKNOWN')]);
                return false;
            }
        });
        $periodTransactions = $applyAccountAndCategoryFilter($periodTransactions);

        $totalIncome = $this->sumByType($periodTransactions, 'Income');
        $totalExpense = $this->sumByType($periodTransactions, 'Expense');
        $netCashFlow = $totalIncome - $totalExpense;
        $closingBalance = $openingBalance + $netCashFlow;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'account_filter' => $accountId,
            'category_filter' => $category,
            'opening_balance' => $openingBalance,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_cash_flow' => $netCashFlow,
            'closing_balance' => $closingBalance,
            'transactions' => $periodTransactions->sortByDesc('Transaction_Date')->values(),
            'skipped_transaction_count' => $skippedTransactionCount,
            'snapshot_source' => 'repository_cache',
            'accounts' => $allAccounts->values(),
            'categories' => $categories
        ];
    }

    private function normaliseDate(mixed $value, string $field): string
    {
        $raw = trim((string) $value);
        $date = Carbon::createFromFormat('!Y-m-d', $raw, config('app.timezone', 'Asia/Jakarta'));
        $errors = Carbon::getLastErrors();
        if (!$date || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $date->format('Y-m-d') !== $raw) {
            throw new Exception("{$field} harus menggunakan format Y-m-d yang valid.");
        }
        return $date->format('Y-m-d');
    }

    private function sumByType($transactions, string $expectedType): float
    {
        return (float) collect($transactions)
            ->filter(function ($transaction) use ($expectedType) {
                return $this->normalizeType($transaction['Type'] ?? '') === $expectedType;
            })
            ->sum(function ($transaction) {
                return Money::value($transaction['Amount'] ?? 0, 'Nominal transaksi');
            });
    }

    private function normalizeType($type): ?string
    {
        $value = strtolower(trim((string) $type));

        if (in_array($value, ['income', 'pemasukan', 'masuk', 'revenue', 'pendapatan'], true)) {
            return 'Income';
        }

        if (in_array($value, ['expense', 'pengeluaran', 'keluar', 'cost', 'biaya', 'beban'], true)) {
            return 'Expense';
        }

        return null;
    }

    public function getOutstandingInvoices($type = null, $studentId = null, $companyId = null)
    {
        $invoices = collect($this->invoiceRepo->fetchAll())
            ->where('Is_Active', '!=', 'FALSE');
            
        if ($type) {
            $invoices = $invoices->where('Invoice_Type', $type);
        }
        
        if ($studentId) {
            $invoices = $invoices->where('Student_ID', $studentId);
        }
        
        if ($companyId) {
            $invoices = $invoices->where('Company_ID', $companyId);
        }
        
        $totalOutstanding = 0;
        $payments = collect($this->paymentRepo->fetchAll())
            ->filter(fn ($payment) => PaymentStatus::verified($payment['Status'] ?? null));
        $students = collect($this->studentRepo->fetchAll())->keyBy('Student_ID');
        $companies = collect($this->companyRepo->fetchAll())->keyBy('Company_ID');
        $invoiceService = app(\App\Services\Finance\InvoiceService::class);
        
        $processedInvoices = $invoices->map(function($inv) use ($payments, $students, $companies, $invoiceService, &$totalOutstanding) {
            $currentStatus = trim($inv['Status'] ?? 'Draft');
            
            if (in_array(strtolower($currentStatus), ['draft', 'cancelled'])) {
                $inv['Remaining_Amount'] = 0;
                return $inv;
            }

            $paidCents = $payments->where('Invoice_ID', $inv['Invoice_ID'] ?? '')
                ->sum(fn ($payment) => Money::cents($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran'));
            $paid = $paidCents / (10 ** Money::SCALE);
            $amount = Money::value($inv['Amount'] ?? 0, 'Invoice Amount');
            $lineItems = $inv['Line_Items'] ?? null;
            if ($lineItems !== null && $lineItems !== '' && $lineItems !== []) {
                [, , , , $canonicalAmount] = $invoiceService->calculateLineItemsTotal($lineItems, $amount);
                if (!Money::equal($amount, $canonicalAmount)) {
                    throw new FinancialIntegrityException("Invoice {$inv['Invoice_ID']} memiliki Amount yang tidak sama dengan total line item.");
                }
                $amount = $canonicalAmount;
            }
            $sisa = max(0, Money::cents($amount) - $paidCents) / (10 ** Money::SCALE);
            
            $inv['Paid_Amount'] = $paid;
            $inv['Remaining_Amount'] = $sisa;

            $dynamicStatus = $invoiceService->resolveDynamicStatus($inv);
            $inv['Status'] = $dynamicStatus;
            $inv['Display_Status'] = $dynamicStatus;

            if ($inv['Invoice_Type'] === 'STUDENT' && isset($inv['Student_ID'])) {
                $inv['Student_Name'] = HumanReadableResolver::studentName($inv['Student_ID'] ?? '', $students);
            } elseif ($inv['Invoice_Type'] === 'COMPANY' && isset($inv['Company_ID'])) {
                $inv['Company_Name'] = HumanReadableResolver::companyName($inv['Company_ID'] ?? '', $companies);
            }

            if ($sisa > 0 && !in_array($dynamicStatus, ['Draft', 'Paid', 'Cancelled'])) {
                $totalOutstanding += $sisa;
            }

            return $inv;
        })->filter(function($inv) {
            return $inv['Remaining_Amount'] > 0 && !in_array($inv['Status'], ['Draft', 'Paid', 'Cancelled']);
        });

        return [
            'total_outstanding' => $totalOutstanding,
            'invoices' => $processedInvoices->sortBy('Due_Date')->values()
        ];
    }
}
