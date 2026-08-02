<?php
$baseDir = __DIR__;

// --- 1. ActivityService ---
$activityService = <<<PHP
<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class ActivityService
{
    protected \$repo;
    
    protected \$moduleWhitelist = [
        'HR' => ['Employee', 'Department', 'Position'],
        'ACADEMIC' => ['Program', 'Batch', 'Class', 'Teacher', 'Student', 'Subject', 'Schedule', 'Assignment', 'Submission', 'Attendance', 'Score', 'Announcement'],
        'MARKETING' => ['Company', 'JobOrder', 'Application', 'Interview', 'Matching'],
        'FINANCE' => ['Finance', 'Payment', 'Invoice'],
    ];

    public function __construct(ActivityLogRepositoryInterface \$repo)
    {
        \$this->repo = \$repo;
    }

    public function getActivities(\$role, \$userId, \$filters = [])
    {
        // 1. Generate Cache Key based on Role, User, and Filters
        \$cacheKey = "wms_activity_{\$role}_{\$userId}_" . md5(json_encode(\$filters));
        
        return Cache::remember(\$cacheKey, 60, function () use (\$role, \$userId, \$filters) {
            
            // 2. Fetch base data (limit to 1000 for performance if possible, assuming fetchAll does it or we take it)
            \$allLogs = collect(\$this->repo->fetchAll())->sortByDesc('Timestamp')->take(1000);
            
            // 3. RBAC Filtering
            \$filteredLogs = \$allLogs->filter(function(\$log) use (\$role, \$userId) {
                if (in_array(\$role, ['ADMINISTRATOR', 'DIRECTOR'])) return true;
                if (in_array(\$role, ['TEACHER', 'STUDENT'])) return (\$log['User_ID'] ?? '') === \$userId;
                
                // Whitelist check
                \$allowedModules = \$this->moduleWhitelist[\$role] ?? [];
                return in_array(\$log['Module'] ?? '', \$allowedModules);
            });
            
            // 4. Apply extra filters (from UI)
            if (!empty(\$filters['keyword'])) {
                \$kw = strtolower(\$filters['keyword']);
                \$filteredLogs = \$filteredLogs->filter(function(\$log) use (\$kw) {
                    return str_contains(strtolower(\$log['User_ID'] ?? ''), \$kw) || 
                           str_contains(strtolower(\$log['Module'] ?? ''), \$kw) || 
                           str_contains(strtolower(\$log['Action'] ?? ''), \$kw) || 
                           str_contains(strtolower(\$log['Description'] ?? ''), \$kw);
                });
            }
            if (!empty(\$filters['module'])) {
                \$filteredLogs = \$filteredLogs->where('Module', \$filters['module']);
            }
            
            return \$filteredLogs->values();
        });
    }
    
    public function calculateKPIs(\$activities)
    {
        \$today = date('Y-m-d');
        \$todayActivities = \$activities->filter(function(\$a) use (\$today) {
            return str_starts_with(\$a['Timestamp'] ?? '', \$today);
        });
        
        \$activeUsersToday = \$todayActivities->pluck('User_ID')->unique()->count();
        \$mostActiveModule = \$activities->pluck('Module')->mode()[0] ?? 'N/A';
        \$criticalActivitiesToday = \$todayActivities->filter(function(\$a) {
            return in_array(strtolower(\$a['Action'] ?? ''), ['delete', 'destroy', 'remove', 'error']);
        })->count();
        
        return [
            'today_total' => \$todayActivities->count(),
            'active_users_today' => \$activeUsersToday,
            'most_active_module' => \$mostActiveModule,
            'critical_today' => \$criticalActivitiesToday,
        ];
    }
    
    public function groupActivities(\$activities)
    {
        \$today = date('Y-m-d');
        \$yesterday = date('Y-m-d', strtotime('-1 day'));
        \$last7Days = date('Y-m-d', strtotime('-7 days'));
        
        \$grouped = [
            'Today' => [],
            'Yesterday' => [],
            'Last 7 Days' => [],
            'Older' => []
        ];
        
        foreach (\$activities as \$a) {
            \$date = substr(\$a['Timestamp'] ?? '', 0, 10);
            if (\$date === \$today) {
                \$grouped['Today'][] = \$a;
            } elseif (\$date === \$yesterday) {
                \$grouped['Yesterday'][] = \$a;
            } elseif (\$date >= \$last7Days) {
                \$grouped['Last 7 Days'][] = \$a;
            } else {
                \$grouped['Older'][] = \$a;
            }
        }
        
        return array_filter(\$grouped, function(\$group) { return count(\$group) > 0; });
    }
}
PHP;
@mkdir("$baseDir/app/Services/Core", 0777, true);
file_put_contents("$baseDir/app/Services/Core/ActivityService.php", $activityService);


