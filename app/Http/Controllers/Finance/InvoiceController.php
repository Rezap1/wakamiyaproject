<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\InvoiceService;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Helpers\ReportHelper;
use App\Helpers\UserResolverHelper;

class InvoiceController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $invoices = $this->invoiceService->getAll();
        
        $search = $request->input('search');
        if (!empty($search)) {
            $invoices = \App\Helpers\CollectionHelper::search($invoices, $search, ['Invoice_ID', 'Category', 'Student_ID']);
        }
        
        return [
            'moduleName' => 'Invoice Tagihan (Invoices)',
            'data' => collect(array_values($invoices->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Invoice', 'Tipe', 'Pihak Tagihan (Nama)', 'Kategori', 'Jumlah Total', 'Sisa Tagihan', 'Jatuh Tempo', 'Status'],
            'mapRow' => function($row) {
                $studentName = UserResolverHelper::getName($row['Student_ID'] ?? '');
                return [
                    $row['Invoice_ID'] ?? '-', 
                    $row['Invoice_Type'] ?? 'STUDENT', 
                    $studentName !== '-' ? $studentName : ($row['Company_Name'] ?? $row['Student_ID'] ?? '-'), 
                    $row['Category'] ?? '-',
                    'Rp ' . number_format((float)($row['Amount'] ?? 0), 0, ',', '.'),
                    'Rp ' . number_format((float)($row['Remaining_Amount'] ?? 0), 0, ',', '.'),
                    isset($row['Due_Date']) ? \Carbon\Carbon::parse($row['Due_Date'])->format('d M Y') : '-',
                    $row['Status'] ?? 'Draft'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data Tagihan</td><td>: '.$invoices->count().'</td></tr>'
        ];
    }

    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function index(Request $request)
    {
        $invoices = $this->invoiceService->getAll();
        
        $type = $request->input('type');
        if ($type) {
            $invoices = $invoices->where('Invoice_Type', $type);
        }

        $statusFilter = $request->input('status');
        if ($statusFilter) {
            $invoices = $invoices->filter(function($item) use ($statusFilter) {
                return strcasecmp($item['Status'] ?? '', $statusFilter) === 0;
            });
        }

        $search = $request->input('search');
        if ($search) {
            $invoices = $invoices->filter(function($item) use ($search) {
                $stdName = UserResolverHelper::getName($item['Student_ID'] ?? '');
                return stripos($item['Invoice_ID'] ?? '', $search) !== false ||
                       stripos($item['Category'] ?? '', $search) !== false ||
                       stripos($item['Student_ID'] ?? '', $search) !== false ||
                       stripos($stdName, $search) !== false;
            });
        }

        $invoices = $invoices->map(function($inv) {
            $inv['student_name'] = UserResolverHelper::getName($inv['Student_ID'] ?? '');
            $inv['Created_By_Name'] = UserResolverHelper::getName($inv['Created_By'] ?? '');
            return $inv;
        });
        
        $invoices = \App\Helpers\CollectionHelper::paginate($invoices, 10)->withQueryString();

        return view('finance.invoices.index', compact('invoices', 'type', 'statusFilter', 'search'));
    }

    public function create()
    {
        $students = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll();
        $companies = app(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class)->fetchAll();
        return view('finance.invoices.create', compact('students', 'companies'));
    }

    public function store(StoreInvoiceRequest $request)
    {
        try {
            $invoice = $this->invoiceService->create($request->validated());
            return redirect()->route('invoices.show', $invoice['Invoice_ID'])->with('success', 'Invoice tagihan berhasil dibuat sebagai Draft.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $invoice = $this->invoiceService->getById($id);
        if (!$invoice) {
            return redirect()->route('invoices.index')->with('error', 'Invoice tidak ditemukan.');
        }

        $invoice['student_name'] = UserResolverHelper::getName($invoice['Student_ID'] ?? '');
        $invoice['Created_By_Name'] = UserResolverHelper::getName($invoice['Created_By'] ?? '');

        $payments = app(\App\Services\Finance\PaymentService::class)->getAll()
            ->where('Invoice_ID', $id)
            ->map(function($pay) {
                $pay['student_name'] = UserResolverHelper::getName($pay['Student_ID'] ?? '');
                $pay['Created_By_Name'] = UserResolverHelper::getName($pay['Created_By'] ?? '');
                return $pay;
            })
            ->values();

        return view('finance.invoices.show', compact('invoice', 'payments'));
    }

    public function edit($id)
    {
        $invoice = $this->invoiceService->getById($id);
        if (!$invoice) {
            return redirect()->route('invoices.index')->with('error', 'Invoice tidak ditemukan.');
        }
        $students = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll();
        $companies = app(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class)->fetchAll();
        return view('finance.invoices.edit', compact('invoice', 'students', 'companies'));
    }

    public function update(UpdateInvoiceRequest $request, $id)
    {
        try {
            $this->invoiceService->update($id, $request->validated());
            return redirect()->route('invoices.show', $id)->with('success', 'Invoice tagihan berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->invoiceService->delete($id);
            return redirect()->route('invoices.index')->with('success', 'Invoice tagihan dibatalkan/dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function downloadPdf($id)
    {
        try {
            $docData = $this->invoiceService->getInvoiceDocumentData($id);
            $docData['invoice']['student_name'] = UserResolverHelper::getName($docData['invoice']['Student_ID'] ?? '');
            
            return ReportHelper::export(
                'pdf',
                'Invoice_' . $id,
                collect([$docData['invoice']]),
                $docData,
                'pdf.official_invoice',
                [],
                null,
                false
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function downloadInvoicePdf($id)
    {
        return $this->downloadPdf($id);
    }

    public function verifyInvoicePublic($id)
    {
        try {
            $docData = $this->invoiceService->getInvoiceDocumentData($id);
            $docData['invoice']['student_name'] = UserResolverHelper::getName($docData['invoice']['Student_ID'] ?? '');
            return view('finance.invoices.verify_invoice_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $e->getMessage());
        }
    }
}
