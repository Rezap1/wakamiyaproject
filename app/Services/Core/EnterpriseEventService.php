<?php

namespace App\Services\Core;

use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use App\Services\Core\DashboardCacheService;
use Illuminate\Support\Facades\Log;

class EnterpriseEventService
{
    protected $activityLogService;
    protected $notificationService;
    protected $dashboardCacheService;

    public function __construct(
        ActivityLogService $activityLogService,
        NotificationService $notificationService,
        DashboardCacheService $dashboardCacheService
    ) {
        $this->activityLogService = $activityLogService;
        $this->notificationService = $notificationService;
        $this->dashboardCacheService = $dashboardCacheService;
    }

    /**
     * @param string $module
     * @param string $action
     * @param string $referenceType
     * @param string $referenceId
     * @param string|null $actorUserId
     * @param array $affectedRoles
     * @param array $affectedUsers
     * @param array $metadata
     * @return bool
     */
    public function dispatch(
        $module,
        $action,
        $referenceType,
        $referenceId,
        $actorUserId = null,
        array $affectedRoles = [],
        array $affectedUsers = [],
        array $metadata = []
    ) {
        // Fallback for user ID
        $actorUserId = $actorUserId ?? auth()->id() ?? 'SYSTEM';

        // 1. Record Activity Log
        $title = "{$referenceType} {$action}";
        $description = json_encode($metadata);
        
        try {
            $this->activityLogService->logAction($actorUserId, $action, $module, $description, null, null, null, null, $referenceType, $referenceId);
        } catch (\Exception $e) {
            Log::error("Failed to log activity: " . $e->getMessage());
        }

        // 2. Create Notification
        $message = "A {$referenceType} has been {$action} ({$referenceId}) by {$actorUserId}.";
        
        // Notify Roles
        foreach ($affectedRoles as $role) {
            try {
                $this->notificationService->NotifyRole($role, $title, $message, $module, 'Normal', '/');
            } catch (\Exception $e) {
                Log::error("Failed to notify role {$role}: " . $e->getMessage());
            }
        }

        // Notify Users
        foreach ($affectedUsers as $userId) {
            try {
                $this->notificationService->NotifyUser($userId, $title, $message, $module, 'Normal', '/');
            } catch (\Exception $e) {
                Log::error("Failed to notify user {$userId}: " . $e->getMessage());
            }
        }

        // 3. Invalidate Dashboard Cache
        $this->invalidateCachesForModule($module, $affectedRoles, $affectedUsers);

        // 4. Return Result
        return true;
    }

    protected function invalidateCachesForModule($module, $affectedRoles, $affectedUsers)
    {
        // By default, admin/director always clear to reflect total counts
        $this->dashboardCacheService->clearAdmin();
        $this->dashboardCacheService->clearDirector();

        $module = strtoupper($module);

        switch ($module) {
            case 'STUDENT':
            case 'PROGRAM':
            case 'BATCH':
            case 'CLASS':
            case 'ATTENDANCE':
            case 'SCORE':
            case 'ASSESSMENT':
            case 'ACADEMIC':
            case 'SCHEDULE':
                $this->dashboardCacheService->clearAcademic();
                break;
                
            case 'EMPLOYEE':
            case 'DEPARTMENT':
            case 'PAYROLL':
            case 'HR':
                $this->dashboardCacheService->clearHR();
                break;
                
            case 'FINANCE':
            case 'FINANCE_TRANSACTION':
            case 'INVOICE':
            case 'PAYMENT':
            case 'ACCOUNT':
                $this->dashboardCacheService->clearFinance();
                break;

            case 'COMPANY':
            case 'DOCUMENT':
            case 'MARKETING':
                $this->dashboardCacheService->clearMarketing();
                break;
        }

        // Clear specific users if known
        foreach ($affectedUsers as $userId) {
            if (str_starts_with($userId, 'TCH')) {
                $this->dashboardCacheService->clearTeacher($userId);
            } elseif (str_starts_with($userId, 'STU')) {
                $this->dashboardCacheService->clearStudent($userId);
            }
        }
    }
}
