<?php
namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Finance\InvoiceService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use App\Repositories\GoogleSheets\StudentRepository;
use App\Repositories\GoogleSheets\CompanyRepository;
use App\Services\Core\SystemSettingService;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Invoice_Date';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $invoices = $this->invoiceService->getAll();
        
        $type = $request->input('type');
        if ($type) {
            $invoices = $invoices->where('Invoice_Type', $type);
        }

        $search = $request->input('search');
        if ($search) {
            $invoices = $invoices->filter(function ($item) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($item['Invoice_ID'] ?? ''), $searchLower)
                    || str_contains(strtolower($item['Student_ID'] ?? ''), $searchLower)
                    || str_contains(strtolower($item['Category'] ?? ''), $searchLower)
                    || str_contains(strtolower($item['student_name'] ?? ''), $searchLower);
            });
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if ($dateFrom) {
            $invoices = $invoices->filter(function ($item) use ($dateFrom) {
                $date = $item['Created_At'] ?? $item['Due_Date'] ?? null;
                if (!$date) return true;
                try { return \Carbon\Carbon::parse($date)->gte(\Carbon\Carbon::parse($dateFrom)->startOfDay()); }
                catch (\Exception $e) { return true; }
            });
        }
        if ($dateTo) {
            $invoices = $invoices->filter(function ($item) use ($dateTo) {
                $date = $item['Created_At'] ?? $item['Due_Date'] ?? null;
                if (!$date) return true;
                try { return \Carbon\Carbon::parse($date)->lte(\Carbon\Carbon::parse($dateTo)->endOfDay()); }
                catch (\Exception $e) { return true; }
            });
        }

        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $classRepo = app(\App\Repositories\GoogleSheets\ClassRepository::class);
        $batchRepo = app(\App\Repositories\GoogleSheets\BatchRepository::class);
        
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        $classes = $classRepo->fetchAll()->keyBy('Class_ID');
        $batches = $batchRepo->fetchAll()->keyBy('Batch_ID');

        $invoices = $invoices->map(function ($item) use ($students, $classes, $batches) {
            $studentId = $item['Student_ID'] ?? null;
            if ($studentId && isset($students[$studentId])) {
                $student = $students[$studentId];
                $item['student_name'] = $student['Full_Name'] ?? '-';
                
                $classId = $student['Class_ID'] ?? null;
                $batchId = $student['Batch_ID'] ?? null;
                
                $item['class_name'] = $classId && isset($classes[$classId]) ? $classes[$classId]['Class_Name'] : '-';
                $item['batch_name'] = $batchId && isset($batches[$batchId]) ? $batches[$batchId]['Batch_Name'] : '-';
            } else {
                $item['student_name'] = '-';
                $item['class_name'] = '-';
                $item['batch_name'] = '-';
            }
            return $item;
        });
        
        return [
            'moduleName' => 'Tagihan (Invoices)',
            'data' => collect(array_values($invoices->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Tagihan', 'Siswa', 'Kelas / Batch', 'Kategori', 'Nominal', 'Jatuh Tempo', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Invoice_ID'] ?? '-', 
                    $row['student_name'] ?? '-', 
                    trim(($row['class_name'] ?? '') . ' / ' . ($row['batch_name'] ?? ''), ' /'), 
                    $row['Category'] ?? '-', 
                    'Rp ' . number_format($row['Amount'] ?? 0, 0, ',', '.'),
                    isset($row['Due_Date']) ? \Carbon\Carbon::parse($row['Due_Date'])->format('d M Y') : '-',
                    $row['Status'] ?? 'Draft'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$invoices->count().'</td></tr>'
        ];
    }

    protected $invoiceService, $enterpriseEvent;

    public function __construct(
        InvoiceService $invoiceService, 
        \App\Services\Core\EnterpriseEventService $enterpriseEvent
    ) {
        $this->invoiceService = $invoiceService;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function index(Request $request, StudentRepository $studentRepo)
    {
        $invoices = $this->invoiceService->getAll();
        
        $type = $request->input('type');
        if ($type) {
            $invoices = $invoices->where('Invoice_Type', $type);
        }

        // Search filter
        $search = $request->input('search');
        if ($search) {
            $invoices = $invoices->filter(function ($item) use ($search) {
                $searchLower = strtolower($search);
                return str_contains(strtolower($item['Invoice_ID'] ?? ''), $searchLower)
                    || str_contains(strtolower($item['Student_ID'] ?? ''), $searchLower)
                    || str_contains(strtolower($item['Category'] ?? ''), $searchLower)
                    || str_contains(strtolower($item['student_name'] ?? ''), $searchLower);
            });
        }

        // Date filter
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if ($dateFrom) {
            $invoices = $invoices->filter(function ($item) use ($dateFrom) {
                $date = $item['Created_At'] ?? $item['Due_Date'] ?? null;
                if (!$date) return true;
                try { return \Carbon\Carbon::parse($date)->gte(\Carbon\Carbon::parse($dateFrom)->startOfDay()); }
                catch (\Exception $e) { return true; }
            });
        }
        if ($dateTo) {
            $invoices = $invoices->filter(function ($item) use ($dateTo) {
                $date = $item['Created_At'] ?? $item['Due_Date'] ?? null;
                if (!$date) return true;
                try { return \Carbon\Carbon::parse($date)->lte(\Carbon\Carbon::parse($dateTo)->endOfDay()); }
                catch (\Exception $e) { return true; }
            });
        }

        // Enrich invoices with student data (name, class, batch)
        $studentRepo = app(\App\Repositories\GoogleSheets\StudentRepository::class);
        $classRepo = app(\App\Repositories\GoogleSheets\ClassRepository::class);
        $batchRepo = app(\App\Repositories\GoogleSheets\BatchRepository::class);
        
        $students = $studentRepo->fetchAll()->keyBy('Student_ID');
        $classes = $classRepo->fetchAll()->keyBy('Class_ID');
        $batches = $batchRepo->fetchAll()->keyBy('Batch_ID');

        $invoices = $invoices->map(function ($item) use ($students, $classes, $batches) {
            $studentId = $item['Student_ID'] ?? null;
            if ($studentId && isset($students[$studentId])) {
                $student = $students[$studentId];
                $item['student_name'] = $student['Full_Name'] ?? '-';
                
                $classId = $student['Class_ID'] ?? null;
                $batchId = $student['Batch_ID'] ?? null;
                
                $item['class_name'] = $classId && isset($classes[$classId]) ? $classes[$classId]['Class_Name'] : '-';
                $item['batch_name'] = $batchId && isset($batches[$batchId]) ? $batches[$batchId]['Batch_Name'] : '-';
            } else {
                $item['student_name'] = '-';
                $item['class_name'] = '-';
                $item['batch_name'] = '-';
            }
            return $item;
        });

        // Pagination
        $invoices = \App\Helpers\CollectionHelper::paginate($invoices, 10)->withQueryString();

        return view('finance.invoices.index', compact('invoices'));
    }

    public function create(
        StudentRepository $studentRepo, 
        CompanyRepository $companyRepo, 
        SystemSettingService $settingService,
        \App\Repositories\GoogleSheets\BatchRepository $batchRepo,
        \App\Repositories\GoogleSheets\ClassRepository $classRepo
    ) {
        $students = $studentRepo->fetchAll()->where('Is_Active', '!=', 'FALSE');
        $companies = $companyRepo->fetchAll()->where('Is_Active', '!=', 'FALSE');
        $batches = $batchRepo->fetchAll()->where('Is_Active', '!=', 'FALSE');
        $classes = $classRepo->fetchAll()->where('Is_Active', '!=', 'FALSE');
        
        // Define universal categories
        $categories = [
            'Biaya Pendidikan',
            'Pendaftaran',
            'Pelatihan',
            'Korporat',
            'Gaji & Honor',
            'Operasional',
            'Lainnya'
        ];

        return view('finance.invoices.create', compact('students', 'companies', 'categories', 'batches', 'classes'));
    }

    public function store(Request $request) // Using generic request for now, we'll create StoreInvoiceRequest soon
    {
        try {
            $request->validate([
                'Invoice_Type' => 'required|in:STUDENT,COMPANY,PAYROLL,OTHER',
                'Category' => 'required|string',
                'Amount' => 'required|numeric|min:0',
                'Due_Date' => 'required|date',
                'Description' => 'nullable|string',
                'Student_ID' => 'required_if:Invoice_Type,STUDENT',
                'Company_ID' => 'required_if:Invoice_Type,COMPANY',
            ]);

            $data = $request->except('_token');
            $data['Status'] = 'Draft';
            
            $this->invoiceService->create($data);
            
            $targetId = $data['Invoice_Type'] === 'STUDENT' ? ($data['Student_ID'] ?? '-') : ($data['Company_ID'] ?? '-');
            
            $this->enterpriseEvent->dispatch(
                'FINANCE',
                'CREATE',
                'INVOICE',
                $data['Invoice_ID'] ?? 'NEW',
                Auth::id() ?? 'SYSTEM',
                ['FINANCE'],
                $data['Invoice_Type'] === 'STUDENT' ? [$data['Student_ID']] : [],
                [
                    'title' => 'Mendaftarkan Invoice Baru',
                    'description' => "Mendaftarkan draft invoice baru tipe {$data['Invoice_Type']} untuk {$targetId}"
                ]
            );

            return redirect()->route('invoices.index')->with('success', 'Draft invoice berhasil dibuat.');
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
        return view('finance.invoices.show', compact('invoice'));
    }

    public function edit(
        $id, 
        StudentRepository $studentRepo, 
        CompanyRepository $companyRepo, 
        SystemSettingService $settingService,
        \App\Repositories\GoogleSheets\BatchRepository $batchRepo,
        \App\Repositories\GoogleSheets\ClassRepository $classRepo
    ) {
        $invoice = $this->invoiceService->getById($id);
        if (!$invoice) {
            return redirect()->route('invoices.index')->with('error', 'Invoice tidak ditemukan.');
        }
        
        $students = $studentRepo->fetchAll()->where('Is_Active', '!=', 'FALSE');
        $companies = $companyRepo->fetchAll()->where('Is_Active', '!=', 'FALSE');
        $batches = $batchRepo->fetchAll()->where('Is_Active', '!=', 'FALSE');
        $classes = $classRepo->fetchAll()->where('Is_Active', '!=', 'FALSE');
        
        $categories = [
            'Biaya Pendidikan',
            'Pendaftaran',
            'Pelatihan',
            'Korporat',
            'Gaji & Honor',
            'Operasional',
            'Lainnya'
        ];

        return view('finance.invoices.edit', compact('invoice', 'students', 'companies', 'categories', 'batches', 'classes'));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'Invoice_Type' => 'required|in:STUDENT,COMPANY,PAYROLL,OTHER',
                'Category' => 'required|string',
                'Amount' => 'required|numeric|min:0',
                'Due_Date' => 'required|date',
                'Description' => 'nullable|string',
                'Student_ID' => 'required_if:Invoice_Type,STUDENT',
                'Company_ID' => 'required_if:Invoice_Type,COMPANY',
            ]);

            $data = $request->except(['_token', '_method']);
            $this->invoiceService->update($id, $data);
            
            $this->enterpriseEvent->dispatch(
                'FINANCE',
                'UPDATE',
                'INVOICE',
                $id,
                Auth::id() ?? 'SYSTEM',
                ['FINANCE'],
                $data['Invoice_Type'] === 'STUDENT' ? [$data['Student_ID']] : [],
                [
                    'title' => 'Mengupdate Invoice',
                    'description' => 'Mengupdate invoice ' . $id
                ]
            );

            return redirect()->route('invoices.index')->with('success', 'Invoice berhasil diupdate.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function notify(Request $request, $id)
    {
        try {
            $message = $request->input('message');
            $invoice = $this->invoiceService->getById($id);
            $studentId = $invoice && $invoice['Invoice_Type'] === 'STUDENT' ? $invoice['Student_ID'] : null;
            $this->invoiceService->sendNotification($id, $message);
            $this->enterpriseEvent->dispatch(
                'FINANCE',
                'NOTIFY',
                'INVOICE',
                $id,
                Auth::id() ?? 'SYSTEM',
                ['FINANCE'],
                $studentId ? [$studentId] : [],
                [
                    'title' => 'Mengirim Notifikasi Invoice',
                    'description' => "Mengirim notifikasi untuk invoice {$id}"
                ]
            );
            return back()->with('success', 'Notifikasi berhasil dikirim.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function publish(Request $request, $id)
    {
        try {
            $this->invoiceService->publish($id);
            $invoice = $this->invoiceService->getById($id);
            $studentId = $invoice && $invoice['Invoice_Type'] === 'STUDENT' ? $invoice['Student_ID'] : null;
            $this->enterpriseEvent->dispatch(
                'FINANCE',
                'PUBLISH',
                'INVOICE',
                $id,
                Auth::id() ?? 'SYSTEM',
                ['FINANCE'],
                $studentId ? [$studentId] : [],
                [
                    'title' => 'Publish Invoice',
                    'description' => "Publish invoice {$id} ke Waiting Payment"
                ]
            );
            return back()->with('success', 'Invoice berhasil dipublish.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        try {
            $this->invoiceService->delete($id);
            $this->enterpriseEvent->dispatch(
                'FINANCE',
                'DELETE',
                'INVOICE',
                $id,
                Auth::id() ?? 'SYSTEM',
                ['FINANCE'],
                [],
                [
                    'title' => 'Menghapus Invoice',
                    'description' => 'Menghapus invoice ' . $id
                ]
            );
            return redirect()->route('invoices.index')->with('success', 'Invoice berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

