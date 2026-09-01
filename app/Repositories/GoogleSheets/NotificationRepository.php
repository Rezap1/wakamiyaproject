<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;

class NotificationRepository extends BaseSheetRepository implements NotificationRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_NOTIFICATION';
        $this->cacheKey = 'notification_list';
        $this->primaryKey = 'Notification_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getAllFresh() { return $this->fetchAllFresh(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Archived']); }
}
