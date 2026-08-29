<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\FinanceReportService;

class ReportController extends Controller
{
    use \App\Traits\Exportable;

    protected $reportService;
    protected $exportDateField = 'Transaction_Date';

    public function __construct(FinanceReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        return view('finance.reports.index');
    }

    public function cashFlow(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
            $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
            $accountId = $request->input('account_id', 'ALL');
            $category = $request->input('category', 'ALL');
            
            $data = $this->reportService->getCashFlow($startDate, $endDate, $accountId, $category);
            
            return view('finance.reports.cash_flow', array_merge($data, compact('startDate', 'endDate', 'accountId', 'category')));
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function outstandingInvoices(Request $request)
    {
        $type = $request->input('type');
        $studentId = $request->input('student_id');
        $companyId = $request->input('company_id');
        
        $data = $this->reportService->getOutstandingInvoices($type, $studentId, $companyId);
        
        return view('finance.reports.outstanding', array_merge($data, compact('type', 'studentId', 'companyId')));
    }

    public function getExportConfig(Request $request)
    {
        $reportType = $request->input('report_type', 'cash_flow');
        
        if ($reportType === 'outstanding') {
            $type = $request->input('type');
            $studentId = $request->input('student_id');
            $companyId = $request->input('company_id');
            $data = $this->reportService->getOutstandingInvoices($type, $studentId, $companyId);
            
            $this->exportDateField = 'Invoice_Date';

            return [
                'moduleName' => 'Laporan Piutang',
                'data' => collect($data['invoices'] ?? collect([]))->values(),
                'pdfView' => 'pdf.generic_table',
                'headers' => ['Jatuh Tempo', 'No Tagihan', 'Tujuan', 'Status', 'Total Tagihan', 'Sudah Dibayar', 'Sisa Piutang'],
                'mapRow' => function($row) {
                    $tujuan = '-';
                    if (($row['Invoice_Type'] ?? '') === 'STUDENT') {
                        $tujuan = 'Siswa: ' . ($row['Student_Name'] ?? $row['Student_ID'] ?? '-');
                    } elseif (($row['Invoice_Type'] ?? '') === 'COMPANY') {
                        $tujuan = 'Perusahaan: ' . ($row['Company_Name'] ?? $row['Company_ID'] ?? '-');
                    } else {
                        $tujuan = $row['Invoice_Type'] ?? '-';
                    }
                    
                    return [
                        $row['Due_Date'] ?? '-',
                        $row['Invoice_ID'] ?? '-',
                        $tujuan,
                        $row['Status'] ?? '-',
                        'Rp ' . number_format((float)($row['Amount'] ?? 0), 0, ',', '.'),
                        'Rp ' . number_format((float)($row['Paid_Amount'] ?? 0), 0, ',', '.'),
                        'Rp ' . number_format((float)($row['Remaining_Amount'] ?? 0), 0, ',', '.')
                    ];
                },
                'isLandscape' => true,
                'summary' => '<tr><td>Total Piutang Belum Terbayar</td><td style="color:red; font-weight:bold;">: Rp ' . number_format($data['total_outstanding'] ?? 0, 0, ',', '.') . '</td></tr>'
            ];
        }

        // Default: cash_flow
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $accountId = $request->input('account_id', 'ALL');
        $category = $request->input('category', 'ALL');

        $data = $this->reportService->getCashFlow($startDate, $endDate, $accountId, $category);

        $this->exportDateField = 'Transaction_Date';

        return [
            'moduleName' => 'Laporan Arus Kas (Cash Flow)',
            'data' => collect($data['transactions'] ?? collect([]))->values(),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Tanggal', 'Akun', 'Tipe', 'Kategori', 'Keterangan', 'Nominal (Rp)'],
            'mapRow' => function($row) {
                return [
                    $row['Transaction_Date'] ?? '-',
                    $row['Account_ID'] ?? '-',
                    $row['Type'] ?? '-',
                    $row['Category'] ?? '-',
                    $row['Description'] ?? '-',
                    'Rp ' . number_format((float)($row['Amount'] ?? 0), 0, ',', '.')
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Saldo Awal (Opening Balance)</td><td>: Rp ' . number_format($data['opening_balance'] ?? 0, 0, ',', '.') . '</td></tr>' .
                         '<tr><td>Total Pemasukan (Income)</td><td>: Rp ' . number_format($data['total_income'] ?? 0, 0, ',', '.') . '</td></tr>' .
                         '<tr><td>Total Pengeluaran (Expense)</td><td>: Rp ' . number_format($data['total_expense'] ?? 0, 0, ',', '.') . '</td></tr>' .
                         '<tr><td>Arus Kas Bersih (Net Cash Flow)</td><td>: Rp ' . number_format($data['net_cash_flow'] ?? 0, 0, ',', '.') . '</td></tr>' .
                         '<tr><td><strong>Saldo Akhir (Closing Balance)</strong></td><td><strong>: Rp ' . number_format($data['closing_balance'] ?? 0, 0, ',', '.') . '</strong></td></tr>'
        ];
    }
}
