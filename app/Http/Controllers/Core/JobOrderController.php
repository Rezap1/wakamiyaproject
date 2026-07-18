<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobOrderRequest;
use App\Http\Requests\UpdateJobOrderRequest;
use App\Services\Core\JobOrderService;
use App\Services\Core\ActivityLogService;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class JobOrderController extends Controller
{
    protected $jobOrderService;
    protected $activityLogService;
    protected $companyRepository;
    protected $employeeRepository;

    public function __construct(
        JobOrderService $jobOrderService, 
        ActivityLogService $activityLogService,
        CompanyRepositoryInterface $companyRepository,
        EmployeeRepositoryInterface $employeeRepository
    ) {
        $this->jobOrderService = $jobOrderService;
        $this->activityLogService = $activityLogService;
        $this->companyRepository = $companyRepository;
        $this->employeeRepository = $employeeRepository;
    }

    public function index()
    {
        try {
            $jobOrders = $this->jobOrderService->getAllJobOrders();
            $companies = $this->companyRepository->fetchAll();
            
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $jobOrders->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $jobOrdersPaginated = new LengthAwarePaginator($currentItems, count($jobOrders), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
            
            return view('job_orders.index', [
                'jobOrders' => $jobOrdersPaginated,
                'companies' => $companies
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching job orders: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data Job Order dari Google Sheets.');
        }
    }

    public function create()
    {
        try {
            $companies = $this->companyRepository->fetchAll()->where('Is_Active', 'TRUE');
            $employees = $this->employeeRepository->fetchAll()->where('Is_Active', 'TRUE');
            return view('job_orders.create', compact('companies', 'employees'));
        } catch (\Exception $e) {
            Log::error('Error loading job order create dependencies: ' . $e->getMessage());
            return redirect()->route('job-orders.index')->with('error', 'Gagal memuat referensi data perusahaan atau PIC.');
        }
    }

    public function store(StoreJobOrderRequest $request)
    {
        try {
            $data = $request->validated();
            $jobOrder = $this->jobOrderService->createJobOrder($data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'CREATE',
                'JOB_ORDER',
                "Mendaftarkan Job Order baru: {$jobOrder['Job_Order_ID']}",
                $request->ip(),
                null,
                $jobOrder,
                $request->userAgent()
            );

            return redirect()->route('job-orders.index')->with('success', 'Job Order berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating job order: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data ke Google Sheets.')->withInput();
        }
    }

    public function show($id)
    {
        try {
            $jobOrder = $this->jobOrderService->getJobOrderById($id);
            if (!$jobOrder) {
                return redirect()->route('job-orders.index')->with('error', 'Job Order tidak ditemukan.');
            }
            
            $employee = null;
            if (!empty($jobOrder['PIC_Employee_ID'])) {
                $employee = $this->employeeRepository->findById($jobOrder['PIC_Employee_ID']);
            }
            
            return view('job_orders.show', compact('jobOrder', 'employee'));
        } catch (\Exception $e) {
            Log::error('Error showing job order: ' . $e->getMessage());
            return redirect()->route('job-orders.index')->with('error', 'Terjadi kesalahan saat memuat data Job Order.');
        }
    }

    public function edit($id)
    {
        try {
            $jobOrder = $this->jobOrderService->getJobOrderById($id);
            if (!$jobOrder) {
                return redirect()->route('job-orders.index')->with('error', 'Job Order tidak ditemukan.');
            }
            
            $companies = $this->companyRepository->fetchAll()->where('Is_Active', 'TRUE');
            $employees = $this->employeeRepository->fetchAll()->where('Is_Active', 'TRUE');
            
            return view('job_orders.edit', compact('jobOrder', 'companies', 'employees'));
        } catch (\Exception $e) {
            Log::error('Error editing job order: ' . $e->getMessage());
            return redirect()->route('job-orders.index')->with('error', 'Terjadi kesalahan saat memuat data Job Order.');
        }
    }

    public function update(UpdateJobOrderRequest $request, $id)
    {
        try {
            $jobOrder = $this->jobOrderService->getJobOrderById($id);
            if (!$jobOrder) {
                return redirect()->route('job-orders.index')->with('error', 'Job Order tidak ditemukan.');
            }

            $data = $request->validated();
            $this->jobOrderService->updateJobOrder($id, $data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'JOB_ORDER',
                "Memperbarui Job Order: {$id}",
                $request->ip(),
                $jobOrder,
                array_merge($jobOrder, $data),
                $request->userAgent()
            );

            return redirect()->route('job-orders.index')->with('success', 'Job Order berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating job order: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data di Google Sheets.')->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $jobOrder = $this->jobOrderService->getJobOrderById($id);
            if (!$jobOrder) {
                return redirect()->route('job-orders.index')->with('error', 'Job Order tidak ditemukan.');
            }

            $this->jobOrderService->deleteJobOrder($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'JOB_ORDER',
                "Menonaktifkan Job Order (Soft Delete): {$id}",
                request()->ip(),
                $jobOrder,
                array_merge($jobOrder, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('job-orders.index')->with('success', 'Job Order berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deleting job order: ' . $e->getMessage());
            return redirect()->route('job-orders.index')->with('error', 'Terjadi kesalahan saat menghapus data di Google Sheets.');
        }
    }
}
