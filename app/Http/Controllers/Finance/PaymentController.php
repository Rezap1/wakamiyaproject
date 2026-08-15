<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\PaymentService;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Helpers\ReportHelper;
use App\Helpers\UserResolverHelper;

class PaymentController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Payment_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $payments = $this->paymentService->getAll();
        
        $search = $request->input('search');
        if (!empty($search)) {
            $payments = \App\Helpers\CollectionHelper::search($payments, $search, ['Payment_ID', 'Invoice_ID', 'Student_ID', 'Payment_Method']);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = \Carbon\Carbon::parse($request->input('date_from'))->startOfDay();
            $dateTo = \Carbon\Carbon::parse($request->input('date_to'))->endOfDay();
            
            $payments = $payments->filter(function ($item) use ($dateFrom, $dateTo) {
                $dateStr = $item['Payment_Date'] ?? $item['Created_At'] ?? null;
                if ($dateStr) {
                    $itemDate = \Carbon\Carbon::parse($dateStr);
                    return $itemDate->between($dateFrom, $dateTo);
                }
                return false;
            });
        }
        
        return [
            'moduleName' => 'Pembayaran (Payments)',
            'data' => collect(array_values($payments->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Pembayaran', 'No. Kwitansi', 'Tagihan (Invoice)', 'Siswa / Pembayar', 'Tanggal', 'Metode', 'Jumlah (Rp)', 'Status'],
            'mapRow' => function($row) {
                $stdName = UserResolverHelper::getName($row['Student_ID'] ?? '');
                return [
                    $row['Payment_ID'] ?? '-', 
                    $row['Receipt_Number'] ?? '-', 
                    $row['Invoice_ID'] ?? '-', 
                    $stdName !== '-' ? $stdName : ($row['Student_ID'] ?? '-'), 
                    isset($row['Payment_Date']) ? \Carbon\Carbon::parse($row['Payment_Date'])->format('d M Y') : '-',
                    $row['Payment_Method'] ?? 'Bank Transfer',
                    'Rp ' . number_format((float)($row['Amount'] ?? 0), 0, ',', '.'),
                    $row['Status'] ?? 'VERIFIED'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Pembayaran Terverifikasi</td><td>: '.$payments->count().'</td></tr>'
        ];
    }

    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function index(Request $request)
    {
        $payments = $this->paymentService->getAll();
        
        $search = $request->input('search');
        if ($search) {
            $payments = $payments->filter(function($item) use ($search) {
                $stdName = UserResolverHelper::getName($item['Student_ID'] ?? '');
                return stripos($item['Payment_ID'] ?? '', $search) !== false ||
                       stripos($item['Invoice_ID'] ?? '', $search) !== false ||
                       stripos($item['Receipt_Number'] ?? '', $search) !== false ||
                       stripos($stdName, $search) !== false;
            });
        }

        $payments = $payments->map(function($pay) {
            $pay['student_name'] = UserResolverHelper::getName($pay['Student_ID'] ?? '');
            $pay['Created_By_Name'] = UserResolverHelper::getName($pay['Created_By'] ?? '');
            $pay['Approved_By_Name'] = UserResolverHelper::getName($pay['Verified_By'] ?? $pay['Approved_By'] ?? '');
            return $pay;
        });

        $payments = \App\Helpers\CollectionHelper::paginate($payments, 10)->withQueryString();

        return view('finance.payments.index', compact('payments', 'search'));
    }

    public function create(Request $request)
    {
        $invoiceId = $request->input('invoice_id');
        $invoice = null;
        if ($invoiceId) {
            $invoice = app(\App\Services\Finance\InvoiceService::class)->getById($invoiceId);
        }
        $invoices = app(\App\Services\Finance\InvoiceService::class)->getAll()
            ->whereIn('Status', ['Waiting Payment', 'Partial Paid', 'OVERDUE']);

        return view('finance.payments.create', compact('invoices', 'invoice'));
    }

    public function store(StorePaymentRequest $request)
    {
        try {
            $payment = $this->paymentService->create($request->validated());
            return redirect()->route('payments.show', $payment['Payment_ID'])->with('success', 'Pembayaran berhasil direkam & diverifikasi secara otomatis.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $payment = $this->paymentService->getById($id);
        if (!$payment) {
            return redirect()->route('payments.index')->with('error', 'Pembayaran tidak ditemukan.');
        }

        $payment['student_name'] = UserResolverHelper::getName($payment['Student_ID'] ?? '');
        $payment['Created_By_Name'] = UserResolverHelper::getName($payment['Created_By'] ?? '');

        return view('finance.payments.show', compact('payment'));
    }

    public function downloadReceiptPdf($id)
    {
        try {
            $docData = $this->paymentService->getReceiptDocumentData($id);
            $docData['payment']['student_name'] = UserResolverHelper::getName($docData['payment']['Student_ID'] ?? '');
            
            return ReportHelper::export(
                'pdf',
                'Kwitansi_' . $id,
                collect([$docData['payment']]),
                $docData,
                'pdf.official_receipt',
                [],
                null,
                false
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function verifyReceiptPublic($id)
    {
        try {
            $docData = $this->paymentService->getReceiptDocumentData($id);
            $docData['payment']['student_name'] = UserResolverHelper::getName($docData['payment']['Student_ID'] ?? '');
            return view('finance.payments.verify_receipt_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $this->paymentService->delete($id);
            return redirect()->route('payments.index')->with('success', 'Pembayaran berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
