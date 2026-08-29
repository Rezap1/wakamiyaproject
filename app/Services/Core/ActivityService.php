<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class ActivityService
{
    protected $repo;
    
    protected $moduleWhitelist = [
        'HR' => ['EMPLOYEE', 'DEPARTMENT', 'POSITION', 'HR', 'PAYROLL', 'LEAVE', 'OVERTIME', 'ATTENDANCE'],
        'ACADEMIC' => ['PROGRAM', 'BATCH', 'CLASS', 'TEACHER', 'STUDENT', 'SUBJECT', 'SCHEDULE', 'ASSIGNMENT', 'SUBMISSION', 'ATTENDANCE', 'SCORE', 'ANNOUNCEMENT', 'ACADEMIC'],
        'MARKETING' => ['COMPANY', 'JOBORDER', 'APPLICATION', 'INTERVIEW', 'MARKETING'],
        'FINANCE' => ['FINANCE', 'FINANCE_TRANSACTION', 'PAYMENT', 'INVOICE', 'ACCOUNT'],
    ];

    public function __construct(ActivityLogRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getActivities($role, $userId, $filters = [])
    {
        $role = strtoupper(trim((string) $role));

        // 1. Generate Cache Key based on Role, User, and Filters
        $cacheKey = "wms_activity_{$role}_{$userId}_" . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, 60, function () use ($role, $userId, $filters) {
            
            // 2. Fetch base data (limit to 1000 for performance if possible, assuming fetchAll does it or we take it)
            $allLogs = collect($this->repo->fetchAll())->sortByDesc('Timestamp')->take(1000);
            
            // 3. RBAC Filtering
            $filteredLogs = $allLogs->filter(function($log) use ($role, $userId) {
                if (in_array($role, ['MASTER', 'ADMINISTRATOR', 'DIRECTOR'], true)) return true;
                if (in_array($role, ['TEACHER', 'STUDENT'])) return ($log['User_ID'] ?? '') === $userId;
                
                // Whitelist check
                $allowedModules = $this->moduleWhitelist[$role] ?? [];
                return in_array(strtoupper(trim((string) ($log['Module'] ?? ''))), $allowedModules, true);
            });
            
            // 4. Apply extra filters (from UI)
            if (!empty($filters['keyword'])) {
                $kw = strtolower($filters['keyword']);
                $filteredLogs = $filteredLogs->filter(function($log) use ($kw) {
                    return str_contains(strtolower($log['User_ID'] ?? ''), $kw) || 
                           str_contains(strtolower($log['Module'] ?? ''), $kw) || 
                           str_contains(strtolower($log['Action'] ?? ''), $kw) || 
                           str_contains(strtolower($log['Description'] ?? ''), $kw);
                });
            }
            if (!empty($filters['module'])) {
                $filteredLogs = $filteredLogs->where('Module', $filters['module']);
            }
            
            return $filteredLogs->values();
        });
    }
    
    public function calculateKPIs($activities)
    {
        $today = date('Y-m-d');
        $todayActivities = $activities->filter(function($a) use ($today) {
            return str_starts_with($a['Timestamp'] ?? '', $today);
        });
        
        $activeUsersToday = $todayActivities->pluck('User_ID')->unique()->count();
        $mostActiveModule = $activities->pluck('Module')->mode()[0] ?? 'N/A';
        $criticalActivitiesToday = $todayActivities->filter(function($a) {
            return in_array(strtolower($a['Action'] ?? ''), ['delete', 'destroy', 'remove', 'error']);
        })->count();
        
        return [
            'today_total' => $todayActivities->count(),
            'active_users_today' => $activeUsersToday,
            'most_active_module' => $mostActiveModule,
            'critical_today' => $criticalActivitiesToday,
        ];
    }
    
    public function groupActivities($activities)
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $last7Days = date('Y-m-d', strtotime('-7 days'));
        
        $grouped = [
            'Today' => [],
            'Yesterday' => [],
            'Last 7 Days' => [],
            'Older' => []
        ];
        
        foreach ($activities as $a) {
            $date = substr($a['Timestamp'] ?? '', 0, 10);
            if ($date === $today) {
                $grouped['Today'][] = $a;
            } elseif ($date === $yesterday) {
                $grouped['Yesterday'][] = $a;
            } elseif ($date >= $last7Days) {
                $grouped['Last 7 Days'][] = $a;
            } else {
                $grouped['Older'][] = $a;
            }
        }
        
        return array_filter($grouped, function($group) { return count($group) > 0; });
    }
}
