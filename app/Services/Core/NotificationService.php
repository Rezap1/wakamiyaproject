<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use App\Support\ActorIdentity;
use App\Exceptions\FinancialIntegrityException;

class NotificationService
{
    private const CACHE_TTL_SECONDS = 30;
    private const CACHE_VERSION_KEY = 'notification_user_cache_version';

    protected $repo;

    public function __construct(NotificationRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getAll() { return $this->repo->getAll(); }

    public function getById($id) { return $this->repo->getById($id); }

    /**
     * Deterministic, same-day reminder lookup used by the invoice scheduler.
     * Prefer a fresh repository read so reruns cannot be fooled by stale cache.
     */
    public function hasReminder(string $invoiceId, int $daysToDue, string $date): bool
    {
        if ($invoiceId === '') {
            return false;
        }
        if (method_exists($this->repo, 'fetchHeadersFresh')) {
            $headers = $this->repo->fetchHeadersFresh();
            $missing = array_values(array_diff(['Reference_Type', 'Reference_ID'], $headers));
            if ($missing !== []) {
                throw new FinancialIntegrityException(
                    'Notification schema tidak mendukung durable reminder identity: ' . implode(', ', $missing)
                );
            }
        }
        $rows = method_exists($this->repo, 'getAllFresh')
            ? $this->repo->getAllFresh()
            : $this->repo->getAll();
        // Do not claim durable deduplication when the production schema
        // cannot expose the identity columns required for it.
        if (collect($rows)->isNotEmpty()
            && !collect($rows)->contains(fn ($row) => array_key_exists('Reference_Type', (array) $row)
                || array_key_exists('Reference_ID', (array) $row))) {
            throw new FinancialIntegrityException('Notification schema tidak mendukung durable reminder identity.');
        }
        $title = "Reminder Tagihan (H-{$daysToDue})";
        return collect($rows)->contains(function ($row) use ($invoiceId, $title, $date) {
            if (strcasecmp((string) ($row['Reference_Type'] ?? ''), 'Invoice') !== 0
                || trim((string) ($row['Reference_ID'] ?? '')) !== $invoiceId) {
                return false;
            }
            if (($row['Title'] ?? '') !== $title) {
                return false;
            }
            $created = (string) ($row['Created_At'] ?? '');
            return $created !== '' && str_starts_with($created, $date);
        });
    }

    public function CreateNotification(array $data)
    {
        // The production sheet calls this column Link; older callers used
        // Action_URL. Preserve the URL in the persisted equivalent instead
        // of silently dropping it.
        if (!array_key_exists('Link', $data) && array_key_exists('Action_URL', $data)) {
            $data['Link'] = $data['Action_URL'];
        }
        $data['Created_By'] = ActorIdentity::resolve($data['Created_By'] ?? null);
        $data['Notification_ID'] = $data['Notification_ID'] ?? uniqid('NOTIF_');
        $data['Status'] = $data['Status'] ?? 'Pending';
        $data['Is_Read'] = $data['Is_Read'] ?? 'FALSE';
        $data['Created_At'] = $data['Created_At'] ?? now()->toDateTimeString();

        $res = $this->repo->create($data);
        $this->repo->clearCache();
        $this->clearUserCache();

        // Hooks for external
        $this->HookEmail($data);
        $this->HookWhatsApp($data);
        $this->HookPush($data);

        return $res;
    }

    public function CreateBulkNotification(array $users, array $data)
    {
        foreach ($users as $user) {
            $payload = $data;
            $payload['User_ID'] = $user;
            $this->CreateNotification($payload);
        }
    }

    public function NotifyUser($userId, $title, $message, $type = 'Information', $priority = 'Normal', $url = null, $createdBy = null)
    {
        return $this->CreateNotification([
            'User_ID' => $userId,
            'Title' => $title,
            'Message' => $message,
            'Notification_Type' => $type,
            'Priority' => $priority,
            'Action_URL' => $url,
            'Created_By' => $createdBy,
        ]);
    }

    public function NotifyRole($role, $title, $message, $type = 'Information', $priority = 'Normal', $url = null, $createdBy = null)
    {
        return $this->CreateNotification([
            'Role' => $role,
            'Title' => $title,
            'Message' => $message,
            'Notification_Type' => $type,
            'Priority' => $priority,
            'Action_URL' => $url,
            'Created_By' => $createdBy,
        ]);
    }

    public function NotifyDepartment($department, $title, $message, $type = 'Information', $priority = 'Normal', $createdBy = null)
    {
        return $this->CreateNotification([
            'Department' => $department,
            'Title' => $title,
            'Message' => $message,
            'Notification_Type' => $type,
            'Priority' => $priority,
            'Created_By' => $createdBy,
        ]);
    }

    public function isForUser($notification, $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) return false;

        $targetUserId = strtolower(trim($notification['User_ID'] ?? ''));
        $targetEmail = strtolower(trim($notification['Recipient_Email'] ?? $notification['Email'] ?? ''));
        $targetRole = strtoupper(trim($notification['Role'] ?? $notification['Target_Role'] ?? ''));

        if ($targetUserId === 'all') {
            return true;
        }

        $userRoleName = strtoupper(trim((string) ($user->Role ?? session('role') ?? '')));
        if ($userRoleName === '') {
            $roleData = app(\App\Services\Core\RoleService::class)->getRoleById($user->Role_ID ?? '');
            $userRoleName = strtoupper(trim($roleData['Role_Name'] ?? ''));
        }

        $targetRoles = collect(preg_split('/[,;|]/', $targetRole))
            ->map(fn ($role) => strtoupper(trim($role)))
            ->filter()
            ->values()
            ->all();
        if (!empty($targetRoles) && in_array($userRoleName, $targetRoles, true)) {
            return true;
        }

        // Resolve Student_ID dynamically if not set
        if (!isset($user->resolved_student_id) && $user) {
            $students = \Illuminate\Support\Facades\Cache::remember('all_students_lookup_map', 300, function () {
                try { return collect(app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class)->fetchAll()); }
                catch (\Exception $e) { return collect(); }
            });
            $std = $students->firstWhere('User_ID', $user->User_ID);
            $user->resolved_student_id = $std['Student_ID'] ?? '';
        }

