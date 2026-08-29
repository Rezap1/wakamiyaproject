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
        $priorTransactions = $allTransactions->filter(function($t) use ($startDate) {
            $date = $t['Transaction_Date'] ?? '';
            return !empty($date) && $date < $startDate;
        });
        $priorTransactions = $applyAccountAndCategoryFilter($priorTransactions);

        $priorIncome = $this->sumByType($priorTransactions, 'Income');
        $priorExpense = $this->sumByType($priorTransactions, 'Expense');
        $openingBalance = $priorIncome - $priorExpense;

        // 2. PERIOD TRANSACTIONS: startDate <= Transaction_Date <= endDate
        $periodTransactions = $allTransactions->filter(function($t) use ($startDate, $endDate) {
            $date = $t['Transaction_Date'] ?? '';
            return !empty($date) && $date >= $startDate && $date <= $endDate;
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
            'accounts' => $allAccounts->values(),
            'categories' => $categories
        ];
    }

    private function sumByType($transactions, string $expectedType): float
    {
        return (float) collect($transactions)
            ->filter(function ($transaction) use ($expectedType) {
                return $this->normalizeType($transaction['Type'] ?? '') === $expectedType;
            })
            ->sum(function ($transaction) {
                return (float) ($transaction['Amount'] ?? 0);
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
        $payments = collect($this->paymentRepo->fetchAll())->where('Status', 'Verified');
        $students = collect($this->studentRepo->fetchAll());
        $companies = collect($this->companyRepo->fetchAll());
        $invoiceService = app(\App\Services\Finance\InvoiceService::class);
        
        $processedInvoices = $invoices->map(function($inv) use ($payments, $students, $companies, $invoiceService, &$totalOutstanding) {
            $currentStatus = trim($inv['Status'] ?? 'Draft');
            
            if (in_array(strtolower($currentStatus), ['draft', 'cancelled'])) {
                $inv['Remaining_Amount'] = 0;
                return $inv;
            }

            $paid = (float) $payments->where('Invoice_ID', $inv['Invoice_ID'] ?? '')->sum('Amount_Paid');
            $amount = (float) ($inv['Amount'] ?? 0);
            $sisa = max(0.0, $amount - $paid);
            
            $inv['Paid_Amount'] = $paid;
            $inv['Remaining_Amount'] = $sisa;

            $dynamicStatus = $invoiceService->resolveDynamicStatus($inv);
            $inv['Status'] = $dynamicStatus;
            $inv['Display_Status'] = $dynamicStatus;

            if ($inv['Invoice_Type'] === 'STUDENT' && isset($inv['Student_ID'])) {
                $student = $students->firstWhere('Student_ID', $inv['Student_ID']);
                $inv['Student_Name'] = $student['Full_Name'] ?? $inv['Student_ID'];
            } elseif ($inv['Invoice_Type'] === 'COMPANY' && isset($inv['Company_ID'])) {
                $company = $companies->firstWhere('Company_ID', $inv['Company_ID']);
                $inv['Company_Name'] = $company['Company_Name'] ?? $inv['Company_ID'];
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
