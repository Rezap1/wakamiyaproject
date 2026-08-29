<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;

class NotificationGoogleSheetRepository extends BaseSheetRepository implements NotificationRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_NOTIFICATION';
        $this->cacheKey = 'notification_list';
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

    public function getAll()
    {
        return $this->fetchAll();
    }

    public function getById($id)
    {
        return $this->fetchAll()->firstWhere($this->primaryKey, $id);
    }

    public function create(array $data)
    {
        $data['Notification_ID'] = $data['Notification_ID'] ?? $this->generateNewId('NOTIF-', 6);
        $data['Status'] = $data['Status'] ?? 'Pending';
        $data['Created_At'] = $data['Created_At'] ?? now()->toDateTimeString();

        return $this->append($data);
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }

    public function delete($id)
    {
        return $this->updateRow($id, ['Status' => 'Archived']);
    }

    public function updateNotification($id, array $data)
    {
        return $this->update($id, $data);
    }
}
