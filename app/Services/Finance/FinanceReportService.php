<?php
namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use Carbon\Carbon;

class FinanceReportService
{
    protected $transactionRepo, $invoiceRepo, $paymentRepo;
    protected $studentRepo, $companyRepo;

    public function __construct(
        TransactionRepositoryInterface $transactionRepo,
        InvoiceRepositoryInterface $invoiceRepo,
        PaymentRepositoryInterface $paymentRepo,
        StudentRepositoryInterface $studentRepo,
        CompanyRepositoryInterface $companyRepo
    ) {
        $this->transactionRepo = $transactionRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->studentRepo = $studentRepo;
        $this->companyRepo = $companyRepo;
    }

    public function getCashFlow($startDate = null, $endDate = null)
    {
        $transactions = collect($this->transactionRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');
        
        if ($startDate && $endDate) {
            $transactions = $transactions->filter(function($t) use ($startDate, $endDate) {
                $date = $t['Transaction_Date'] ?? '';
                return $date >= $startDate && $date <= $endDate;
            });
        }

        $income = $transactions->where('Type', 'Income')->sum('Amount');
        $expense = $transactions->where('Type', 'Expense')->sum('Amount');
        $net = $income - $expense;

        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'net_cash_flow' => $net,
            'transactions' => $transactions->sortByDesc('Transaction_Date')->values()
        ];
    }

    public function getOutstandingInvoices($type = null, $studentId = null, $companyId = null)
    {
        $invoices = collect($this->invoiceRepo->fetchAll())
            ->where('Is_Active', '!=', 'FALSE')
            ->whereIn('Status', ['Waiting Payment', 'Partial Paid']);
            
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
        
        $processedInvoices = $invoices->map(function($inv) use ($payments, $students, $companies, &$totalOutstanding) {
            $paid = $payments->where('Invoice_ID', $inv['Invoice_ID'])->sum('Amount_Paid');
            $sisa = max(0, (float)($inv['Amount'] ?? 0) - $paid);
            $inv['Paid_Amount'] = $paid;
            $inv['Remaining_Amount'] = $sisa;
            
            if ($inv['Invoice_Type'] === 'STUDENT' && isset($inv['Student_ID'])) {
                $student = $students->firstWhere('Student_ID', $inv['Student_ID']);
                $inv['Student_Name'] = $student['Full_Name'] ?? $inv['Student_ID'];
            } elseif ($inv['Invoice_Type'] === 'COMPANY' && isset($inv['Company_ID'])) {
                $company = $companies->firstWhere('Company_ID', $inv['Company_ID']);
                $inv['Company_Name'] = $company['Company_Name'] ?? $inv['Company_ID'];
            }

            $totalOutstanding += $sisa;
            return $inv;
        })->filter(function($inv) {
            return $inv['Remaining_Amount'] > 0;
        });

        return [
            'total_outstanding' => $totalOutstanding,
            'invoices' => $processedInvoices->sortBy('Due_Date')->values()
        ];
    }
}
