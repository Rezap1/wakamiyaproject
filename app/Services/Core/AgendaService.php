<?php

namespace App\Services\Core;

use App\Interfaces\AgendaRepositoryInterface;
use App\Support\ActorIdentity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AgendaService
{
    public const TYPES = [
        'ZOOM' => 'Zoom',
        'VISIT' => 'Kunjungan',
        'MEETING' => 'Rapat',
        'INTERVIEW' => 'Wawancara',
        'OTHER' => 'Lainnya',
    ];

    public const STATUSES = [
        'SCHEDULED' => 'Terjadwal',
        'IN_PROGRESS' => 'Berlangsung',
        'COMPLETED' => 'Selesai',
        'CANCELLED' => 'Dibatalkan',
    ];

    public const FOLLOW_UP_STATUSES = [
        'NOT_STARTED' => 'Belum Mulai',
        'IN_PROGRESS' => 'Proses',
        'COMPLETED' => 'Selesai',
    ];

    public function __construct(private AgendaRepositoryInterface $repository) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function find(string $id): ?array
    {
        return $this->repository->find($id);
    }

    public function pics(): Collection
    {
        return $this->repository->eligiblePics();
    }

    public function create(array $data): array
    {
        $this->assertEligiblePic((string) $data['PIC_User_ID']);
        $actor = ActorIdentity::required();
        $now = now()->toDateTimeString();

        return $this->repository->create(array_merge($this->normalize($data), [
            'Agenda_ID' => $this->repository->generateNewId(),
            'Created_By' => $actor,
            'Created_At' => $now,
            'Updated_By' => $actor,
            'Updated_At' => $now,
            'Deleted_At' => null,
        ]));
    }

    public function update(string $id, array $data): bool
    {
        if ($this->find($id) === null) {
            return false;
        }
        $this->assertEligiblePic((string) $data['PIC_User_ID']);

        return $this->repository->update($id, array_merge($this->normalize($data), [
            'Updated_By' => ActorIdentity::required(),
            'Updated_At' => now()->toDateTimeString(),
        ]));
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id, ActorIdentity::required());
    }

    private function normalize(array $data): array
    {
        $nullable = ['Activity_Result', 'Follow_Up', 'Follow_Up_Deadline', 'Document_Notes'];
        foreach ($nullable as $field) {
            $data[$field] = ($value = trim((string) ($data[$field] ?? ''))) === '' ? null : $value;
        }

        return $data;
    }

    private function assertEligiblePic(string $userId): void
    {
        if (! $this->repository->isEligiblePic($userId)) {
            throw ValidationException::withMessages([
                'PIC_User_ID' => 'PIC harus merupakan pengguna WMS aktif non-siswa.',
            ]);
        }
    }
}