        // Resolve Employee_ID dynamically if not set
        if (!isset($user->resolved_employee_id) && $user) {
            $employees = \Illuminate\Support\Facades\Cache::remember('all_employees_lookup_map', 300, function () {
                try { return collect(app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class)->fetchAll()); }
                catch (\Exception $e) { return collect(); }
            });
            $emp = $employees->firstWhere('User_ID', $user->User_ID);
            $user->resolved_employee_id = $emp['Employee_ID'] ?? '';
        }

        $userIdentifiers = array_filter([
            strtolower(trim($user->User_ID ?? '')),
            strtolower(trim($user->email ?? $user->Email ?? '')),
            strtolower(trim($user->Employee_ID ?? $user->resolved_employee_id ?? '')),
            strtolower(trim($user->Student_ID ?? $user->resolved_student_id ?? '')),
        ]);

        if ((!empty($targetUserId) && in_array($targetUserId, $userIdentifiers, true))
            || (!empty($targetEmail) && in_array($targetEmail, $userIdentifiers, true))) {
            return true;
        }

        return false;
    }

    public function visibleToCurrentUser($id): ?array
    {
        $notif = $this->getById($id);
        if (!$notif) {
            return null;
        }

        if (!$this->isForUser($notif, auth()->user())) {
            return null;
        }

        return $notif;
    }

    public function MarkAsRead($id)
    {
        $notif = $this->visibleToCurrentUser($id);
        if($notif) {
            $this->repo->update($id, [
                'Is_Read' => 'TRUE',
                'Read_At' => now()->toDateTimeString(),
                'Status' => 'Read'
            ]);
            $this->repo->clearCache();
            $this->clearUserCache();
            return true;
        }

        return false;
    }

    public function MarkAllRead($userId = null)
    {
        $user = auth()->user();
        $notifications = $this->getAll()->filter(function($n) use ($user) {
            return $this->isForUser($n, $user) && strtoupper(trim($n['Is_Read'] ?? '')) !== 'TRUE';
        });

        foreach ($notifications as $notif) {
            $this->repo->update($notif['Notification_ID'], [
                'Is_Read' => 'TRUE',
                'Read_At' => now()->toDateTimeString(),
                'Status' => 'Read'
            ]);
        }
        $this->repo->clearCache();
        $this->clearUserCache();
    }

    public function ArchiveNotification($id)
    {
        $notif = $this->visibleToCurrentUser($id);
        if($notif) {
            $this->repo->update($id, ['Status' => 'Archived']);
            $this->repo->clearCache();
            $this->clearUserCache();
            return true;
        }

        return false;
    }

    public function DeleteNotification($id)
    {
        return $this->ArchiveNotification($id);
    }

    public function UnreadCount($userId = null, $role = null)
    {
        $user = auth()->user();
        $cacheKey = $this->userNotificationCacheKey('unread', $user, $userId, $role);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($user) {
            return $this->getAll()
                ->filter(function($n) use ($user) {
                    return $this->isForUser($n, $user) &&
                           strtoupper(trim($n['Is_Read'] ?? '')) !== 'TRUE' &&
                           strtolower(trim($n['Status'] ?? '')) !== 'archived';
                })->count();
        });
    }

    public function RecentNotification($userId = null, $role = null, $limit = 10)
    {
        $user = auth()->user();
        $cacheKey = $this->userNotificationCacheKey('recent', $user, $userId, $role, ['limit' => $limit]);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($user, $limit) {
            return $this->getAll()
                ->filter(function($n) use ($user) {
                    return $this->isForUser($n, $user) &&
                           strtolower(trim($n['Status'] ?? '')) !== 'archived';
                })->sortByDesc(function($n) {
                    try {
                        return \Carbon\Carbon::parse($n['Created_At'] ?? null)->timestamp;
                    } catch (\Exception $e) {
                        return 0;
                    }
                })->take($limit)->values();
        });
    }

    public function CriticalNotification($userId = null, $role = null)
    {
        $user = auth()->user();
        return $this->getAll()
            ->filter(function($n) use ($user) {
                return $this->isForUser($n, $user) &&
                       strcasecmp($n['Priority'] ?? '', 'Critical') === 0 &&
                       strtoupper(trim($n['Is_Read'] ?? '')) !== 'TRUE';
            })->first();
    }

    public function GenerateSystemNotification($message)
    {
        $this->CreateNotification([
            'Role' => 'ADMINISTRATOR',
            'Title' => 'System Alert',
            'Message' => $message,
            'Notification_Type' => 'Information',
            'Priority' => 'High',
            'Created_By' => 'SYSTEM',
        ]);
    }

    private function clearUserCache($userId = null, $role = null)
    {
        Cache::forever(self::CACHE_VERSION_KEY, $this->notificationCacheVersion() + 1);
    }

    private function userNotificationCacheKey(string $type, $user, $userId = null, $role = null, array $extra = []): string
    {
        $userKey = $userId ?: ($user ? ($user->User_ID ?? $user->email ?? $user->Email ?? 'anonymous') : 'anonymous');
        $roleKey = $role ?: ($user->Role ?? session('role') ?? 'any');
        $version = $this->notificationCacheVersion();

        return 'notification_' . $type . '_' . md5(json_encode([
            'version' => $version,
            'user' => $userKey,
            'role' => $roleKey,
            'extra' => $extra,
        ]));
    }

    private function notificationCacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    // External Hooks Preparation
    private function HookEmail($data) { return true; }
    private function HookWhatsApp($data) { return true; }
    private function HookPush($data) { return true; }
    private function HookTelegram($data) { return true; }
    private function HookFirebase($data) { return true; }
}
