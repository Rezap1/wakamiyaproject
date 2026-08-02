<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;

class AnnouncementService
{
    protected $repository;

    public function __construct(AnnouncementRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAll()
    {
        return $this->repository->fetchAll();
    }

    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    public function generateId()
    {
        return $this->repository->generateNewId('ANC', 6);
    }

    public function getActiveAnnouncements($role = null, $classId = null)
    {
        return $this->getAll()->filter(function ($item) use ($role, $classId) {
            // Check status and Is_Active
            if (($item['Is_Active'] ?? 'TRUE') !== 'TRUE') return false;
            if (($item['Status'] ?? 'PUBLISHED') !== 'PUBLISHED') return false;
            
            // Check expiry
            if (!empty($item['Expired_Date'])) {
                if (strtotime($item['Expired_Date']) < time()) return false;
            }
            if (!empty($item['Publish_Date'])) {
                if (strtotime($item['Publish_Date']) > time()) return false;
            }

            // Check target role
            if ($role && !empty($item['Target_Role']) && strtoupper($item['Target_Role']) !== 'ALL') {
                if (strtoupper($item['Target_Role']) !== strtoupper($role)) return false;
            }

            // Check target class
            if ($classId && !empty($item['Target_ID'])) {
                if ($item['Target_ID'] !== $classId) return false;
            }

            return true;
        });
    }

    public function create(array $data)
    {
        if (!isset($data['Announcement_ID'])) {
            $data['Announcement_ID'] = $this->generateId();
        }
        $data['Created_At'] = now()->toDateTimeString();
        
        $result = $this->repository->create($data);
        $this->repository->clearCache();
        return $result;
    }
    
    public function update($id, array $data)
    {
        $data['Updated_At'] = now()->toDateTimeString();
        $result = $this->repository->update($id, $data);
        $this->repository->clearCache();
        return $result;
    }
    
    public function delete($id)
    {
        $result = $this->repository->delete($id);
        $this->repository->clearCache();
        return $result;
    }
}
