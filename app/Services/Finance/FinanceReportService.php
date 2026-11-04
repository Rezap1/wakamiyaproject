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

        // Inactive accounts remain valid references for historical ledger entries.
        $allAccounts = collect($this->accountRepo->fetchAll());
        $accountIndex = collect();
        foreach ($allAccounts as $account) {
            foreach (['Account_ID', 'Account_Code'] as $field) {
                $key = trim((string) ($account[$field] ?? ''));
                if ($key === '') { continue; }
                if ($accountIndex->has($key) && ($accountIndex[$key]['Account_ID'] ?? null) !== ($account['Account_ID'] ?? null)) {
                    throw new Exception('Referensi akun ambigu: '.$key.'. Rekonsiliasi master akun diperlukan.');
                }
                $accountIndex->put($key, $account);
            }
        }
        
        // Account Filter Validation
        $selectedAccountKeys = [];
        if ($accountId !== 'ALL') {
            $validAcc = $accountIndex->get(trim((string) $accountId));
            if (!$validAcc) {
                throw new Exception("Akun Keuangan dengan ID/Kode #{$accountId} tidak ditemukan.");
            }
            $selectedAccountKeys = array_values(array_filter(array_map(fn ($id) => trim((string) $id), [
                $validAcc['Account_ID'] ?? null,
                $validAcc['Account_Code'] ?? null,
            ]), fn ($id) => $id !== ''));
        }

        $allTransactions = collect($this->transactionRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');
        $duplicateIds = $allTransactions->groupBy('Transaction_ID')->filter(fn ($rows) => $rows->count() > 1)->keys();
        if ($duplicateIds->isNotEmpty()) {
            throw new Exception('ID transaksi duplikat; laporan tidak dapat direkonsiliasi: '.$duplicateIds->implode(', '));
        }
        $integrityIssues = [];
        // Distinct ledger IDs can still point to the same idempotent business event.
        $allTransactions->filter(fn ($row) => in_array(strtolower(trim((string) ($row['Reference_Type'] ?? ''))), ['payment', 'paymentreversal', 'payroll'], true)
                && trim((string) ($row['Reference_ID'] ?? '')) !== '')
            ->groupBy(fn ($row) => strtolower(trim($row['Reference_Type'])).' / '.trim($row['Reference_ID']))
            ->filter(fn ($rows) => $rows->count() > 1)
            ->each(function ($rows, $reference) use (&$integrityIssues) {
                $integrityIssues[] = 'Sumber tercatat berulang: '.$reference.' ['.$rows->pluck('Transaction_ID')->implode(', ').']';
            });
        $skippedTransactionCount = 0;
        $allTransactions = $allTransactions->map(function ($row) use ($accountIndex, &$integrityIssues, &$skippedTransactionCount) {
            $id = (string) ($row['Transaction_ID'] ?? 'UNKNOWN');
            try {
                $row['Transaction_Date'] = $this->normaliseDate($row['Transaction_Date'] ?? '', 'Transaction_Date');
            } catch (Exception) {
                $skippedTransactionCount++;
                $integrityIssues[] = 'Tanggal transaksi tidak valid: '.$id;
                Log::warning('finance.report_skipped_malformed_transaction_date', ['transaction_id' => $id]);
                return null;
            }
            $type = $this->normalizeType($row['Type'] ?? '');
            if ($type === null) {
                throw new Exception('Tipe transaksi tidak valid: '.$id.'. Rekonsiliasi diperlukan.');
            }
            $row['Type'] = $type;
            $accountId = trim((string) ($row['Account_ID'] ?? '')) ?: trim((string) ($row['Account_Code'] ?? ''));
            $row['Account_Name'] = HumanReadableResolver::accountName($accountId, $accountIndex);
            if (!$accountIndex->has($accountId)) {
                $integrityIssues[] = 'Referensi akun tidak valid: '.$id.' / '.($accountId ?: '(kosong)');
                $row['Account_Name'] = 'Referensi akun tidak valid ['.($accountId ?: 'kosong').']';
            }
            $referenceId = trim((string) ($row['Reference_ID'] ?? ''));
            $label = match (strtolower(trim((string) ($row['Reference_Type'] ?? '')))) {
                'payment' => 'Penerimaan pembayaran',
                'paymentreversal' => 'Pembatalan pembayaran',
                'payroll' => 'Pembayaran gaji',
                default => trim((string) ($row['Description'] ?? '')) ?: 'Transaksi',
            };
            $row['Report_Description'] = $label.($referenceId !== '' ? ' | Referensi: '.$referenceId : '');
            return $row;
        })->filter()->values();

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
        $priorTransactions = $allTransactions->filter(fn ($t) => $t['Transaction_Date'] < $startDate);
        $priorTransactions = $applyAccountAndCategoryFilter($priorTransactions);

        $priorIncome = $this->sumByType($priorTransactions, 'Income');
        $priorExpense = $this->sumByType($priorTransactions, 'Expense');
        $openingBalance = $priorIncome - $priorExpense;

        // 2. PERIOD TRANSACTIONS: startDate <= Transaction_Date <= endDate
        $periodTransactions = $allTransactions->filter(fn ($t) => $t['Transaction_Date'] >= $startDate && $t['Transaction_Date'] <= $endDate);
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
            'integrity_issues' => $integrityIssues,
            'snapshot_source' => 'runtime_repository',
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
        $payments = collect($this->paymentRepo->fetchAll());
        $students = collect($this->studentRepo->fetchAll())->keyBy('Student_ID');
        $companies = collect($this->companyRepo->fetchAll())->keyBy('Company_ID');
        $invoiceService = app(\App\Services\Finance\InvoiceService::class);
        
        $processedInvoices = $invoices->map(function($inv) use ($payments, $students, $companies, $invoiceService, &$totalOutstanding) {
            $currentStatus = trim($inv['Status'] ?? 'Draft');
            
            if (in_array(strtolower($currentStatus), ['draft', 'cancelled'])) {
                $inv['Remaining_Amount'] = 0;
                return $inv;
            }

            $inv = $invoiceService->formatInvoiceRecord($inv, null, $payments);
            $studentId = trim((string) ($inv['Student_ID'] ?? ''));
            $student = $studentId !== '' ? $students->get($studentId) : null;
            $inv = $invoiceService->applyEducationCollectibility(
                $inv,
                $student ? (array) $student : null,
            );
            $sisa = (float) $inv['Remaining_Amount'];
            $dynamicStatus = $inv['Status'];

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
