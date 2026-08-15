<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HR\PayrollService;
use App\Http\Requests\StorePayrollRequest;
use App\Http\Requests\GenerateBatchPayrollRequest;
use App\Helpers\ReportHelper;
use App\Helpers\UserResolverHelper;

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
        
        return [
            'moduleName' => 'Penggajian (Payroll)',
            'data' => collect(array_values($payrolls->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Payroll', 'No. Payroll', 'Pegawai', 'Periode', 'Gaji Pokok', 'Potongan', 'Gaji Bersih', 'Status'],
            'mapRow' => function($row) {
                $empId = $row['Employee_ID'] ?? null;
                $empName = UserResolverHelper::getName($empId);
                
                return [
                    $row['Payroll_ID'] ?? '-',
                    $row['Payroll_Number'] ?? '-',
                    $empName,
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
            return redirect()->route('payrolls.show', $payroll['Payroll_ID'])
                ->with('success', 'Payroll berhasil dibuat secara deterministik sebagai Draft.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
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
            return back()->withErrors(['error' => $e->getMessage()]);
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
            return redirect()->route('payrolls.index')->with('error', $e->getMessage());
        }
    }

    public function submit($id)
    {
        try {
            $user = auth()->user()->Email ?? 'HR Admin';
            $this->payrollService->updateStatus($id, 'Waiting Approval', $user);
            return redirect()->route('payrolls.show', $id)->with('success', 'Payroll berhasil diajukan untuk persetujuan (Waiting Approval).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function approve($id)
    {
        try {
            $user = auth()->user()->Email ?? 'Director';
            $this->payrollService->updateStatus($id, 'Approved', $user);
            return redirect()->route('payrolls.show', $id)->with('success', 'Payroll berhasil disetujui (Approved).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reject($id)
    {
        try {
            $user = auth()->user()->Email ?? 'Director';
            $this->payrollService->updateStatus($id, 'Rejected', $user);
            return redirect()->route('payrolls.show', $id)->with('success', 'Payroll ditolak (Rejected).');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function pay(Request $request, $id)
    {
        try {
            $user = auth()->user()->Email ?? 'Finance';
            $paymentProofPath = null;
            if ($request->hasFile('Payment_Proof')) {
                $file = $request->file('Payment_Proof');
                $filename = 'proof_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('proofs', $filename, 'public');
                $paymentProofPath = 'storage/proofs/' . $filename;
            }

            $notes = $request->input('Notes');
            $this->payrollService->updateStatus($id, 'Paid', $user, $paymentProofPath, $notes);
            return redirect()->route('payrolls.show', $id)->with('success', 'Payroll lunas (Paid) dan jurnal kas pengeluaran tercatat.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
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
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function verifyPayslipPublic($id)
    {
        try {
            $docData = $this->payrollService->getPayslipDocumentData($id);
            if (isset($docData['payroll']['Approved_By'])) {
                $docData['payroll']['Approved_By'] = UserResolverHelper::getName($docData['payroll']['Approved_By']);
            }
            return view('finance.payrolls.verify_payslip_public', ['data' => $docData]);
        } catch (\Exception $e) {
            abort(404, $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $this->payrollService->delete($id);
            return redirect()->route('payrolls.index')->with('success', 'Payroll berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
