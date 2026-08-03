<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\PaymentService;
use App\Services\Core\ActivityLogService;
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
            'headers' => ['ID Kuitansi', 'ID Tagihan', 'Siswa', 'Nominal Dibayar', 'Tanggal Bayar', 'Status'],
            'mapRow' => function($row) {
                $studentText = ($row['Student_ID'] ?? '-') . ' - ' . ($row['student_name'] ?? '-');
                return [
                    $row['Payment_ID'] ?? '-', 
                    $row['Invoice_ID'] ?? '-', 
                    $studentText, 
                    'Rp ' . number_format((float)($row['Amount_Paid'] ?? 0), 0, ',', '.'),
                    isset($row['Payment_Date']) ? \Carbon\Carbon::parse($row['Payment_Date'])->format('d M Y') : '-',
                    $row['Status'] ?? 'Menunggu Verifikasi'
                ];
            },
            'isLandscape' => false,
            'summary' => '<tr><td>Total Data</td><td>: '.$payments->count().'</td></tr>'
        ];
    }

    protected $paymentService, $activityLogService;

    public function __construct(PaymentService $paymentService, ActivityLogService $activityLogService)
    {
        $this->paymentService = $paymentService;
        $this->activityLogService = $activityLogService;
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
            $item['Student_ID'] = $studentName . ($studentId ? " ($studentId)" : '');
            // Do not overwrite Amount_Paid
            return $item;
        });

        $search = $request->input('search');
        if ($search) {
            $payments = $payments->filter(function($item) use ($search) {
                return stripos($item['Payment_ID'] ?? '', $search) !== false ||
                       stripos($item['Invoice_ID'] ?? '', $search) !== false ||
                       stripos($item['Student_ID'] ?? '', $search) !== false;
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
        // Pagination
        $payments = \App\Helpers\CollectionHelper::paginate($payments, 10)->withQueryString();
        
        return view('finance.payments.index', compact('payments'));
    }

    public function show($id)
    {
        $payment = $this->paymentService->getById($id);
        if (!$payment) {
            return redirect()->route('payments.index')->with('error', 'Payment tidak ditemukan.');
        }
        return view('finance.payments.show', compact('payment'));
    }

    public function verify(Request $request, $id)
    {
        try {
            \Log::info('Payment verify payload:', $request->all());
            
            $request->validate([
                'Status' => 'required|in:Verified,Need Revision,Rejected',
            ]);
            
            $status = $request->input('Status');
            $notes = $request->input('Notes', '');
            
            $this->paymentService->verifyPayment($id, auth()->user()->Name ?? auth()->user()->Email ?? 'Finance Officer', $status, $notes);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM', 
                'VERIFY_PAYMENT', 
                'FINANCE', 
                "Memverifikasi payment {$id} dengan status {$status}", 
                $request->ip(), 
                null, 
                null, 
                $request->userAgent()
            );
            
            return redirect()->route('payments.index')->with('success', "Payment berhasil {$status}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id, Request $request)
    {
        try {
            $this->paymentService->deletePayment($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM', 
                'DELETE_PAYMENT', 
                'FINANCE', 
                "Menghapus payment {$id}", 
                $request->ip(), 
                null, 
                null, 
                $request->userAgent()
            );
            
            return redirect()->route('payments.index')->with('success', 'Data pembayaran berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}

