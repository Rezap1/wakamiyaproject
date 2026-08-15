<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\PaymentService;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Helpers\ReportHelper;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Payment_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $payments = $this->paymentService->getAll();
        
        $type = $request->input('type');
        if ($type) {
            $payments = $payments->where('Payment_Type', $type);
        }

        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        $payments = $payments->map(function ($item) use ($students) {
            $studentId = $item['Student_ID'] ?? null;
            if ($studentId && isset($students[$studentId])) {
                $item['student_name'] = $students[$studentId]['Full_Name'] ?? '-';
            } else {
                $item['student_name'] = '-';
            }
            return $item;
        });

        $search = $request->input('search');
        if ($search) {
            $payments = $payments->filter(function($item) use ($search) {
                return stripos($item['Payment_ID'] ?? '', $search) !== false ||
                       stripos($item['Invoice_ID'] ?? '', $search) !== false ||
                       stripos($item['student_name'] ?? '', $search) !== false;
            });
        }
        
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if ($dateFrom || $dateTo) {
            $payments = $payments->filter(function($item) use ($dateFrom, $dateTo) {
                $dateStr = $item['Payment_Date'] ?? $item['Created_At'] ?? null;
                if (!$dateStr) return false;
                
                try {
                    $date = \Carbon\Carbon::parse($dateStr)->startOfDay();
                    if ($dateFrom && $dateTo) {
                        return $date->between(\Carbon\Carbon::parse($dateFrom)->startOfDay(), \Carbon\Carbon::parse($dateTo)->endOfDay());
                    } elseif ($dateFrom) {
                        return $date->greaterThanOrEqualTo(\Carbon\Carbon::parse($dateFrom)->startOfDay());
                    } elseif ($dateTo) {
                        return $date->lessThanOrEqualTo(\Carbon\Carbon::parse($dateTo)->endOfDay());
                    }
                } catch (\Exception $e) {
                    return false;
                }
                return true;
            });
        }
        
        return [
            'moduleName' => 'Pembayaran (Payments)',
            'data' => collect(array_values($payments->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Kuitansi', 'ID Tagihan', 'Siswa / Pihak', 'Nominal Dibayar', 'Metode Pembayaran', 'Tanggal Bayar', 'Status Verifikasi'],
            'mapRow' => function($row) {
                $studentText = ($row['Student_ID'] ?? '-') . ' - ' . ($row['student_name'] ?? '-');
                return [
                    $row['Payment_ID'] ?? '-', 
                    $row['Invoice_ID'] ?? '-', 
                    $studentText, 
                    'Rp ' . number_format((float)($row['Amount_Paid'] ?? 0), 0, ',', '.'),
                    $row['Payment_Method'] ?? 'TRANSFER',
                    isset($row['Payment_Date']) ? \Carbon\Carbon::parse($row['Payment_Date'])->format('d M Y') : '-',
                    $row['Status'] ?? 'Waiting Verification'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data Pembayaran</td><td>: '.$payments->count().'</td></tr>'
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
        
        $type = $request->input('type');
        if ($type) {
            $payments = $payments->where('Payment_Type', $type);
        }

        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        
        $payments = $payments->map(function ($item) use ($students) {
            $studentId = $item['Student_ID'] ?? null;
            $studentName = $studentId && isset($students[$studentId]) ? $students[$studentId]['Full_Name'] : $studentId;
            $item['Student_Display'] = $studentName . ($studentId ? " ($studentId)" : '');
            return $item;
        });

        $search = $request->input('search');
        if ($search) {
            $payments = $payments->filter(function($item) use ($search) {
                return stripos($item['Payment_ID'] ?? '', $search) !== false ||
                       stripos($item['Invoice_ID'] ?? '', $search) !== false ||
                       stripos($item['Student_Display'] ?? '', $search) !== false;
            });
        }
        
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if ($dateFrom || $dateTo) {
            $payments = $payments->filter(function($item) use ($dateFrom, $dateTo) {
                $dateStr = $item['Payment_Date'] ?? $item['Created_At'] ?? null;
                if (!$dateStr) return false;
                
                try {
                    $date = \Carbon\Carbon::parse($dateStr)->startOfDay();
                    if ($dateFrom && $dateTo) {
                        return $date->between(\Carbon\Carbon::parse($dateFrom)->startOfDay(), \Carbon\Carbon::parse($dateTo)->endOfDay());
                    } elseif ($dateFrom) {
                        return $date->greaterThanOrEqualTo(\Carbon\Carbon::parse($dateFrom)->startOfDay());
                    } elseif ($dateTo) {
                        return $date->lessThanOrEqualTo(\Carbon\Carbon::parse($dateTo)->endOfDay());
                    }
                } catch (\Exception $e) {
                    return false;
                }
                return true;
            });
        }

        $payments = \App\Helpers\CollectionHelper::paginate($payments, 10)->withQueryString();
        
        return view('finance.payments.index', compact('payments'));
    }

    public function store(StorePaymentRequest $request)
    {
        try {
            $this->paymentService->submitPayment($request->validated());
            return redirect()->route('payments.index')->with('success', 'Pembayaran berhasil dikirimkan.');
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
        $invoice = null;
        if (!empty($payment['Invoice_ID'])) {
            $invoice = app(\App\Services\Finance\InvoiceService::class)->getById($payment['Invoice_ID']);
        }
        $accounts = app(\App\Services\Finance\AccountService::class)->getAll();
        return view('finance.payments.show', compact('payment', 'invoice', 'accounts'));
    }

    public function verify(UpdatePaymentRequest $request, $id)
    {
        try {
            $status = $request->input('Status');
            $notes = $request->input('Notes', '');
            $accountId = $request->input('Account_ID');
            $verifiedBy = auth()->user()->Name ?? auth()->user()->Email ?? 'Finance Officer';
            
            $this->paymentService->verifyPayment($id, $verifiedBy, $status, $notes, $accountId);
            
            return redirect()->route('payments.index')->with('success', "Pembayaran #{$id} berhasil diproses dengan status {$status}.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function downloadReceiptPdf($id)
    {
        try {
            $receiptData = $this->paymentService->getPaymentReceiptData($id);
            
            if (($receiptData['payment']['Status'] ?? '') !== 'Verified') {
                return back()->withErrors(['error' => 'Kuitansi resmi hanya dapat diterbitkan untuk pembayaran yang telah Terverifikasi (Verified).']);
            }

            return ReportHelper::export(
                'pdf',
                'Kwitansi_Resmi_' . $id,
                collect([$receiptData['payment']]),
                $receiptData,
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
            $receiptData = $this->paymentService->getPaymentReceiptData($id);
            return view('finance.payments.verify_receipt_public', ['data' => $receiptData]);
        } catch (\Exception $e) {
            abort(404, $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $this->paymentService->deletePayment($id);
            return redirect()->route('payments.index')->with('success', 'Data pembayaran berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