// --- 2. ActivityController ---
$activityCtrl = <<<PHP
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\ActivityService;
use App\Services\Core\RoleService;

class ActivityController extends Controller
{
    protected \$activityService;
    protected \$roleService;

    public function __construct(ActivityService \$activityService, RoleService \$roleService)
    {
        \$this->activityService = \$activityService;
        \$this->roleService = \$roleService;
    }

    public function index(Request \$request)
    {
        \$user = auth()->user();
        \$userId = \$user->Employee_ID ?? \$user->User_ID;
        \$roleData = \$this->roleService->getRoleById(\$user->Role_ID);
        \$roleName = strtoupper(trim(\$roleData['Role_Name'] ?? ''));

        \$filters = \$request->only(['keyword', 'module']);
        
        \$activities = \$this->activityService->getActivities(\$roleName, \$userId, \$filters);
        \$kpis = \$this->activityService->calculateKPIs(\$activities);
        \$groupedActivities = \$this->activityService->groupActivities(\$activities);

        // Extract unique modules for filter dropdown
        \$allActivities = \$this->activityService->getActivities(\$roleName, \$userId, []); // without text filters
        \$availableModules = \$allActivities->pluck('Module')->unique()->filter()->sort()->values();

        return view('activity.index', compact('groupedActivities', 'kpis', 'availableModules', 'filters', 'roleName'));
    }

    public function export(Request \$request)
    {
        \$user = auth()->user();
        \$userId = \$user->Employee_ID ?? \$user->User_ID;
        \$roleData = \$this->roleService->getRoleById(\$user->Role_ID);
        \$roleName = strtoupper(trim(\$roleData['Role_Name'] ?? ''));

        \$filters = \$request->only(['keyword', 'module']);
        \$activities = \$this->activityService->getActivities(\$roleName, \$userId, \$filters);

        \$filename = "audit_log_" . date('Ymd_His') . ".csv";
        \$headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=\$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        \$callback = function() use(\$activities) {
            \$file = fopen('php://output', 'w');
            fputcsv(\$file, ['Timestamp', 'User', 'Role', 'Module', 'Action', 'Description', 'IP Address']);
            foreach (\$activities as \$a) {
                fputcsv(\$file, [
                    \$a['Timestamp'] ?? '',
                    \$a['User_ID'] ?? '',
                    \$a['Role'] ?? '',
                    \$a['Module'] ?? '',
                    \$a['Action'] ?? '',
                    \$a['Description'] ?? '',
                    \$a['IP_Address'] ?? ''
                ]);
            }
            fclose(\$file);
        };

        return response()->stream(\$callback, 200, \$headers);
    }
}
PHP;
@mkdir("$baseDir/app/Http/Controllers/Core", 0777, true);
file_put_contents("$baseDir/app/Http/Controllers/Core/ActivityController.php", $activityCtrl);

// --- 3. View ---
@mkdir("$baseDir/resources/views/activity", 0777, true);

$activityView = <<<'BLADE'
@extends('layouts.app')
@section('header', 'Enterprise Activity Center')
@section('content')

