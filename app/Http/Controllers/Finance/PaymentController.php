<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\PaymentService;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Helpers\ReportHelper;
use App\Helpers\StoragePathHelper;
use App\Helpers\UserResolverHelper;
use App\Support\Reporting\HumanReadableResolver;

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

        $studentsById = collect(app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll())
            ->keyBy('Student_ID');
        $companiesById = collect(app(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class)->fetchAll())
            ->keyBy('Company_ID');
        
        return [
            'moduleName' => 'Pembayaran (Payments)',
            'data' => collect(array_values($payments->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['No. Pembayaran', 'No. Kwitansi', 'Tagihan (Invoice)', 'Siswa / Pembayar', 'Tanggal', 'Metode', 'Jumlah (Rp)', 'Status'],
            'mapRow' => function($row) use ($studentsById, $companiesById) {
                $payerName = trim((string) ($row['Company_ID'] ?? '')) !== ''
                    ? HumanReadableResolver::companyName($row['Company_ID'] ?? '', $companiesById)
                    : HumanReadableResolver::studentName($row['Student_ID'] ?? '', $studentsById);
                return [
                    $row['Payment_ID'] ?? '-',
                    $row['Receipt_Number'] ?? '-',
                    $row['Invoice_ID'] ?? '-',
                    $payerName,
                    isset($row['Payment_Date']) ? \Carbon\Carbon::parse($row['Payment_Date'])->format('d M Y') : '-',
                    $row['Payment_Method'] ?? 'Bank Transfer',
                    'Rp ' . number_format((float)($row['Amount_Paid'] ?? $row['Amount'] ?? 0), 0, ',', '.'),
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

        $paymentGroups = $payments
            ->groupBy(fn ($payment) => trim((string) ($payment['Status'] ?? 'Waiting Verification')) ?: 'Waiting Verification')
            ->map(function ($group, $status) {
                return [
                    'id' => $status,
                    'title' => $status,
                    'total' => $group->count(),
                    'amount' => (float) $group->sum(fn ($payment) => (float) ($payment['Amount_Paid'] ?? $payment['Amount'] ?? 0)),
                    'items' => $group->sortByDesc('Payment_Date')->values(),
                ];
            })
            ->sortBy(function ($group) {
                $order = array_search($group['id'], ['Waiting Verification', 'Need Revision', 'Verified', 'Rejected'], true);
                return $order === false ? 99 : $order;
            })
            ->values();

        $payments = \App\Helpers\CollectionHelper::paginate($payments, 10)->withQueryString();

        return view('finance.payments.index', compact('payments', 'paymentGroups', 'search'));
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
        $proofFile = '';

        try {
            $data = $request->validated();
            if ($request->hasFile('Proof_File')) {
                $proofFile = $request->file('Proof_File')->store('payments');
                $data['Proof_File'] = $proofFile;
                $data['Proof_Image'] = $data['Proof_File'];
            }

            $payment = $this->paymentService->create($data);
            return redirect()->route('payments.show', $payment['Payment_ID'])->with('success', 'Pembayaran berhasil direkam dan menunggu verifikasi.');
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
            return back()->with('error', $this->safeExceptionMessage($e))->withInput();
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
        $invoice = null;
        if (!empty($payment['Invoice_ID'])) {
            $invoice = app(\App\Services\Finance\InvoiceService::class)->getById($payment['Invoice_ID']);
        }

        return view('finance.payments.show', compact('payment', 'invoice'));
    }

    public function downloadReceiptPdf($id)
    {
        try {
            $docData = $this->paymentService->getReceiptDocumentData($id);
            $studentsById = collect(app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll())->keyBy('Student_ID');
            $companiesById = collect(app(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class)->fetchAll())->keyBy('Company_ID');
            $docData['payment']['student_name'] = trim((string) ($docData['payment']['Company_ID'] ?? '')) !== ''
                ? HumanReadableResolver::companyName($docData['payment']['Company_ID'] ?? '', $companiesById)
                : HumanReadableResolver::studentName($docData['payment']['Student_ID'] ?? '', $studentsById);
            
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
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function verifyReceiptPublic($id)
    {
        try {
            $docData = $this->paymentService->getReceiptDocumentData($id, true);
            $studentsById = collect(app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll())->keyBy('Student_ID');
            $companiesById = collect(app(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class)->fetchAll())->keyBy('Company_ID');
            $docData['payment']['student_name'] = trim((string) ($docData['payment']['Company_ID'] ?? '')) !== ''
                ? HumanReadableResolver::companyName($docData['payment']['Company_ID'] ?? '', $companiesById)
                : HumanReadableResolver::studentName($docData['payment']['Student_ID'] ?? '', $studentsById);
            return view('finance.payments.verify_receipt_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $this->safeExceptionMessage($e, 'Bukti pembayaran tidak ditemukan atau tidak tersedia.'));
        }
    }

    public function destroy($id)
    {
        try {
            $this->paymentService->deletePayment($id);
            return redirect()->route('payments.index')->with('success', 'Pembayaran berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function verify(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|in:Verified,Rejected,Need Revision',
                'notes' => 'nullable|string|max:1000',
            ]);

            $user = auth()->user();
            $verifiedBy = $user->User_ID ?? $user->Email ?? $user->email ?? null;
            if (!$verifiedBy) {
                abort(403, 'Identitas verifikator tidak valid.');
            }

            $status = $validated['status'] ?? 'Verified';
            $notes = $validated['notes'] ?? '';

            $this->paymentService->verifyPayment($id, $verifiedBy, $status, $notes);
            return redirect()->route('payments.show', $id)->with('success', 'Pembayaran berhasil diverifikasi.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function reverse(Request $request, $id)
    {
        try {
            $validated = $request->validate(['reason' => 'required|string|max:1000']);
            $this->paymentService->reversePayment($id, $validated['reason']);
            return redirect()->route('payments.show', $id)->with('success', 'Pembayaran berhasil direversal melalui transaksi kompensasi.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e))->withInput();
        }
    }

    public function reconcileLedger(Request $request, $id)
    {
        try {
            $validated = $request->validate(['Account_ID' => 'nullable|string|max:100']);
            $this->paymentService->reconcileVerifiedPaymentLedger($id, $validated['Account_ID'] ?? null);
            return redirect()->route('payments.show', $id)->with('success', 'Ledger pembayaran berhasil direkonsiliasi.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function reconcileReversal(Request $request, $id)
    {
        try {
            $validated = $request->validate(['reason' => 'nullable|string|max:1000']);
            $this->paymentService->reconcilePaymentReversal($id, $validated['reason'] ?? 'Recovery reversal');
            return redirect()->route('payments.show', $id)->with('success', 'Ledger reversal berhasil direkonsiliasi.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function edit($id)
    {
        $payment = $this->paymentService->getById($id);
        if (!$payment) {
            return redirect()->route('payments.index')->with('error', 'Pembayaran tidak ditemukan.');
        }
        $invoice = null;
        if (!empty($payment['Invoice_ID'])) {
            $invoice = app(\App\Services\Finance\InvoiceService::class)->getById($payment['Invoice_ID']);
        }
        return view('finance.payments.show', compact('payment', 'invoice'));
    }

    public function update(UpdatePaymentRequest $request, $id)
    {
        try {
            $data = $request->validated();
            $user = auth()->user();
            $verifiedBy = $user->User_ID ?? $user->Email ?? $user->email ?? null;
            if (!$verifiedBy) {
                abort(403, 'Identitas verifikator tidak valid.');
            }
            $this->paymentService->verifyPayment(
                $id,
                $verifiedBy,
                $data['Status'],
                $data['Notes'] ?? '',
                $data['Account_ID'] ?? null
            );
            return redirect()->route('payments.show', $id)->with('success', 'Pembayaran berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e))->withInput();
        }
    }

    public function downloadProof(Request $request, $id)
    {
        $payment = $this->paymentService->getById($id);
        if (!$payment) {
            abort(404, 'Bukti pembayaran tidak ditemukan.');
        }

        $storedPath = $payment['Proof_File'] ?? $payment['Proof_Image'] ?? null;
        if (empty($storedPath)) {
            abort(404, 'Bukti pembayaran tidak ditemukan.');
        }
        $path = StoragePathHelper::privateFileResponsePath($storedPath);
        if (!$path) {
            abort(404, 'File bukti pembayaran tidak ditemukan di server.');
        }

        if ($request->boolean('inline')) {
            return response()->file($path);
        }

        return response()->download($path, $this->downloadFilename('bukti-pembayaran', $id, $path));
    }

    private function downloadFilename(string $prefix, string $id, string $path): string
    {
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $id);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $prefix . '-' . $safeId . ($extension ? '.' . $extension : '');
    }
}
