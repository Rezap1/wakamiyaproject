<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Services\Attendance\AttendanceWindowService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PermanentQrService
{
    public const TIMEZONE = 'Asia/Jakarta';

    protected $qrRepository;

    protected $activityLog;

    protected ?SystemSettingService $settingService;

    public function __construct(
        PermanentQrRepositoryInterface $qrRepository,
        ActivityLogService $activityLog,
        ?SystemSettingService $settingService = null
    ) {
        $this->qrRepository = $qrRepository;
        $this->activityLog = $activityLog;
        $this->settingService = $settingService;
    }

    public function getAllQrCodes()
    {
        return collect($this->qrRepository->fetchAll())->sortByDesc('CREATED_AT')->values()->all();
    }

    public function getQrById($id)
    {
        $qr = $this->qrRepository->findById($id);
        if (! $qr) {
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
        $attendanceWindow = $this->attendanceWindowFromInputOrDefault($data, $type);

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
            'ATTENDANCE_START_TIME' => $attendanceWindow['start'],
            'ATTENDANCE_END_TIME' => $attendanceWindow['end'],
            'CREATED_AT' => $this->serverNow()->toDateTimeString(),
            'CREATED_BY' => $actorUserId,
            'UPDATED_AT' => $this->serverNow()->toDateTimeString(),
            'UPDATED_BY' => $actorUserId,
            'DEACTIVATED_AT' => '',
        ];
        $result = $this->qrRepository->create($mappedData);
        if (! $result) {
            throw new \Exception('Gagal menyimpan data QR. Pastikan tabel MASTER_PERMANENT_QR tersedia.');
        }

        $this->qrRepository->clearCache();
        Cache::forget('permanent_qr_sheet_all');
        Cache::forget('hr_dashboard');

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
        if (! $qr) {
            throw new \Exception('QR Presensi tidak ditemukan.');
        }

        $activeFrom = $this->normalizeDateTime($data['ACTIVE_FROM'] ?? ($qr['ACTIVE_FROM'] ?? null));
        $activeUntil = $this->normalizeDateTime($data['ACTIVE_UNTIL'] ?? ($qr['ACTIVE_UNTIL'] ?? null));
        $this->assertValidSchedule($activeFrom, $activeUntil);
        $attendanceWindow = $this->attendanceWindowFromInputOrExisting($data, $qr);

        $status = strtoupper(trim((string) ($data['STATUS'] ?? ($qr['STATUS'] ?? 'ACTIVE'))));
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new \Exception('Status QR Presensi tidak valid.');
        }

        $update = [
            'STATUS' => $status,
            'ACTIVE_FROM' => $activeFrom,
            'ACTIVE_UNTIL' => $activeUntil,
            'ATTENDANCE_START_TIME' => $attendanceWindow['start'],
            'ATTENDANCE_END_TIME' => $attendanceWindow['end'],
            'UPDATED_AT' => $this->serverNow()->toDateTimeString(),
            'UPDATED_BY' => $actorUserId,
            'DEACTIVATED_AT' => $status === 'INACTIVE'
                ? (($qr['DEACTIVATED_AT'] ?? '') ?: $this->serverNow()->toDateTimeString())
                : '',
        ];

        $targetId = $qr['QR_ID'] ?? $id;
        $result = $this->qrRepository->update($targetId, $update);
        if (! $result) {
            throw new \RuntimeException('Gagal memperbarui status atau jadwal QR Presensi pada penyimpanan.');
        }

        $this->qrRepository->clearCache();
        Cache::forget('permanent_qr_sheet_all');
        Cache::forget('qr_sessions_list');
        if (! empty($qr['IDENTIFIER'])) {
            Cache::forget("qr_code_{$qr['IDENTIFIER']}");
            Cache::forget("qr_session_{$qr['IDENTIFIER']}");
        }
        if (! empty($qr['QR_ID'])) {
            Cache::forget("qr_session_{$qr['QR_ID']}");
        }
        Cache::forget('hr_dashboard');
        Cache::forget('academic_dashboard');
        Cache::forget('dashboard_admin');

        try {
            $this->activityLog->log(
                'ATTENDANCE_QR',
                'UPDATE_AVAILABILITY',
                'Mengubah jadwal aktif QR Presensi: '.($qr['IDENTIFIER'] ?? $id),
                ['QR_ID' => $id, 'IDENTIFIER' => $qr['IDENTIFIER'] ?? ''],
                $update
            );
        } catch (\Throwable $logFailure) {
            // The lifecycle update is already durable; audit failure must not
            // turn it into a false rejection or trigger an unsafe retry.
            report($logFailure);
        }

        return array_merge($qr, $update);
    }

    public function deactivateQr($id)
    {
        $actorUserId = $this->authenticatedActorId();
        $qr = $this->getQrById($id);
        if (! $qr) {
            throw new \Exception('QR Presensi tidak ditemukan.');
        }

        $targetId = $qr['QR_ID'] ?? $id;
        $res = $this->qrRepository->deactivate($targetId, $actorUserId);
        if (! $res && ! empty($qr['IDENTIFIER'])) {
            $res = $this->qrRepository->deactivate($qr['IDENTIFIER'], $actorUserId);
        }
        if (! $res) {
            throw new \RuntimeException('Gagal menonaktifkan QR Presensi pada penyimpanan.');
        }

        $this->qrRepository->clearCache();
        Cache::forget('permanent_qr_sheet_all');
        Cache::forget('qr_sessions_list');
        if (! empty($qr['IDENTIFIER'])) {
            Cache::forget("qr_code_{$qr['IDENTIFIER']}");
            Cache::forget("qr_session_{$qr['IDENTIFIER']}");
        }
        if (! empty($qr['QR_ID'])) {
            Cache::forget("qr_session_{$qr['QR_ID']}");
        }
        Cache::forget('hr_dashboard');
        Cache::forget('academic_dashboard');
        Cache::forget('dashboard_admin');

        $this->activityLog->log(
            'ATTENDANCE_QR',
            'DEACTIVATE',
            'Menonaktifkan QR Presensi Permanen: '.($qr['IDENTIFIER'] ?? $id),
            ['QR_ID' => $id, 'IDENTIFIER' => $qr['IDENTIFIER'] ?? '']
        );

        return true;
    }

    public function deleteQr($id)
    {
        $this->authenticatedActorId();
        $qr = $this->getQrById($id);
        if (! $qr) {
            throw new \Exception('QR Presensi tidak ditemukan.');
        }

        $targetId = $qr['QR_ID'] ?? $id;
        $res = $this->qrRepository->delete($targetId);
        if (! $res && ! empty($qr['IDENTIFIER'])) {
            $res = $this->qrRepository->delete($qr['IDENTIFIER']);
        }
        if (! $res) {
            throw new \RuntimeException('Gagal menghapus QR Presensi pada penyimpanan.');
        }

        $this->qrRepository->clearCache();
        Cache::forget('permanent_qr_sheet_all');
        Cache::forget('qr_sessions_list');
        if (! empty($qr['IDENTIFIER'])) {
            Cache::forget("qr_code_{$qr['IDENTIFIER']}");
            Cache::forget("qr_session_{$qr['IDENTIFIER']}");
        }
        if (! empty($qr['QR_ID'])) {
            Cache::forget("qr_session_{$qr['QR_ID']}");
        }
        Cache::forget('hr_dashboard');
        Cache::forget('academic_dashboard');
        Cache::forget('dashboard_admin');

        $this->activityLog->log(
            'ATTENDANCE_QR',
            'DELETE',
            'Menghapus QR Presensi Permanen secara permanen: '.($qr['IDENTIFIER'] ?? $id),
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

        $lifecycle = $this->getLifecycleConfiguration($qr);
        if (! $lifecycle['valid']) {
            return [
                'usable' => false,
                'state' => 'INVALID',
                'message' => $lifecycle['message'],
            ];
        }

        $activeFrom = $lifecycle['from'];
        $activeUntil = $lifecycle['until'];

        $now = $this->serverNow();

        if ($activeFrom && $now->lt($activeFrom)) {
            return [
                'usable' => false,
                'state' => 'UPCOMING',
                'message' => 'QR Presensi belum berlaku. Mulai berlaku pada '.$activeFrom->format('d M Y H:i').' WIB.',
            ];
        }

        if ($activeUntil && $now->gt($activeUntil)) {
            return [
                'usable' => false,
                'state' => 'EXPIRED',
                'message' => 'QR Presensi sudah berakhir pada '.$activeUntil->format('d M Y H:i').' WIB.',
            ];
        }

        return ['usable' => true, 'state' => 'ACTIVE', 'message' => 'QR Presensi aktif.'];
    }

    /**
     * Use one strict parser for management, print and scan flows so MySQL
     * DATETIME(6) values cannot be accepted by one surface and rejected by
     * another.
     *
     * @return array{valid: bool, from: ?CarbonImmutable, until: ?CarbonImmutable, message: string}
     */
    public function getLifecycleConfiguration(array $qr): array
    {
        try {
            $activeFrom = $this->parseRequiredDateTime($qr['ACTIVE_FROM'] ?? null);
            $activeUntil = $this->parseRequiredDateTime($qr['ACTIVE_UNTIL'] ?? null);
            $this->assertValidSchedule(
                $activeFrom->format('Y-m-d H:i:s.u'),
                $activeUntil->format('Y-m-d H:i:s.u')
            );

            return [
                'valid' => true,
                'from' => $activeFrom,
                'until' => $activeUntil,
                'message' => 'Masa berlaku QR Presensi valid.',
            ];
        } catch (InvalidArgumentException $exception) {
            return [
                'valid' => false,
                'from' => null,
                'until' => null,
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function isQrCurrentlyUsable(array $qr): bool
    {
        return $this->getAvailabilityStatus($qr)['usable'] === true;
    }

    /**
     * @return array{valid: bool, start: ?string, end: ?string, inherited: bool, message: string}
     */
    public function getAttendanceWindowConfiguration(array $qr): array
    {
        $rawStart = trim((string) ($qr['ATTENDANCE_START_TIME'] ?? ''));
        $rawEnd = trim((string) ($qr['ATTENDANCE_END_TIME'] ?? ''));
        $inherited = $rawStart === '' && $rawEnd === '';

        try {
            if ($inherited) {
                $defaults = $this->defaultAttendanceWindow((string) ($qr['QR_TYPE'] ?? ''));
                $start = $defaults['start'];
                $end = $defaults['end'];
            } elseif ($rawStart === '' || $rawEnd === '') {
                throw new InvalidArgumentException('Jam mulai dan selesai absensi QR harus diisi lengkap.');
            } else {
                $start = $this->normalizeStoredAttendanceTime($rawStart);
                $end = $this->normalizeStoredAttendanceTime($rawEnd);
            }

            $this->assertValidAttendanceWindow($start, $end);

            return [
                'valid' => true,
                'start' => $start,
                'end' => $end,
                'inherited' => $inherited,
                'message' => $inherited
                    ? 'Menggunakan fallback pengaturan global karena QR lama belum memiliki jam tersimpan.'
                    : 'Jam absensi tersimpan khusus untuk QR ini.',
            ];
        } catch (InvalidArgumentException $exception) {
            return [
                'valid' => false,
                'start' => null,
                'end' => null,
                'inherited' => $inherited,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function normalizeDateTime(mixed $value): string
    {
        return $this->parseRequiredDateTime($value)->format('Y-m-d H:i:s');
    }

    /** @return array{start: string, end: string} */
    private function attendanceWindowFromInputOrDefault(array $data, string $type): array
    {
        $start = trim((string) ($data['ATTENDANCE_START_TIME'] ?? ''));
        $end = trim((string) ($data['ATTENDANCE_END_TIME'] ?? ''));
        if ($start === '' && $end === '') {
            return $this->defaultAttendanceWindow($type);
        }

        $this->assertValidAttendanceWindow($start, $end);

        return compact('start', 'end');
    }

    /** @return array{start: string, end: string} */
    private function attendanceWindowFromInputOrExisting(array $data, array $qr): array
    {
        $hasInput = array_key_exists('ATTENDANCE_START_TIME', $data)
            || array_key_exists('ATTENDANCE_END_TIME', $data);
        if ($hasInput) {
            return $this->attendanceWindowFromInputOrDefault($data, (string) ($qr['QR_TYPE'] ?? ''));
        }

        $configuration = $this->getAttendanceWindowConfiguration($qr);
        if (! $configuration['valid']) {
            throw new InvalidArgumentException($configuration['message']);
        }

        return ['start' => $configuration['start'], 'end' => $configuration['end']];
    }

    /** @return array{start: string, end: string} */
    private function defaultAttendanceWindow(string $type): array
    {
        $settings = $this->settingService ?? app(SystemSettingService::class);
        if (strtoupper(trim($type)) === 'EMPLOYEE') {
            $start = trim((string) $settings->get('WORK_START_TIME', '07:00'));
            $end = trim((string) $settings->get('WORK_END_TIME', '18:00'));
        } else {
            $start = trim((string) $settings->get(
                AttendanceWindowService::START_SETTING_KEY,
                AttendanceWindowService::DEFAULT_START
            ));
            $end = trim((string) $settings->get(
                AttendanceWindowService::END_SETTING_KEY,
                AttendanceWindowService::DEFAULT_END
            ));
        }

        $this->assertValidAttendanceWindow($start, $end);

        return compact('start', 'end');
    }

    private function assertValidAttendanceWindow(string $start, string $end): void
    {
        $pattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/D';
        if (! preg_match($pattern, $start) || ! preg_match($pattern, $end)) {
            throw new InvalidArgumentException('Jam absensi QR harus menggunakan format HH:mm.');
        }
        if ($start >= $end) {
            throw new InvalidArgumentException('Jam selesai absensi QR harus setelah jam mulai pada hari yang sama.');
        }
    }

    private function normalizeStoredAttendanceTime(string $value): string
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $value)) {
            return $value;
        }
        if (preg_match('/^((?:[01]\d|2[0-3]):[0-5]\d):00$/D', $value, $matches)) {
            return $matches[1];
        }

        throw new InvalidArgumentException('Jam absensi QR pada penyimpanan tidak valid.');
    }

    private function parseRequiredDateTime(mixed $value): CarbonImmutable
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException('Tanggal dan waktu masa berlaku QR wajib diisi.');
        }

        $format = match (true) {
            preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) === 1 => 'Y-m-d H:i:s',
            preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/D', $value) === 1 => 'Y-m-d H:i:s.u',
            default => throw new InvalidArgumentException('Tanggal atau waktu masa berlaku QR tidak valid.'),
        };

        try {
            $dateTime = CarbonImmutable::createFromFormat('!'.$format, $value, self::TIMEZONE);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Tanggal atau waktu masa berlaku QR tidak valid.', 0, $exception);
        }

        if (! $dateTime || $dateTime->format($format) !== $value) {
            throw new InvalidArgumentException('Tanggal atau waktu masa berlaku QR tidak valid.');
        }

        return $dateTime;
    }

    private function assertValidSchedule(string $activeFrom, string $activeUntil): void
    {
        $from = $this->parseRequiredDateTime($activeFrom);
        $until = $this->parseRequiredDateTime($activeUntil);

        if ($until->lessThanOrEqualTo($from)) {
            throw new InvalidArgumentException('Waktu berakhir QR harus setelah waktu mulai berlaku.');
        }
    }

    private function serverNow(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
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