<!-- KPIs -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-blue-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Today's Activities</h4>
        <p class="text-2xl font-black text-blue-700 mt-1">{{ $kpis['today_total'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-green-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Active Users Today</h4>
        <p class="text-2xl font-black text-green-700 mt-1">{{ $kpis['active_users_today'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-purple-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Most Active Module</h4>
        <p class="text-lg font-black text-purple-700 mt-1 truncate" title="{{ $kpis['most_active_module'] }}">{{ $kpis['most_active_module'] }}</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow border-l-4 border-red-500">
        <h4 class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Critical Activities (Today)</h4>
        <p class="text-2xl font-black text-red-700 mt-1">{{ $kpis['critical_today'] }}</p>
    </div>
</div>

<!-- Action Bar -->
<div class="bg-white p-4 rounded-xl shadow mb-6 border border-slate-100 flex flex-wrap items-center justify-between gap-4">
    <form action="{{ route('activity.index') }}" method="GET" class="flex flex-wrap gap-2 items-center flex-1">
        <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Search activities..." class="border-gray-300 rounded-lg text-sm w-48">
        
        <select name="module" class="border-gray-300 rounded-lg text-sm">
            <option value="">All Modules</option>
            @foreach($availableModules as $mod)
                <option value="{{ $mod }}" {{ request('module') == $mod ? 'selected' : '' }}>{{ $mod }}</option>
            @endforeach
        </select>
        
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-bold shadow-sm hover:bg-slate-900 transition">Filter</button>
        <a href="{{ route('activity.index') }}" class="px-4 py-2 text-slate-500 text-sm font-bold hover:text-slate-800">Reset</a>
    </form>
    
    <div class="flex gap-2">
        <a href="{{ route('activity.export', request()->all()) }}" class="px-4 py-2 bg-green-50 text-green-700 border border-green-200 rounded-lg text-sm font-bold hover:bg-green-100 transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Export CSV
        </a>
    </div>
</div>

<!-- Timeline -->
<div class="bg-white rounded-2xl shadow p-6 md:p-8">
    @if(empty($groupedActivities))
        <div class="p-12 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <h3 class="text-lg font-bold text-gray-700">No Activities Found</h3>
            <p class="text-gray-500 text-sm mt-1">Try adjusting your filters or wait for users to interact with the system.</p>
        </div>
    @else
        <div class="space-y-8 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-200 before:to-transparent">
            
            @foreach($groupedActivities as $groupName => $activities)
                <div class="relative">
                    <div class="md:mx-auto text-center w-32 bg-slate-100 rounded-full px-3 py-1 text-xs font-bold text-slate-500 border border-slate-200 shadow-sm relative z-10 mx-0 mb-6 flex justify-center">
                        {{ $groupName }}
                    </div>
                    
                    <div class="space-y-6">
                        @foreach($activities as $log)
                        @php
                            $actionLower = strtolower($log['Action'] ?? '');
                            if (str_contains($actionLower, 'create') || str_contains($actionLower, 'add') || str_contains($actionLower, 'publish')) {
                                $color = 'green';
                                $icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>';
                            } elseif (str_contains($actionLower, 'delete') || str_contains($actionLower, 'remove') || str_contains($actionLower, 'error')) {
                                $color = 'red';
                                $icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>';
                            } else {
                                $color = 'blue';
                                $icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>';
                            }
                        @endphp
                        
                        <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                            <!-- Icon -->
                            <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-{{ $color }}-100 text-{{ $color }}-600 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10 ml-0 md:ml-auto md:mr-auto">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $icon !!}</svg>
                            </div>
                            <!-- Card -->
                            <div class="w-[calc(100%-3rem)] md:w-[calc(50%-2.5rem)] p-4 rounded-xl border border-gray-100 shadow-sm bg-white group-hover:shadow-md transition ml-4 md:ml-0">
                                <div class="flex justify-between items-start mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-800">{{ $log['User_ID'] ?? 'System' }}</span>
                                        <span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-bold uppercase">{{ $log['Role'] ?? '' }}</span>
                                    </div>
                                    <span class="text-xs text-gray-400 font-bold">{{ substr($log['Timestamp'] ?? '', 11, 5) }}</span>
                                </div>
                                <h4 class="font-bold text-sm text-{{ $color }}-700">{{ $log['Action'] ?? 'Activity' }}</h4>
                                <p class="text-sm text-gray-600 mt-1">{{ $log['Description'] ?? '' }}</p>
                                <div class="mt-2 text-xs font-bold text-gray-400 flex gap-2">
                                    <span class="border border-gray-200 px-1.5 rounded">{{ $log['Module'] ?? 'Module' }}</span>
                                    @if(!empty($log['IP_Address']))
                                    <span title="IP Address" class="border border-gray-200 px-1.5 rounded bg-gray-50 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg> {{ $log['IP_Address'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
            
        </div>
    @endif
</div>
@endsection
BLADE;
file_put_contents("$baseDir/resources/views/activity/index.blade.php", $activityView);

echo "Activity Center Controller and views created.\\n";
