<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use Carbon\Carbon;

class DirectorDashboardController extends Controller
{
    protected $employeeRepo, $teacherRepo, $studentRepo, $companyRepo, $documentRepo, $activityLogRepo, $programRepo, $batchRepo;

    public function __construct(
        EmployeeRepositoryInterface $employeeRepo,
        TeacherRepositoryInterface $teacherRepo,
        StudentRepositoryInterface $studentRepo,
        CompanyRepositoryInterface $companyRepo,
        DocumentRepositoryInterface $documentRepo,
        ActivityLogRepositoryInterface $activityLogRepo,
        \App\Interfaces\GoogleSheets\ProgramRepositoryInterface $programRepo,
        \App\Interfaces\GoogleSheets\BatchRepositoryInterface $batchRepo
    ) {
        $this->employeeRepo = $employeeRepo;
        $this->teacherRepo = $teacherRepo;
        $this->studentRepo = $studentRepo;
        $this->companyRepo = $companyRepo;
        $this->documentRepo = $documentRepo;
        $this->activityLogRepo = $activityLogRepo;
        $this->programRepo = $programRepo;
        $this->batchRepo = $batchRepo;
    }

    public function index()
    {
        try {
            $dashboardData = Cache::remember('wms_director_dashboard_data', 300, function () {
                $countActive = function($repo) {
                    return collect($repo->fetchAll())->where('Is_Active', '!=', 'FALSE')->count();
                };

                // 1. Executive KPI Counts
                $kpi = [
                    'students' => $countActive($this->studentRepo),
                    'teachers' => $countActive($this->teacherRepo),
                    'employees' => $countActive($this->employeeRepo),
                    'companies' => $countActive($this->companyRepo),
                    'documents' => $countActive($this->documentRepo),
                ];

                // 2. Executive Charts Data
                $students = collect($this->studentRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');
                
                // Student per Program
                $programs = collect($this->programRepo->fetchAll())->keyBy('Program_ID');
                $studentProgramCount = $students->groupBy('Program_ID')->map->count();
                $studentProgramLabels = [];
                $studentProgramData = [];
                foreach ($studentProgramCount as $progId => $count) {
                    $progName = isset($programs[$progId]) ? $programs[$progId]['Program_Name'] : 'Tidak Diketahui';
                    $studentProgramLabels[] = $progName;
                    $studentProgramData[] = $count;
                }

                // Student per Batch
                $batches = collect($this->batchRepo->fetchAll())->keyBy('Batch_ID');
                $studentBatchCount = $students->groupBy('Batch_ID')->map->count();
                $studentBatchLabels = [];
                $studentBatchData = [];
                foreach ($studentBatchCount as $batchId => $count) {
                    $batchName = isset($batches[$batchId]) ? $batches[$batchId]['Batch_Name'] : 'Tidak Diketahui';
                    $studentBatchLabels[] = $batchName;
                    $studentBatchData[] = $count;
                }

                // Application Status (Removed due to SSOT)

                $charts = [
                    'studentProgram' => ['labels' => $studentProgramLabels, 'data' => $studentProgramData],
                    'studentBatch' => ['labels' => $studentBatchLabels, 'data' => $studentBatchData],
                ];

                // 3. Executive Summary
                $summary = [
                    'active_students' => $kpi['students'],
                    'active_employees' => $kpi['employees'],
                    'active_companies' => $kpi['companies'],
                ];

                // 4. Notifications
                $today = Carbon::today()->format('Y-m-d');
                $thirtyDays = Carbon::today()->addDays(30)->format('Y-m-d');

                $notifications = [
                    'interviewsToday' => [],
                    'pendingApplications' => []
                ];

                // 5. Recent Activity (Lintas Modul dari AUDIT_LOG)
                try {
                    $recentActivities = collect($this->activityLogRepo->fetchAll())
                        ->sortByDesc('Created_At')
                        ->take(10)
                        ->map(function ($log) {
                            $desc = $log['Description'] ?? ($log['New_Value'] ?? '');
                            if (is_string($desc) && str_starts_with($desc, '{')) {
                                $decoded = json_decode($desc, true);
                                if (is_array($decoded) && isset($decoded['description'])) {
                                    $desc = $decoded['description'];
                                } elseif (is_array($decoded) && isset($decoded['title'])) {
                                    $desc = $decoded['title'];
                                } else {
                                    $action = str_replace('_', ' ', $log['Action'] ?? '');
                                    $refId = $log['Reference_ID'] ?? '';
                                    $desc = "Aktivitas " . ucwords(strtolower($action)) . ($refId ? " pada {$refId}" : '');
                                }
                            }
                            return [
                                'title'       => $log['Action'] ?? 'Aktivitas',
                                'description' => ($log['Module'] ?? '') . ' — ' . $desc,
                                'time'        => isset($log['Created_At']) ? Carbon::parse($log['Created_At'])->diffForHumans() : 'Baru saja',
                            ];
                        })
                        ->values()
                        ->toArray();
                } catch (\Exception $e) {
                    $recentActivities = [];
                    \Illuminate\Support\Facades\Log::error('Failed to fetch audit log for director dashboard: ' . $e->getMessage());
                }

                return compact('kpi', 'charts', 'summary', 'notifications', 'recentActivities');
            });
        } catch (\Exception $e) {
            $dashboardData = ['api_error' => true, 'error_message' => $this->safeExceptionMessage($e)];
        }

        return view('dashboard.director', $dashboardData);
    }
}
