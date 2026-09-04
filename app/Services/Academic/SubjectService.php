<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Support\Academic\AcademicSheetMapper;
use Exception;

class SubjectService
{
    protected $repository;
    protected $scheduleRepository;
    protected $enterpriseEvent;

    public function __construct(
        SubjectRepositoryInterface $repository,
        ScheduleRepositoryInterface $scheduleRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->repository = $repository;
        $this->scheduleRepository = $scheduleRepository;
        $this->enterpriseEvent = $enterpriseEvent;
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
        return $this->repository->generateNewId('SUB', 6);
    }

    public function validateSubject(array $data, $ignoreId = null)
    {
        $all = $this->getAll()->map(fn ($row) => AcademicSheetMapper::normalizeSubjectRow((array) $row));
        
        if (isset($data['Subject_Code'])) {
            $existingCode = $all->firstWhere('Subject_Code', $data['Subject_Code']);
            if ($existingCode && $existingCode['Subject_ID'] !== $ignoreId) {
                throw new \Exception('Kode materi sudah digunakan.');
            }
        }

        if (isset($data['Subject_Name']) && isset($data['Program_ID'])) {
            $existingName = $all->first(function ($item) use ($data, $ignoreId) {
                return $item['Subject_Name'] === $data['Subject_Name'] 
                    && $item['Program_ID'] === $data['Program_ID']
                    && $item['Subject_ID'] !== $ignoreId;
            });
            if ($existingName) {
                throw new \Exception('Nama materi sudah digunakan pada program ini.');
            }
        }
    }

    public function create(array $data)
    {
        unset($data['id']);
        $data = $this->normalizePayload($data);
        $this->validateSubject($data);
        
        if (!isset($data['Subject_ID'])) {
            $data['Subject_ID'] = $this->generateId();
        }
        $data['Is_Active'] = $data['Is_Active'] ?? 'TRUE';
        $data['Created_At'] = now()->toDateTimeString();
        
        $result = $this->repository->create($data);
        $this->repository->clearCache();
        $this->assertReadBackMatches($data['Subject_ID'], $data);

        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'CREATE',
            'SUBJECT',
            $data['Subject_ID'],
            \App\Support\ActorIdentity::required(),
            ['ACADEMIC'],
            [],
            $data
        );

        return $result;
    }
    
    public function update($id, array $data)
    {
        $existing = $this->getById($id);
        if (!$existing) {
            throw new \RuntimeException("Materi '{$id}' tidak ditemukan.");
        }

        unset($data['Subject_ID'], $data['id']);
        $data = $this->normalizePayload($data);
        $merged = array_merge(AcademicSheetMapper::normalizeSubjectRow((array) $existing), $data);
        $this->validateSubject($merged, $id);
        
        $data['Updated_At'] = now()->toDateTimeString();
        $result = $this->repository->update($id, $data);
        $this->repository->clearCache();
        $this->assertReadBackMatches($id, $data);

        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'UPDATE',
            'SUBJECT',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ACADEMIC'],
            [],
            $data
        );

        return $result;
    }
    
    public function delete($id)
    {
        $schedules = collect($this->scheduleRepository->fetchAll());
        $relatedSchedulesCount = $schedules->where('Subject_ID', $id)->filter(function($s) {
            return ($s['Is_Active'] ?? 'TRUE') !== 'FALSE';
        })->count();

        if ($relatedSchedulesCount > 0) {
            throw new Exception("Materi tidak dapat dihapus karena masih digunakan pada jadwal kelas.");
        }

        $result = $this->repository->delete($id);
        $this->repository->clearCache();

        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'DELETE',
            'SUBJECT',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ACADEMIC'],
            [],
            []
        );

        return $result;
    }

    private function normalizePayload(array $data): array
    {
        foreach (['Subject_Code', 'Subject_Name', 'Program_ID', 'Description', 'Is_Active', 'Notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim((string) $data[$field]);
            }
        }

        foreach (['Credit', 'Duration'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = trim((string) $data[$field]);
            }
        }

        return $data;
    }

    private function assertReadBackMatches(string $id, array $expected): void
    {
        $fresh = $this->repository->findById($id);
        if (!$fresh) {
            throw new \RuntimeException("Materi '{$id}' tidak dapat diverifikasi setelah disimpan.");
        }

        $fresh = AcademicSheetMapper::normalizeSubjectRow((array) $fresh);
        foreach (['Subject_Code', 'Subject_Name', 'Program_ID', 'Credit', 'Duration', 'Description', 'Is_Active'] as $field) {
            if (!array_key_exists($field, $expected)) {
                continue;
            }

            if ((string) ($fresh[$field] ?? '') !== (string) $expected[$field]) {
                throw new \RuntimeException("Materi '{$id}' gagal diverifikasi: {$field} tidak sesuai setelah disimpan.");
            }
        }
    }
}
