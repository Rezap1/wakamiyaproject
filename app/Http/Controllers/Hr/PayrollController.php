<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HR\PayrollService;
use App\Http\Requests\StorePayrollRequest;
use App\Http\Requests\GenerateBatchPayrollRequest;
use App\Helpers\ReportHelper;
use App\Helpers\StoragePathHelper;
use App\Helpers\UserResolverHelper;
use App\Support\Reporting\HumanReadableResolver;

class PayrollController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Payroll_Period';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $payrolls = $this->payrollService->getAll()->filter(function ($item) {
            return ($item['Status'] ?? '') !== 'Cancelled';
        });

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = \Carbon\Carbon::parse($request->input('date_from'))->startOfDay();
            $dateTo = \Carbon\Carbon::parse($request->input('date_to'))->endOfDay();
            
            $payrolls = $payrolls->filter(function ($item) use ($dateFrom, $dateTo) {
                $dateStr = $item['Created_At'] ?? null;
                if ($dateStr) {
                    $itemDate = \Carbon\Carbon::parse($dateStr);
                    return $itemDate->between($dateFrom, $dateTo);
                }
                return false;
            });
        }

        $employeesById = collect(app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll())
            ->keyBy('Employee_ID');
        
        return [
            'moduleName' => 'Penggajian (Payroll)',
            'data' => collect(array_values($payrolls->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['No. Payroll', 'Pegawai', 'Periode', 'Gaji Pokok', 'Potongan', 'Gaji Bersih', 'Status'],
            'mapRow' => function($row) use ($employeesById) {
                return [
                    $row['Payroll_Number'] ?? '-',
                    HumanReadableResolver::employeeName($row['Employee_ID'] ?? '', $employeesById),
                    $row['Payroll_Period'] ?? '-',
                    'Rp ' . number_format((float)($row['Base_Salary'] ?? 0), 0, ',', '.'),
                    'Rp ' . number_format((float)($row['Total_Deductions'] ?? 0), 0, ',', '.'),
                    'Rp ' . number_format((float)($row['Net_Salary'] ?? 0), 0, ',', '.'),
                    $row['Status'] ?? 'Draft'
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data Payroll</td><td>: '.$payrolls->count().'</td></tr>'
        ];
    }

    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function index(Request $request)
    {
        $payrolls = $this->payrollService->getAll()->filter(function ($item) {
            return ($item['Status'] ?? '') !== 'Cancelled';
        });

        $user = auth()->user();
        if ($user && in_array(strtoupper($user->Role ?? ''), ['TEACHER', 'EMPLOYEE'])) {
            $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
            $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($employee) {
                $payrolls = $payrolls->where('Employee_ID', $employee['Employee_ID'])->values();
            } else {
                $payrolls = collect();
            }
        }

        $search = $request->input('search');
        if ($search) {
            $payrolls = $payrolls->filter(function($item) use ($search) {
                $empName = UserResolverHelper::getName($item['Employee_ID'] ?? '');
                return stripos($item['Payroll_ID'] ?? '', $search) !== false ||
                       stripos($item['Payroll_Number'] ?? '', $search) !== false ||
                       stripos($item['Employee_ID'] ?? '', $search) !== false ||
                       stripos($empName, $search) !== false;
            });
        }

        $payrolls = $payrolls->map(function($p) {
            $p['Employee_Name'] = UserResolverHelper::getName($p['Employee_ID'] ?? '');
            $p['Approved_By_Name'] = UserResolverHelper::getName($p['Approved_By'] ?? '');
            return $p;
        });

        $payrolls = \App\Helpers\CollectionHelper::paginate($payrolls, 10)->withQueryString();

        return view('hr.payroll.index', compact('payrolls', 'search'));
    }

    public function create()
    {
        $employees = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll();
        return view('hr.payroll.create', compact('employees'));
    }

    public function store(StorePayrollRequest $request)
    {
        try {
            $payroll = $this->payrollService->processPayroll($request->validated());
            $id = $payroll['Payroll_ID'] ?? ($payroll['id'] ?? null);
            if ($id) {
                return redirect()->route('payrolls.show', ['id' => $id])
                    ->with('success', 'Payroll berhasil dibuat secara deterministik sebagai Draft.');
            }
            return redirect()->route('payrolls.index')->with('success', 'Payroll berhasil dibuat.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function batchGenerate(GenerateBatchPayrollRequest $request)
    {
        try {
            $period = $request->input('Payroll_Period');
            $generated = $this->payrollService->generateBatchPayroll($period);
            return redirect()->route('payrolls.index')
                ->with('success', "Batch Payroll periode {$period} berhasil diproses untuk " . count($generated) . " pegawai.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function show($id)
    {
        try {
            $docData = $this->payrollService->getPayslipDocumentData($id);
            if (isset($docData['payroll']['Approved_By'])) {
                $docData['payroll']['Approved_By_Name'] = UserResolverHelper::getName($docData['payroll']['Approved_By']);
            }
            return view('hr.payroll.show', ['payroll' => $docData['payroll'], 'docData' => $docData]);
        } catch (\Exception $e) {
            return redirect()->route('payrolls.index')->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function submit($id)
    {
        try {
            $user = $this->authenticatedActor();
            $this->payrollService->updateStatus($id, 'Waiting Approval', $user);
            return redirect()->route('payrolls.show', $id)->with('success', 'Payroll berhasil diajukan untuk persetujuan (Waiting Approval).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function approve($id)
    {
        try {
            $user = $this->authenticatedActor();
            $this->payrollService->updateStatus($id, 'Approved', $user);
            return redirect()->route('payrolls.show', $id)->with('success', 'Payroll berhasil disetujui (Approved).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function reject($id)
    {
        try {
            $user = $this->authenticatedActor();
            $this->payrollService->updateStatus($id, 'Rejected', $user);
            return redirect()->route('payrolls.show', $id)->with('success', 'Payroll ditolak (Rejected).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function pay(Request $request, $id)
    {
        $paymentProofPath = null;

        try {
            $user = $this->authenticatedActor();
            $request->validate([
                'Payment_Proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:' . config('upload.max_kb', 5120)
            ]);

            if ($request->hasFile('Payment_Proof')) {
                $file = $request->file('Payment_Proof');
                $filename = 'proof_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('proofs', $filename);
                $paymentProofPath = 'proofs/' . $filename;
            }

            $notes = $request->input('Notes');
            $this->payrollService->updateStatus($id, 'Paid', $user, $paymentProofPath, $notes);
            return redirect()->route('payrolls.show', $id)->with('success', 'Payroll lunas (Paid) dan jurnal kas pengeluaran tercatat.');
        } catch (\Exception $e) {
            if ($paymentProofPath) {
                try {
                    $payroll = $this->payrollService->getById($id);
                    if (($payroll['Payment_Proof'] ?? '') !== $paymentProofPath) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($paymentProofPath);
                    }
                } catch (\Throwable $lookupFailure) {
                    // Preserve the file when persistence cannot be determined safely.
                }
            }
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function downloadPayslipPdf($id)
    {
        try {
            $docData = $this->payrollService->getPayslipDocumentData($id);
            if (isset($docData['payroll']['Approved_By'])) {
                $docData['payroll']['Approved_By'] = UserResolverHelper::getName($docData['payroll']['Approved_By']);
            }
            
            return ReportHelper::export(
                'pdf',
                'Slip_Gaji_' . $id,
                collect([$docData['payroll']]),
                $docData,
                'pdf.official_payslip',
                [],
                null,
                false
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function verifyPayslipPublic($id)
    {
        try {
            $docData = $this->payrollService->getPayslipDocumentData($id, true);
            if (isset($docData['payroll']['Approved_By'])) {
                $docData['payroll']['Approved_By'] = UserResolverHelper::getName($docData['payroll']['Approved_By']);
            }
            return view('finance.payrolls.verify_payslip_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $this->safeExceptionMessage($e, 'Slip gaji tidak ditemukan atau tidak tersedia.'));
        }
    }

    public function destroy($id)
    {
        try {
            $this->payrollService->delete($id);
            return redirect()->route('payrolls.index')->with('success', 'Payroll berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function edit($id)
    {
        try {
            $docData = $this->payrollService->getPayslipDocumentData($id);
            $employees = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll();
            return view('hr.payroll.show', ['payroll' => $docData['payroll'], 'docData' => $docData, 'employees' => $employees]);
        } catch (\Exception $e) {
            return redirect()->route('payrolls.index')->with('error', $this->safeExceptionMessage($e));
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'Status' => 'required|in:Draft,Waiting Approval,Approved,Rejected,Paid,Closed',
            ]);
            $user = $this->authenticatedActor();
            $this->payrollService->updateStatus($id, $validated['Status'], $user);
            return redirect()->route('payrolls.show', $id)->with('success', 'Status Payroll berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function downloadProof(Request $request, $id)
    {
        $payroll = $this->payrollService->getById($id);
        if (!$payroll || empty($payroll['Payment_Proof'])) {
            abort(404, 'Bukti pembayaran tidak ditemukan.');
        }
        $path = StoragePathHelper::privateFileResponsePath($payroll['Payment_Proof']);
        if (!$path) {
            abort(404, 'File bukti pembayaran tidak ditemukan di server.');
        }

        if ($request->boolean('inline')) {
            return response()->file($path);
        }

        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $id);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return response()->download($path, 'bukti-pembayaran-payroll-' . $safeId . ($extension ? '.' . $extension : ''));
    }

    private function authenticatedActor(): string
    {
        $user = auth()->user();
        $actor = $user->User_ID ?? $user->Email ?? $user->email ?? null;
        if (!$actor) {
            abort(403, 'Identitas pengguna tidak valid.');
        }

        return $actor;
    }
}
