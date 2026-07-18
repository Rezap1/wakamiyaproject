<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use Illuminate\Support\Facades\Log;


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

    public function logAction(string $userId, string $action, string $module, string $description, string $ipAddress = null, $oldValue = null, $newValue = null, string $userAgent = null)
    {
        try {
            $newId = $this->activityLogRepository->generateNewId('LOG', 7);

            $data = [
                'Log_ID' => $newId,
                'User_ID' => $userId,
                'Module' => $module,
                'Action' => $action,
                'Description' => $description,
                'Old_Value' => is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $oldValue,
                'New_Value' => is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $newValue,
                'IP_Address' => $ipAddress ?? request()->ip(),
                'User_Agent' => $userAgent ?? request()->userAgent(),
                'Created_At' => now()->toDateTimeString(),
            ];
            
            return $this->activityLogRepository->create($data);
        } catch (\Exception $e) {
            Log::error('Failed to write Audit Log to Google Sheets: ' . $e->getMessage());
            return false;
        }
    }
}
