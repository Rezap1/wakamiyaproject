<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;

class ScheduleService
{
    protected $repository;

    public function __construct(ScheduleRepositoryInterface $repository)
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
        return $this->repository->generateNewId('SCH', 6);
    }

    public function validateSchedule(array $data, $ignoreId = null)
    {
        if (isset($data['Start_Time']) && isset($data['End_Time'])) {
            if (strtotime($data['Start_Time']) >= strtotime($data['End_Time'])) {
                throw new \Exception('Start Time must be less than End Time.');
            }
        }

        $all = $this->getAll()->filter(function ($item) use ($ignoreId) {
            return ($item['Is_Active'] ?? 'TRUE') === 'TRUE' && $item['Schedule_ID'] !== $ignoreId;
        });

        foreach ($all as $item) {
            if ($item['Academic_Year_ID'] === ($data['Academic_Year_ID'] ?? '') && $item['Day_Of_Week'] === ($data['Day_Of_Week'] ?? '')) {
                // Check time overlap
                $start1 = strtotime($data['Start_Time']);
                $end1 = strtotime($data['End_Time']);
                $start2 = strtotime($item['Start_Time']);
                $end2 = strtotime($item['End_Time']);

                if ($start1 < $end2 && $end1 > $start2) {
                    if ($item['Teacher_ID'] === ($data['Teacher_ID'] ?? '')) {
                        throw new \Exception('Jadwal pengajar bentrok dengan jadwal lain.');
                    }
                    if ($item['Class_ID'] === ($data['Class_ID'] ?? '')) {
                        throw new \Exception('Jadwal kelas bentrok dengan jadwal lain.');
                    }
                    if (!empty($data['Room']) && $item['Room'] === $data['Room']) {
                        throw new \Exception('Ruangan sedang digunakan pada waktu tersebut.');
                    }
                }
            }
        }
    }

    public function create(array $data)
    {
        $this->validateSchedule($data);
        
        if (!isset($data['Schedule_ID'])) {
            $data['Schedule_ID'] = $this->generateId();
        }
        $data['Created_At'] = now()->toDateTimeString();
        
        $result = $this->repository->create($data);
        $this->repository->clearCache();
        return $result;
    }
    
    public function update($id, array $data)
    {
        $this->validateSchedule($data, $id);
        
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
