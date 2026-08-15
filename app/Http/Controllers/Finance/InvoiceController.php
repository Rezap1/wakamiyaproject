<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\InvoiceService;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Helpers\ReportHelper;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Due_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
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
                return stripos($item['Invoice_ID'] ?? '', $search) !== false ||
                       stripos($item['Category'] ?? '', $search) !== false ||
                       stripos($item['Student_ID'] ?? '', $search) !== false;
            });
        }
        
        return [
            'moduleName' => 'Tagihan (Invoices)',
            'data' => collect(array_values($invoices->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Tagihan', 'Tipe', 'Penerima', 'Kategori', 'Total Tagihan (Rp)', 'Sisa Piutang (Rp)', 'Jatuh Tempo', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Invoice_ID'] ?? '-', 
                    $row['Invoice_Type'] ?? 'STUDENT', 
                    $row['Student_ID'] ?? $row['Company_ID'] ?? '-', 
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
                return stripos($item['Invoice_ID'] ?? '', $search) !== false ||
                       stripos($item['Category'] ?? '', $search) !== false ||
                       stripos($item['Student_ID'] ?? '', $search) !== false;
            });
        }
        
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

        $payments = app(\App\Services\Finance\PaymentService::class)->getAll()
            ->where('Invoice_ID', $id)
            ->values();

        return view('finance.invoices.show', compact('invoice', 'payments'));
    }

    public function edit($id)
    {
        $invoice = $this->invoiceService->getById($id);
        if (!$invoice) {
            return redirect()->route('invoices.index')->with('error', 'Invoice tidak ditemukan.');
        }

        if (strcasecmp($invoice['Status'] ?? 'Draft', 'Draft') !== 0) {
            return redirect()->route('invoices.show', $id)->with('error', 'Hanya invoice berstatus Draft yang dapat diedit.');
        }

        $students = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll();
        $companies = app(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class)->fetchAll();

        return view('finance.invoices.edit', compact('invoice', 'students', 'companies'));
    }

    public function update(UpdateInvoiceRequest $request, $id)
    {
        try {
            $invoice = $this->invoiceService->update($id, $request->validated());
            return redirect()->route('invoices.show', $id)->with('success', 'Invoice berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function publish($id)
    {
        try {
            $this->invoiceService->publish($id);
            return redirect()->route('invoices.show', $id)->with('success', 'Invoice tagihan berhasil diterbitkan (Published).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cancel($id)
    {
        try {
            $this->invoiceService->cancel($id);
            return redirect()->route('invoices.show', $id)->with('success', 'Invoice tagihan berhasil dibatalkan (Cancelled).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function downloadInvoicePdf($id)
    {
        try {
            $docData = $this->invoiceService->getInvoiceDocumentData($id);
            
            return ReportHelper::export(
                'pdf',
                'Invoice_Resmi_' . $id,
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

    public function verifyInvoicePublic($id)
    {
        try {
            $docData = $this->invoiceService->getInvoiceDocumentData($id);
            return view('finance.invoices.verify_invoice_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $this->invoiceService->delete($id);
            return redirect()->route('invoices.index')->with('success', 'Invoice berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
