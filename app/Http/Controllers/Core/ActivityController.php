<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\ActivityService;
use App\Services\Core\RoleService;
use App\Helpers\ReportHelper;

class ActivityController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Timestamp';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $user = auth()->user();
        $userId = $user->Employee_ID ?? $user->User_ID ?? \App\Support\ActorIdentity::required();
        $roleData = $this->roleService->getRoleById($user->Role_ID ?? '');
        $roleName = strtoupper(trim($roleData['Role_Name'] ?? ''));

        $filters = $request->only(['keyword', 'module']);
        $activities = $this->activityService->getActivities($roleName, $userId, $filters);
        
        return [
            'moduleName' => 'Log Aktivitas (Activity)',
            'data' => collect(array_values($activities->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID', 'Waktu', 'Modul', 'Aktivitas', 'Aktor'],
            'mapRow' => function($row) {

                return [
                    $row['Activity_ID'] ?? '-',
                    isset($row['Created_At']) ? \Carbon\Carbon::parse($row['Created_At'])->format('d M Y H:i:s') : '-',
                    $row['Module'] ?? '-',
                    $row['Action'] ?? '-',
                    $row['Actor'] ?? '-'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$activities->count().'</td></tr>'
        ];
    }

    protected $activityService;
    protected $roleService;

    public function __construct(ActivityService $activityService, RoleService $roleService)
    {
        $this->activityService = $activityService;
        $this->roleService = $roleService;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $userId = $user->Employee_ID ?? $user->User_ID;
        $roleData = $this->roleService->getRoleById($user->Role_ID);
        $roleName = strtoupper(trim($roleData['Role_Name'] ?? ''));

        $filters = $request->only(['keyword', 'module']);
        
        $activities = $this->activityService->getActivities($roleName, $userId, $filters);
        $kpis = $this->activityService->calculateKPIs($activities);
        $groupedActivities = $this->activityService->groupActivities($activities);

        // Extract unique modules for filter dropdown
        $allActivities = $this->activityService->getActivities($roleName, $userId, []); // without text filters
        $availableModules = $allActivities->pluck('Module')->unique()->filter()->sort()->values();

        return view('activity.index', compact('groupedActivities', 'kpis', 'availableModules', 'filters', 'roleName'));
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        $userId = $user->Employee_ID ?? $user->User_ID;
        $roleData = $this->roleService->getRoleById($user->Role_ID);
        $roleName = strtoupper(trim($roleData['Role_Name'] ?? ''));

        $filters = $request->only(['keyword', 'module']);
        $activities = $this->activityService->getActivities($roleName, $userId, $filters);

        $filename = "audit_log_" . date('Ymd_His') . ".csv";
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($activities) {
            $file = fopen('php://output', 'w');
            $sanitize = [ReportHelper::class, 'sanitizeCsvCell'];
            fputcsv($file, array_map($sanitize, ['Timestamp', 'User', 'Role', 'Module', 'Action', 'Description', 'IP Address']));
            foreach ($activities as $a) {
                fputcsv($file, array_map($sanitize, [
                    $a['Timestamp'] ?? '',
                    $a['User_ID'] ?? '',
                    $a['Role'] ?? '',
                    $a['Module'] ?? '',
                    $a['Action'] ?? '',
                    $a['Description'] ?? '',
                    $a['IP_Address'] ?? ''
                ]));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
