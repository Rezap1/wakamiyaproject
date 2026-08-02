<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;

class NotificationGoogleSheetRepository extends BaseSheetRepository implements NotificationRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_NOTIFICATION';
        $this->cacheKey = 'notifications_sheet';
        $this->primaryKey = 'Notification_ID';
    }

    public function getByUserId($userId)
    {
        $all = $this->fetchAll();
        $result = [];
        foreach ($all as $n) {
            if (isset($n['User_ID']) && $n['User_ID'] == $userId) {
                $result[] = $n;
            }
        }
        return collect($result)->sortByDesc('Created_At')->values()->all();
    }

    public function create(array $data)
    {
        $id = $this->generateNewId('NOTIF-', 6); // Or some logical prefix
        if (empty($id) || $id === 'NOTIF-000000') {
            $id = 'NOTIF-' . time() . rand(100, 999);
        }

        $row = [
            'Notification_ID' => $id,
            'User_ID' => $data['User_ID'] ?? '',
            'Role' => $data['Role'] ?? '',
            'Module' => $data['Module'] ?? '',
            'Reference_ID' => $data['Reference_ID'] ?? '',
            'Category' => $data['Category'] ?? '',
            'Priority' => $data['Priority'] ?? 'Low',
            'Title' => $data['Title'] ?? '',
            'Message' => $data['Message'] ?? '',
            'Action_URL' => $data['Action_URL'] ?? '',
            'Icon' => $data['Icon'] ?? '',
            'Color' => $data['Color'] ?? '',
            'Is_Read' => 'FALSE',
            'Read_At' => '',
            'Is_Archived' => 'FALSE',
            'Archived_At' => '',
            'Created_At' => date('Y-m-d H:i:s'),
            'Created_By' => $data['Created_By'] ?? 'System',
            'Notes' => $data['Notes'] ?? ''
        ];
        
        $this->append($row);
        return $id;
    }

    public function updateNotification($id, array $data)
    {
        return $this->update($id, $data);
    }
}