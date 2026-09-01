<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\InvoiceService;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Helpers\ReportHelper;
use App\Helpers\UserResolverHelper;
use App\Services\Core\SystemSettingService;
use App\Exceptions\AmbiguousSheetWriteException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Log;

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
            $stdDetail = UserResolverHelper::getStudentDetail($inv['Student_ID'] ?? '');
            $inv['student_name'] = $stdDetail['name'];
            $inv['class_name'] = $stdDetail['class_name'];
            $inv['batch_name'] = $stdDetail['batch_name'];
            $inv['student_formatted'] = $stdDetail['formatted'];
            $inv['Created_By_Name'] = UserResolverHelper::getName($inv['Created_By'] ?? '');
            return $inv;
        });

        $invoiceGroups = $invoices
            ->groupBy(fn ($invoice) => trim((string) ($invoice['Status'] ?? 'Draft')) ?: 'Draft')
            ->map(function ($group, $status) {
                return [
                    'id' => $status,
                    'title' => $status,
                    'total' => $group->count(),
                    'amount' => (float) $group->sum(fn ($invoice) => (float) ($invoice['Grand_Total'] ?? $invoice['Amount'] ?? 0)),
                    'remaining' => (float) $group->sum(fn ($invoice) => (float) ($invoice['Remaining_Amount'] ?? $invoice['Amount'] ?? 0)),
                    'items' => $group->sortByDesc('Due_Date')->values(),
                ];
            })
            ->sortBy(function ($group) {
                $order = array_search($group['id'], ['OVERDUE', 'Waiting Payment', 'Partial Paid', 'Draft', 'Paid', 'Cancelled'], true);
                return $order === false ? 99 : $order;
            })
            ->values();
        
        $invoices = \App\Helpers\CollectionHelper::paginate($invoices, 10)->withQueryString();

        return view('finance.invoices.index', compact('invoices', 'invoiceGroups', 'type', 'statusFilter', 'search'));
    }

    public function create()
    {
        $students = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll();
        $companies = app(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class)->fetchAll();
        $classes = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class)->fetchAll();
        $batches = app(\App\Interfaces\GoogleSheets\BatchRepositoryInterface::class)->fetchAll();

        $classesMap = collect($classes)->keyBy('Class_ID');
        $batchesMap = collect($batches)->keyBy('Batch_ID');
        $students = collect($students)->map(function($s) use ($classesMap, $batchesMap) {
            $cId = $s['Class_ID'] ?? '';
            $bId = $s['Batch_ID'] ?? '';
            $s['class_name'] = isset($classesMap[$cId]) ? ($classesMap[$cId]['Class_Name'] ?? $classesMap[$cId]['Class_Code'] ?? '-') : '-';
            $s['batch_name'] = isset($batchesMap[$bId]) ? ($batchesMap[$bId]['Batch_Name'] ?? $batchesMap[$bId]['Batch_Code'] ?? '-') : '-';
            return $s;
        });

        $settingService = app(SystemSettingService::class);
        $categories = $settingService->getInvoiceCategories();
        $defaultDueDays = max(1, (int) $settingService->get('INVOICE_DUE_DAYS', 14));
        $defaultTuitionFee = $settingService->getDefaultTuitionFee();

        return view('finance.invoices.create', compact(
            'students',
            'companies',
            'classes',
            'batches',
            'categories',
            'defaultDueDays',
            'defaultTuitionFee'
        ));
    }

    public function store(StoreInvoiceRequest $request)
    {
        try {
            $invoice = $this->invoiceService->create($request->validated());
            return redirect()->route('invoices.show', $invoice['Invoice_ID'])->with('success', 'Invoice tagihan berhasil dibuat sebagai Draft.');
        } catch (AmbiguousSheetWriteException $e) {
            Log::warning('Invoice create persistence ambiguous', [
                'request_id' => $request->header('X-Request-ID'),
                'idempotency_key' => $request->input('Idempotency_Key') ? hash('sha256', $request->input('Idempotency_Key')) : null,
                'exception' => get_class($e),
            ]);
            return back()->with('error', 'Status penyimpanan invoice belum dapat dikonfirmasi. Silakan cek daftar invoice sebelum mencoba kembali.')->withInput();
        } catch (LockTimeoutException $e) {
            Log::warning('Invoice create lock timeout', [
                'request_id' => $request->header('X-Request-ID'),
                'exception' => get_class($e),
            ]);
            return back()->with('error', 'Permintaan invoice sedang diproses oleh transaksi lain. Silakan tunggu lalu kirim ulang dengan token yang sama.')->withInput();
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e))->withInput();
        }
    }

    public function show($id)
    {
        $invoice = $this->invoiceService->getById($id);
        if (!$invoice) {
            return redirect()->route('invoices.index')->with('error', 'Invoice tidak ditemukan.');
        }

        $stdDetail = UserResolverHelper::getStudentDetail($invoice['Student_ID'] ?? '');
        $invoice['student_name'] = $stdDetail['name'];
        $invoice['class_name'] = $stdDetail['class_name'];
        $invoice['batch_name'] = $stdDetail['batch_name'];
        $invoice['student_formatted'] = $stdDetail['formatted'];
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
            return back()->with('error', $this->safeExceptionMessage($e))->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->invoiceService->delete($id);
            return redirect()->route('invoices.index')->with('success', 'Invoice tagihan dibatalkan/dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
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
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function downloadInvoicePdf($id)
    {
        return $this->downloadPdf($id);
    }

    public function verifyInvoicePublic($id)
    {
        try {
            $docData = $this->invoiceService->getInvoiceDocumentData($id, true);
            $docData['invoice']['student_name'] = UserResolverHelper::getName($docData['invoice']['Student_ID'] ?? '');
            return view('finance.invoices.verify_invoice_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $this->safeExceptionMessage($e, 'Invoice tidak ditemukan atau tidak tersedia.'));
        }
    }

    public function publish($id)
    {
        try {
            $this->invoiceService->publish($id);
            return redirect()->route('invoices.show', $id)->with('success', 'Invoice berhasil diterbitkan (Status: Waiting Payment).');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function cancel($id)
    {
        try {
            $this->invoiceService->cancel($id);
            return redirect()->route('invoices.show', $id)->with('success', 'Invoice berhasil dibatalkan (Status: Cancelled).');
        } catch (\Exception $e) {
            return back()->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function notify(Request $request, $id)
    {
        try {
            $invoice = $this->invoiceService->getById($id);
            if (!$invoice) {
                return back()->with('error', 'Invoice tidak ditemukan.');
            }

            $message = $request->input('message', 'Pengingat pembayaran tagihan WMS.');
            $studentId = $invoice['Student_ID'] ?? null;
            if (($invoice['Invoice_Type'] ?? 'STUDENT') !== 'STUDENT' || empty($studentId)) {
                return back()->with('error', 'Pengingat pembayaran hanya dapat dikirim untuk invoice siswa yang memiliki Student_ID.');
            }

            $notificationDelivered = true;
            $eventDispatched = true;

            // Create notification record using Service to ensure cache clearing
            try {
                $notifService = app(\App\Services\Core\NotificationService::class);
                $notifService->CreateNotification([
                    'Notification_ID' => uniqid('NTF_'),
                    'User_ID'         => $studentId,
                    'Title'           => 'Pengingat Pembayaran Tagihan',
                    'Message'         => $message,
                    'Notification_Type'=> 'BILLING_REMINDER',
                    'Priority'        => 'High',
                    'Is_Read'         => 'FALSE',
                    'Created_At'      => now()->toDateTimeString()
                ]);
            } catch (\Throwable $e) {
                $notificationDelivered = false;
                \Illuminate\Support\Facades\Log::warning('Invoice notification delivery failed', [
                    'invoice_id' => $id,
                    'student_id' => $studentId,
                    'exception' => get_class($e),
                ]);
            }

            // Dispatch Enterprise Event
            try {
                $enterpriseEvent = app(\App\Services\Core\EnterpriseEventService::class);
                $enterpriseEvent->dispatch(
                    'FINANCE',
                    'NOTIFY',
                    'INVOICE',
                    $id,
                    \App\Support\ActorIdentity::required(),
                    ['STUDENT', 'FINANCE'],
                    array_filter([$studentId]),
                    ['Message' => $message, 'Amount' => $invoice['Amount'] ?? 0]
                );
            } catch (\Throwable $e) {
                $eventDispatched = false;
                \Illuminate\Support\Facades\Log::warning('Invoice notification event dispatch failed', [
                    'invoice_id' => $id,
                    'student_id' => $studentId,
                    'exception' => get_class($e),
                ]);
            }

            $message = ($notificationDelivered && $eventDispatched)
                ? "Pengingat penagihan untuk invoice #{$id} berhasil dikirim."
                : "Operasi invoice #{$id} berhasil, tetapi pengiriman pengingat tertunda/gagal dan dapat dicoba kembali.";
            return redirect()->route('invoices.index')->with($notificationDelivered && $eventDispatched ? 'success' : 'warning', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengirim pengingat: ' . $this->safeExceptionMessage($e)]);
        }
    }
}

