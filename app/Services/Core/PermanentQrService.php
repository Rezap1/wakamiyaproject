<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PermanentQrService
{
    protected $qrRepository;
    protected $activityLog;

    public function __construct(
        PermanentQrRepositoryInterface $qrRepository,
        ActivityLogService $activityLog
    ) {
        $this->qrRepository = $qrRepository;
        $this->activityLog = $activityLog;
    }

    public function getAllQrCodes()
    {
        return collect($this->qrRepository->fetchAll())->sortByDesc('CREATED_AT')->values()->all();
    }

    public function getQrById($id)
    {
        $qr = $this->qrRepository->findById($id);
        if (!$qr) {
            $qr = $this->qrRepository->findByIdentifier($id);
        }
        return $qr;
    }

    public function getQrByIdentifier($identifier)
    {
        return $this->qrRepository->findByIdentifier($identifier);
    }

    public function createQr(array $data)
    {
        $actorUserId = $this->authenticatedActorId();
        $newId = $this->qrRepository->generateNewId('QR', 5);
        $type = strtoupper($data['QR_TYPE']);
        $activeFrom = $this->normalizeDateTime($data['ACTIVE_FROM'] ?? null);
        $activeUntil = $this->normalizeDateTime($data['ACTIVE_UNTIL'] ?? null);

        $this->assertValidSchedule($activeFrom, $activeUntil);
        
        // Generate unpredictable identifier
        $randomPart = strtoupper(Str::random(8));
        $prefix = $type === 'STUDENT' ? 'STU' : 'EMP';
        $identifier = "WMS-ATT-{$prefix}-{$randomPart}";
        
        // Ensure uniqueness
        while ($this->getQrByIdentifier($identifier)) {
            $randomPart = strtoupper(Str::random(8));
            $identifier = "WMS-ATT-{$prefix}-{$randomPart}";
        }

        $mappedData = [
            'QR_ID' => $newId,
            'QR_TYPE' => $type,
            'IDENTIFIER' => $identifier,
            'LABEL' => $data['LABEL'],
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM' => $activeFrom,
            'ACTIVE_UNTIL' => $activeUntil,
            'CREATED_AT' => now()->toDateTimeString(),
            'CREATED_BY' => $actorUserId,
            'UPDATED_AT' => now()->toDateTimeString(),
            'UPDATED_BY' => $actorUserId,
            'DEACTIVATED_AT' => ''
        ];
        $result = $this->qrRepository->create($mappedData);
        if (!$result) {
            throw new \Exception("Gagal menyimpan data ke Google Sheets. Pastikan Worksheet MASTER_PERMANENT_QR tersedia.");
        }
        
        $this->qrRepository->clearCache();
        \Illuminate\Support\Facades\Cache::forget('permanent_qr_sheet_all');
        \Illuminate\Support\Facades\Cache::forget('hr_dashboard');
        
        $this->activityLog->log(
            'ATTENDANCE_QR',
            'CREATE',
            "Membuat QR Presensi Permanen {$type}: {$identifier}",
            null,
            $mappedData
        );

        return $mappedData;
    }

    public function updateAvailability(string $id, array $data): array
    {
        $actorUserId = $this->authenticatedActorId();
        $qr = $this->getQrById($id);
        if (!$qr) {
            throw new \Exception("QR Presensi tidak ditemukan.");
        }

        $activeFrom = $this->normalizeDateTime($data['ACTIVE_FROM'] ?? null);
        $activeUntil = $this->normalizeDateTime($data['ACTIVE_UNTIL'] ?? null);
        $this->assertValidSchedule($activeFrom, $activeUntil);

        $status = strtoupper(trim((string) ($data['STATUS'] ?? ($qr['STATUS'] ?? 'ACTIVE'))));
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new \Exception("Status QR Presensi tidak valid.");
        }

        $update = [
            'STATUS' => $status,
            'ACTIVE_FROM' => $activeFrom,
            'ACTIVE_UNTIL' => $activeUntil,
            'UPDATED_AT' => now()->toDateTimeString(),
            'UPDATED_BY' => $actorUserId,
            'DEACTIVATED_AT' => $status === 'INACTIVE'
                ? (($qr['DEACTIVATED_AT'] ?? '') ?: now()->toDateTimeString())
                : '',
        ];

        $targetId = $qr['QR_ID'] ?? $id;
        $result = $this->qrRepository->update($targetId, $update);
        if (!$result) {
            throw new \RuntimeException('Gagal memperbarui status atau jadwal QR Presensi pada penyimpanan.');
        }

        $this->qrRepository->clearCache();
        \Illuminate\Support\Facades\Cache::forget('permanent_qr_sheet_all');
        \Illuminate\Support\Facades\Cache::forget('qr_sessions_list');
        if (!empty($qr['IDENTIFIER'])) {
            \Illuminate\Support\Facades\Cache::forget("qr_code_{$qr['IDENTIFIER']}");
            \Illuminate\Support\Facades\Cache::forget("qr_session_{$qr['IDENTIFIER']}");
        }
        if (!empty($qr['QR_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("qr_session_{$qr['QR_ID']}");
        }
        \Illuminate\Support\Facades\Cache::forget('hr_dashboard');
        \Illuminate\Support\Facades\Cache::forget('academic_dashboard');
        \Illuminate\Support\Facades\Cache::forget('dashboard_admin');

        $this->activityLog->log(
            'ATTENDANCE_QR',
            'UPDATE_AVAILABILITY',
            "Mengubah jadwal aktif QR Presensi: " . ($qr['IDENTIFIER'] ?? $id),
            ['QR_ID' => $id, 'IDENTIFIER' => $qr['IDENTIFIER'] ?? ''],
            $update
        );

        return array_merge($qr, $update);
    }

    public function deactivateQr($id)
    {
        $actorUserId = $this->authenticatedActorId();
        $qr = $this->getQrById($id);
        if (!$qr) {
            throw new \Exception("QR Presensi tidak ditemukan.");
        }

        $targetId = $qr['QR_ID'] ?? $id;
        $res = $this->qrRepository->deactivate($targetId, $actorUserId);
        if (!$res && !empty($qr['IDENTIFIER'])) {
            $res = $this->qrRepository->deactivate($qr['IDENTIFIER'], $actorUserId);
        }
        if (!$res) {
            throw new \RuntimeException('Gagal menonaktifkan QR Presensi pada penyimpanan.');
        }

        $this->qrRepository->clearCache();
        \Illuminate\Support\Facades\Cache::forget('permanent_qr_sheet_all');
        \Illuminate\Support\Facades\Cache::forget('qr_sessions_list');
        if (!empty($qr['IDENTIFIER'])) {
            \Illuminate\Support\Facades\Cache::forget("qr_code_{$qr['IDENTIFIER']}");
            \Illuminate\Support\Facades\Cache::forget("qr_session_{$qr['IDENTIFIER']}");
        }
        if (!empty($qr['QR_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("qr_session_{$qr['QR_ID']}");
        }
        \Illuminate\Support\Facades\Cache::forget('hr_dashboard');
        \Illuminate\Support\Facades\Cache::forget('academic_dashboard');
        \Illuminate\Support\Facades\Cache::forget('dashboard_admin');

        $this->activityLog->log(
            'ATTENDANCE_QR',
            'DEACTIVATE',
            "Menonaktifkan QR Presensi Permanen: " . ($qr['IDENTIFIER'] ?? $id),
            ['QR_ID' => $id, 'IDENTIFIER' => $qr['IDENTIFIER'] ?? '']
        );

        return true;
    }

    public function deleteQr($id)
    {
        $this->authenticatedActorId();
        $qr = $this->getQrById($id);
        if (!$qr) {
            throw new \Exception("QR Presensi tidak ditemukan.");
        }

        $targetId = $qr['QR_ID'] ?? $id;
        $res = $this->qrRepository->delete($targetId);
        if (!$res && !empty($qr['IDENTIFIER'])) {
            $res = $this->qrRepository->delete($qr['IDENTIFIER']);
        }
        if (!$res) {
            throw new \RuntimeException('Gagal menghapus QR Presensi pada penyimpanan.');
        }

        $this->qrRepository->clearCache();
        \Illuminate\Support\Facades\Cache::forget('permanent_qr_sheet_all');
        \Illuminate\Support\Facades\Cache::forget('qr_sessions_list');
        if (!empty($qr['IDENTIFIER'])) {
            \Illuminate\Support\Facades\Cache::forget("qr_code_{$qr['IDENTIFIER']}");
            \Illuminate\Support\Facades\Cache::forget("qr_session_{$qr['IDENTIFIER']}");
        }
        if (!empty($qr['QR_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("qr_session_{$qr['QR_ID']}");
        }
        \Illuminate\Support\Facades\Cache::forget('hr_dashboard');
        \Illuminate\Support\Facades\Cache::forget('academic_dashboard');
        \Illuminate\Support\Facades\Cache::forget('dashboard_admin');

        $this->activityLog->log(
            'ATTENDANCE_QR',
            'DELETE',
            "Menghapus QR Presensi Permanen secara permanen: " . ($qr['IDENTIFIER'] ?? $id),
            ['QR_ID' => $id, 'IDENTIFIER' => $qr['IDENTIFIER'] ?? '']
        );

        return true;
    }

    public function getCanonicalQrUrl(array $qr): string
    {
        // APP_URL is the deployment source of truth. In local QA this must
        // remain the configured localhost URL; production supplies its own
        // domain through the production environment.
        $baseUrl = rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/');
        $type = strtolower($qr['QR_TYPE']);
        $identifier = $qr['IDENTIFIER'];
        
        return "{$baseUrl}/attendance/scan/{$type}/{$identifier}";
    }

    public function getAvailabilityStatus(array $qr): array
    {
        $status = strtoupper(trim((string) ($qr['STATUS'] ?? 'INACTIVE')));
        if ($status !== 'ACTIVE') {
            return ['usable' => false, 'state' => 'INACTIVE', 'message' => 'QR Presensi ini sedang nonaktif.'];
        }

        $now = now();
        $activeFrom = $this->parseDateTime($qr['ACTIVE_FROM'] ?? null);
        $activeUntil = $this->parseDateTime($qr['ACTIVE_UNTIL'] ?? null);

        if ($activeFrom && $now->lt($activeFrom)) {
            return [
                'usable' => false,
                'state' => 'SCHEDULED',
                'message' => 'QR Presensi belum aktif. Mulai aktif pada ' . $activeFrom->format('d M Y H:i') . ' WIB.',
            ];
        }

        if ($activeUntil && $now->gt($activeUntil)) {
            return [
                'usable' => false,
                'state' => 'EXPIRED',
                'message' => 'QR Presensi sudah melewati jadwal aktif pada ' . $activeUntil->format('d M Y H:i') . ' WIB.',
            ];
        }

        return ['usable' => true, 'state' => 'ACTIVE', 'message' => 'QR Presensi aktif.'];
    }

    public function isQrCurrentlyUsable(array $qr): bool
    {
        return $this->getAvailabilityStatus($qr)['usable'] === true;
    }

    private function normalizeDateTime($value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function parseDateTime($value): ?Carbon
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function assertValidSchedule(string $activeFrom, string $activeUntil): void
    {
        if ($activeFrom === '' || $activeUntil === '') {
            return;
        }

        if (Carbon::parse($activeUntil)->lte(Carbon::parse($activeFrom))) {
            throw new \Exception("Jadwal nonaktif QR harus setelah jadwal aktif.");
        }
    }

    private function authenticatedActorId(): string
    {
        $user = auth()->user();
        $actorUserId = trim((string) ($user->User_ID ?? auth()->id() ?? ''));
        if ($actorUserId === '') {
            throw new \RuntimeException('Identitas pengguna tidak valid. Operasi QR Presensi ditolak.');
        }

        return $actorUserId;
    }
}
