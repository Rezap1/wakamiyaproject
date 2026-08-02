<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    protected $repo;

    public function __construct(NotificationRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getAll() { return $this->repo->getAll(); }
    
    public function getById($id) { return $this->repo->getById($id); }

    public function CreateNotification(array $data)
    {
        $data['Notification_ID'] = uniqid('NOTIF_');
        $data['Status'] = $data['Status'] ?? 'Pending';
        $data['Is_Read'] = 'FALSE';
        $data['Created_At'] = now()->toDateTimeString();
        
        $res = $this->repo->create($data);
        $this->repo->clearCache();
        $this->clearUserCache($data['User_ID'] ?? null, $data['Role'] ?? null);
        
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

    public function NotifyUser($userId, $title, $message, $type = 'Information', $priority = 'Normal', $url = null)
    {
        return $this->CreateNotification([
            'User_ID' => $userId,
            'Title' => $title,
            'Message' => $message,
            'Notification_Type' => $type,
            'Priority' => $priority,
            'Action_URL' => $url
        ]);
    }

    public function NotifyRole($role, $title, $message, $type = 'Information', $priority = 'Normal', $url = null)
    {
        return $this->CreateNotification([
            'Role' => $role,
            'Title' => $title,
            'Message' => $message,
            'Notification_Type' => $type,
            'Priority' => $priority,
            'Action_URL' => $url
        ]);
    }

    public function NotifyDepartment($department, $title, $message, $type = 'Information', $priority = 'Normal')
    {
        return $this->CreateNotification([
            'Department' => $department,
            'Title' => $title,
            'Message' => $message,
            'Notification_Type' => $type,
            'Priority' => $priority
        ]);
    }

    public function MarkAsRead($id)
    {
        $notif = $this->getById($id);
        if($notif) {
            $this->repo->update($id, [
                'Is_Read' => 'TRUE',
                'Read_At' => now()->toDateTimeString(),
                'Status' => 'Read'
            ]);
            $this->repo->clearCache();
            $this->clearUserCache($notif['User_ID'] ?? null, $notif['Role'] ?? null);
        }
    }

    public function MarkAllRead($userId)
    {
        $notifications = $this->getAll()->where('User_ID', $userId)->where('Is_Read', '!=', 'TRUE');
        foreach ($notifications as $notif) {
            $this->repo->update($notif['Notification_ID'], [
                'Is_Read' => 'TRUE',
                'Read_At' => now()->toDateTimeString(),
                'Status' => 'Read'
            ]);
        }
        $this->repo->clearCache();
        $this->clearUserCache($userId);
    }

    public function ArchiveNotification($id)
    {
        $notif = $this->getById($id);
        if($notif) {
            $this->repo->update($id, ['Status' => 'Archived']);
            $this->repo->clearCache();
            $this->clearUserCache($notif['User_ID'] ?? null, $notif['Role'] ?? null);
        }
    }

    public function DeleteNotification($id)
    {
        // Actually soft delete via archive in this setup
        $this->ArchiveNotification($id);
    }

    public function UnreadCount($userId, $role)
    {
        return Cache::remember("notification_unread_{$userId}", 60, function () use ($userId, $role) {
            return $this->getAll()
                ->filter(function($n) use ($userId, $role) {
                    return (($n['User_ID'] ?? '') == $userId || ($n['Role'] ?? '') == $role) && 
                           ($n['Is_Read'] ?? '') !== 'TRUE' && 
                           ($n['Status'] ?? '') !== 'Archived';
                })->count();
        });
    }

    public function RecentNotification($userId, $role, $limit = 10)
    {
        return Cache::remember("notification_user_{$userId}", 60, function () use ($userId, $role, $limit) {
            return $this->getAll()
                ->filter(function($n) use ($userId, $role) {
                    return (($n['User_ID'] ?? '') == $userId || ($n['Role'] ?? '') == $role) && 
                           ($n['Status'] ?? '') !== 'Archived';
                })->sortByDesc('Created_At')->take($limit);
        });
    }

    public function CriticalNotification($userId, $role)
    {
        return $this->getAll()
            ->filter(function($n) use ($userId, $role) {
                return (($n['User_ID'] ?? '') == $userId || ($n['Role'] ?? '') == $role) && 
                       ($n['Priority'] ?? '') === 'Critical' && 
                       ($n['Is_Read'] ?? '') !== 'TRUE';
            })->first();
    }

    public function GenerateSystemNotification($message)
    {
        $this->CreateNotification([
            'Role' => 'ADMINISTRATOR',
            'Title' => 'System Alert',
            'Message' => $message,
            'Notification_Type' => 'Information',
            'Priority' => 'High'
        ]);
    }

    private function clearUserCache($userId = null, $role = null)
    {
        if ($userId) {
            Cache::forget("notification_unread_{$userId}");
            Cache::forget("notification_user_{$userId}");
        }
    }

    // External Hooks Preparation
    private function HookEmail($data) { return true; }
    private function HookWhatsApp($data) { return true; }
    private function HookPush($data) { return true; }
    private function HookTelegram($data) { return true; }
    private function HookFirebase($data) { return true; }
}