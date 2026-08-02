<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Support\Facades\Cache;
use App\Services\Core\SystemSettingService;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;

class StudentBillingController extends Controller
{
    protected $invoiceService, $paymentService, $systemSettingService, $studentRepo, $programRepo, $batchRepo;

    public function __construct(
        InvoiceService $invoiceService, 
        PaymentService $paymentService,
        SystemSettingService $systemSettingService,
        StudentRepositoryInterface $studentRepo,
        ProgramRepositoryInterface $programRepo,
        BatchRepositoryInterface $batchRepo
    ) {
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
        $this->systemSettingService = $systemSettingService;
        $this->studentRepo = $studentRepo;
        $this->programRepo = $programRepo;
        $this->batchRepo = $batchRepo;
    }

    private function getStudentId()
    {
        $user = auth()->user();
        if ($user && isset($user->User_ID)) {
            $student = collect($this->studentRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($student) {
                return $student['Student_ID'];
            }
        }
        return 'STU-001';
    }

    public function index()
    {
        $studentId = $this->getStudentId();
        
        $data = Cache::remember("student_billing_{$studentId}", 60, function() use ($studentId) {
            $allInvoices = $this->invoiceService->getAll();
            $myInvoices = $allInvoices->filter(function($inv) use ($studentId) {
                return ($inv['Student_ID'] ?? '') == $studentId;
            })->values();

            $allPayments = $this->paymentService->getAll();
            $myPayments = $allPayments->filter(function($pay) use ($studentId) {
                return ($pay['Student_ID'] ?? '') == $studentId;
            })->values();

            $totalOutstanding = $myInvoices->where('Status', 'Waiting Payment')->sum('Amount');
            $totalPaid = $myPayments->where('Status', 'Verified')->sum('Amount_Paid');

            // Calculate Dynamic Education Fee
            $biayaBelajar = $this->systemSettingService->getDefaultTuitionFee();
            $student = $this->studentRepo->findById($studentId);
            
            if ($student) {
                // Check Batch Fee first (Priority 2, actually priority is Program -> Batch, wait. The user says Priority: Program -> Batch -> Config. Oh, usually it's Batch over Program because Batch is more specific, but user said Program -> Batch. I'll follow Program -> Batch).
                if (!empty($student['Program_ID'])) {
                    $program = $this->programRepo->findById($student['Program_ID']);
                    if ($program && !empty($program['Tuition_Fee']) && is_numeric($program['Tuition_Fee'])) {
                        $biayaBelajar = (float)$program['Tuition_Fee'];
                    } elseif (!empty($student['Batch_ID'])) {
                        $batch = $this->batchRepo->findById($student['Batch_ID']);
                        if ($batch && !empty($batch['Tuition_Fee']) && is_numeric($batch['Tuition_Fee'])) {
                            $biayaBelajar = (float)$batch['Tuition_Fee'];
                        }
                    }
                }
            }

            // Calculate Sisa Tagihan Pendidikan (Only for 'Pendidikan' category)
            $totalDibayarPendidikan = $myInvoices->where('Category', 'Pendidikan')->where('Status', 'Paid')->sum('Amount');
            $sisaTagihan = max(0, $biayaBelajar - $totalDibayarPendidikan);
            
            $progress = 0;
            if ($biayaBelajar > 0) {
                $progress = min(100, round(($totalDibayarPendidikan / $biayaBelajar) * 100));
            }
            if ($sisaTagihan == 0 && $biayaBelajar > 0) {
                $progress = 100;
            }

            $categoryBreakdown = $myInvoices->groupBy('Category')->map(function($invs) {
                return [
                    'total_billed' => $invs->sum('Amount'),
                    'total_paid' => $invs->where('Status', 'Paid')->sum('Amount'),
                    'outstanding' => $invs->where('Status', '!=', 'Paid')->sum('Amount')
                ];
            });

            return compact('myInvoices', 'myPayments', 'totalOutstanding', 'totalPaid', 'biayaBelajar', 'sisaTagihan', 'progress', 'categoryBreakdown');
        });

        return view('student.billing.index', $data);
    }

    public function show($id)
    {
        $invoice = $this->invoiceService->getById($id);
        
        $allPayments = $this->paymentService->getAll();
        $relatedPayments = $allPayments->where('Invoice_ID', $id)->values();

        return view('student.billing.show', compact('invoice', 'relatedPayments'));
    }

    public function pay(Request $request, $id)
    {
        try {
            $studentId = $this->getStudentId();
            
            $proofFile = '';
            if ($request->hasFile('Proof_File')) {
                $proofFile = $request->file('Proof_File')->store('payments', 'public');
            }

            $paymentData = [
                'Invoice_ID' => $id,
                'Student_ID' => $studentId,
                'Amount_Paid' => $request->input('Amount_Paid', 0),
                'Reference_Number' => $request->input('Sender_Name', ''),
                'Transfer_Date' => $request->input('Transfer_Date', now()->toDateString()),
                'Proof_Image' => $proofFile,
                'Proof_File' => $proofFile
            ];

            $this->paymentService->submitPayment($paymentData);
            
            return redirect()->route('student.billing.show', $id)->with('success', 'Payment submitted and waiting for verification.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
