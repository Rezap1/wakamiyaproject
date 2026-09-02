<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Support\Facades\Cache;
use App\Services\Core\SystemSettingService;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Helpers\StoragePathHelper;
use App\Helpers\ReportHelper;
use App\Support\Finance\PaymentStatus;

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

    private function getStudentProfile(): array
    {
        $user = auth()->user();
        if ($user && isset($user->User_ID)) {
            $student = collect($this->studentRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($student) {
                return (array) $student;
            }
        }

        abort(403, 'Profil siswa tidak ditemukan.');
    }

    private function getStudentId()
    {
        $student = $this->getStudentProfile();
        return $student['Student_ID'];
    }

    public function index()
    {
        $student = $this->getStudentProfile();
        $studentId = $student['Student_ID'];
        
        $allInvoices = $this->invoiceService->getAll();
        $myInvoices = $allInvoices->filter(function($inv) use ($studentId) {
            return ($inv['Student_ID'] ?? '') == $studentId && strcasecmp(trim($inv['Status'] ?? ''), 'Draft') !== 0;
        })->values();

        $allPayments = $this->paymentService->getAll();
        $myPayments = $allPayments->filter(function($pay) use ($studentId) {
            return ($pay['Student_ID'] ?? '') == $studentId;
        })->values();
        $selfServicePayments = $myPayments->filter(fn ($payment) =>
            strcasecmp(trim((string) ($payment['Payment_Type'] ?? '')), 'STUDENT_SELF_SERVICE') === 0
            || empty($payment['Invoice_ID']))->values();

        // Use the dynamic remaining amount so partial and overdue invoices are
        // represented accurately without double-counting verified payments.
        $totalOutstanding = $myInvoices
            ->whereIn('Status', ['Waiting Payment', 'Partial Paid', 'OVERDUE'])
            ->sum('Remaining_Amount');
        $totalPaid = $myPayments->filter(fn ($payment) => PaymentStatus::verified($payment['Status'] ?? null))->sum('Amount_Paid');

        $educationSummary = $this->invoiceService->getStudentEducationBillingSummary(
            $studentId,
            null,
            $myInvoices,
            $myPayments,
            $student
        );
        $biayaBelajar = $educationSummary['tuition_fee'];
        $totalDibayarPendidikan = $educationSummary['education_paid'];
        $sisaTagihan = $educationSummary['remaining_to_pay'];
        $progress = $educationSummary['progress'];
        $companyProfile = $this->systemSettingService->getCompanyProfile();
        $bank = $companyProfile['bank'];

        $categoryBreakdown = $myInvoices->groupBy('Category')->map(function($invs) use ($myPayments) {
            $invoiceIds = $invs->pluck('Invoice_ID');
            return [
                'total_billed' => $invs->sum('Amount'),
                'total_paid' => $myPayments->filter(fn ($payment) => PaymentStatus::verified($payment['Status'] ?? null))
                    ->whereIn('Invoice_ID', $invoiceIds)->sum('Amount_Paid'),
                'outstanding' => $invs
                    ->whereNotIn('Status', ['Paid', 'Cancelled', 'Draft'])
                    ->sum('Remaining_Amount')
            ];
        });

        $data = compact('myInvoices', 'myPayments', 'selfServicePayments', 'totalOutstanding', 'totalPaid', 'biayaBelajar', 'sisaTagihan', 'progress', 'categoryBreakdown', 'bank', 'totalDibayarPendidikan');

        return view('student.billing.index', $data);
    }

    public function selfService()
    {
        $this->getStudentId();
        $companyProfile = $this->systemSettingService->getCompanyProfile();
        return view('student.billing.self-service', ['bank' => $companyProfile['bank'] ?? []]);
    }

    public function selfServicePay(Request $request)
    {
        $proofFile = '';
        try {
            $studentId = $this->getStudentId();
            $validated = $request->validate([
                'Amount_Paid' => 'required|numeric|gt:0',
                'Sender_Name' => 'required|string|max:255',
                'Transfer_Date' => 'required|date_format:Y-m-d',
                'Payment_Method' => 'required|string|in:TRANSFER,CASH',
                'Idempotency_Key' => 'required|uuid',
                'Proof_File' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);
            if ($request->hasFile('Proof_File')) {
                $proofFile = $request->file('Proof_File')->store('payments');
            }
            \Illuminate\Support\Facades\Log::notice('finance.student_payment_verification_requested', [
                'student_id' => $studentId,
            ]);
            $payment = $this->paymentService->submitPayment([
                'Self_Service' => true,
                'Student_ID' => $studentId,
                'Amount_Paid' => $validated['Amount_Paid'],
                'Payment_Method' => $validated['Payment_Method'],
                'Reference_Number' => $validated['Sender_Name'],
                'Sender_Name' => $validated['Sender_Name'],
                'Transfer_Date' => $validated['Transfer_Date'],
                'Payment_Date' => $validated['Transfer_Date'],
                'Idempotency_Key' => $validated['Idempotency_Key'],
                'Proof_Image' => $proofFile,
                'Proof_File' => $proofFile,
            ]);
            \Illuminate\Support\Facades\Log::notice('finance.student_payment_submitted', [
                'student_id' => $studentId,
                'payment_id' => (string) ($payment['Payment_ID'] ?? 'UNKNOWN'),
            ]);
            return redirect()->route('student.billing.index')->with('success', 'Pembayaran mandiri terkirim dan menunggu verifikasi Finance.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('finance.student_payment_submission_failed', [
                'student_id' => isset($studentId) ? (string) $studentId : 'UNKNOWN',
                'reason' => get_class($e),
            ]);
            if ($proofFile !== '') {
                try {
                    $persisted = collect($this->paymentService->getAll())->contains(fn ($payment) =>
                        ($payment['Proof_File'] ?? $payment['Proof_Image'] ?? '') === $proofFile);
                    if (!$persisted) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($proofFile);
                    }
                } catch (\Throwable) {
                    // Preserve the file when persistence cannot be determined safely.
                }
            }
            return back()->with('error', $this->safeExceptionMessage($e))->withInput();
        }
    }

    public function show($id)
    {
        $studentId = $this->getStudentId();
        $invoice = $this->invoiceService->getById($id);
        
        if (!$invoice || ($invoice['Student_ID'] ?? '') !== $studentId || strcasecmp(trim($invoice['Status'] ?? ''), 'Draft') === 0) {
            abort(403, "Akses Ditolak: Tagihan #{$id} bukan milik akun Anda atau belum diterbitkan.");
        }

        $allPayments = $this->paymentService->getAll();
        $relatedPayments = $allPayments
            ->where('Invoice_ID', $id)
            ->where('Student_ID', $studentId)
            ->values();

        return view('student.billing.show', compact('invoice', 'relatedPayments'));
    }

    public function downloadInvoicePdf($id)
    {
        $studentId = $this->getStudentId();
        $invoice = $this->invoiceService->getById($id);

        if (!$invoice || ($invoice['Student_ID'] ?? '') !== $studentId || strcasecmp(trim($invoice['Status'] ?? ''), 'Draft') === 0) {
            abort(403, "Akses Ditolak: Tagihan #{$id} bukan milik akun Anda atau belum diterbitkan.");
        }

        try {
            $docData = $this->invoiceService->getInvoiceDocumentData($id);
            $docData['invoice']['student_name'] = \App\Helpers\UserResolverHelper::getName($docData['invoice']['Student_ID'] ?? '');

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
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function pay(Request $request, $id)
    {
        $proofFile = '';

        try {
            $studentId = $this->getStudentId();

            $invoice = $this->invoiceService->getById($id);
            if (!$invoice || ($invoice['Student_ID'] ?? '') !== $studentId || strcasecmp(trim($invoice['Status'] ?? ''), 'Draft') === 0) {
                abort(403, "Akses Ditolak: Tagihan #{$id} bukan milik akun Anda atau belum diterbitkan.");
            }

            $validated = $request->validate([
                'Amount_Paid' => 'required|numeric|gt:0',
                'Sender_Name' => 'required|string|max:255',
                'Transfer_Date' => 'required|date',
                'Idempotency_Key' => 'nullable|uuid',
                'Proof_File' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120'
            ]);

            if ($request->hasFile('Proof_File')) {
                $proofFile = $request->file('Proof_File')->store('payments');
            }

            $paymentData = [
                'Invoice_ID' => $id,
                'Student_ID' => $studentId,
                'Amount_Paid' => $validated['Amount_Paid'],
                'Reference_Number' => $validated['Sender_Name'],
                'Transfer_Date' => $validated['Transfer_Date'],
                'Payment_Date' => $validated['Transfer_Date'],
                'Idempotency_Key' => $validated['Idempotency_Key'] ?? (string) Str::uuid(),
                'Proof_Image' => $proofFile,
                'Proof_File' => $proofFile
            ];

            $this->paymentService->submitPayment($paymentData);
            
            return redirect()->route('student.billing.show', $id)->with('success', 'Payment submitted and waiting for verification.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($proofFile !== '') {
                try {
                    $persisted = collect($this->paymentService->getAll())->contains(function ($payment) use ($proofFile) {
                        return ($payment['Proof_File'] ?? $payment['Proof_Image'] ?? '') === $proofFile;
                    });
                    if (!$persisted) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($proofFile);
                    }
                } catch (\Throwable $lookupFailure) {
                    // Preserve the file when persistence cannot be determined safely.
                }
            }
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function downloadProof(Request $request, $id)
    {
        $studentId = $this->getStudentId();
        $invoice = $this->invoiceService->getById($id);

        if (!$invoice || ($invoice['Student_ID'] ?? '') !== $studentId || strcasecmp(trim($invoice['Status'] ?? ''), 'Draft') === 0) {
            abort(403, "Akses Ditolak: Tagihan #{$id} bukan milik akun Anda atau belum diterbitkan.");
        }

        $payment = collect($this->paymentService->getAll())->first(function ($payment) use ($id, $studentId) {
            return ($payment['Invoice_ID'] ?? '') === $id
                && ($payment['Student_ID'] ?? '') === $studentId
                && (!empty($payment['Proof_File']) || !empty($payment['Proof_Image']));
        });

        if (!$payment) {
            abort(404, 'Bukti pembayaran tidak ditemukan.');
        }

        return $this->paymentProofResponse($payment, $request, 'bukti-pembayaran-' . $id);
    }

    public function downloadPaymentProof(Request $request, $paymentId)
    {
        $studentId = $this->getStudentId();
        $payment = collect($this->paymentService->getAll())->first(function ($payment) use ($paymentId, $studentId) {
            return ($payment['Payment_ID'] ?? '') === $paymentId
                && ($payment['Student_ID'] ?? '') === $studentId
                && (!empty($payment['Proof_File']) || !empty($payment['Proof_Image']));
        });

        if (!$payment) {
            abort(404, 'Bukti pembayaran tidak ditemukan.');
        }

        return $this->paymentProofResponse($payment, $request, 'bukti-pembayaran-' . $paymentId);
    }

    private function paymentProofResponse(array $payment, Request $request, string $filenamePrefix)
    {
        $proofPath = $payment['Proof_File'] ?? $payment['Proof_Image'] ?? null;
        $path = StoragePathHelper::privateFileResponsePath($proofPath);
        if (!$path) {
            abort(404, 'File bukti pembayaran tidak ditemukan di server.');
        }

        if ($request->boolean('inline')) {
            return response()->file($path);
        }

        return response()->download($path, $this->downloadFilename($filenamePrefix, $path));
    }

    private function downloadFilename(string $prefix, string $path): string
    {
        $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $safePrefix . ($extension ? '.' . $extension : '');
    }
}
