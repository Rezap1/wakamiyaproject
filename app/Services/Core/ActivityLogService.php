<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;


class ActivityLogService
{
    protected $activityLogRepository;

    public function __construct(ActivityLogRepositoryInterface $activityLogRepository)
    {
        $this->activityLogRepository = $activityLogRepository;
    }

    public function getAllLogs()
    {
        return $this->activityLogRepository->fetchAll();
    }

    public function log(string $module, string $action, string $description, $oldValue = null, $newValue = null, string $ipAddress = null, string $userAgent = null)
    {
        return $this->logAction(
            auth()->user()->User_ID ?? 'SYSTEM',
            $action,
            $module,
            $description,
            $ipAddress,
            $oldValue,
            $newValue,
            $userAgent
        );
    }

    public function logAction(string $userId, string $action, string $module, string $description, string $ipAddress = null, $oldValue = null, $newValue = null, string $userAgent = null, string $referenceType = '', string $referenceId = '')
    {
        try {
            $newId = $this->activityLogRepository->generateNewId('LOG', 7);

            $data = [
                'Audit_ID' => $newId,
                'User_ID' => $userId,
                'Role' => '',
                'Department' => '',
                'Module' => $module,
                'Reference_Type' => $referenceType,
                'Reference_ID' => $referenceId,
                'Action' => $action,
                'Old_Value' => is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $oldValue,
                'New_Value' => empty($newValue) ? $description : (is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $newValue),
                'IPAddress' => $ipAddress ?? request()->ip(),
                'Device' => '',
                'Browser' => $userAgent ?? request()->userAgent(),
                'Operating_System' => '',
                'Location' => '',
                'Status' => 'SUCCESS',
                'Created_At' => now()->toDateTimeString(),
            ];
            
            $result = $this->activityLogRepository->create($data);
            
            // Dashboard cache invalidation is now handled centrally by DashboardCacheService 
            // via EnterpriseEventService. No direct Cache::forget here.
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to write Audit Log to Google Sheets: ' . $e->getMessage());
            return false;
        }
    }
}
