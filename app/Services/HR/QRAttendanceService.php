<?php

namespace App\Services\HR;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class QRAttendanceService
{
    protected $attendanceRepository;
    protected $employeeRepository;
    protected $enterpriseEvent;

    public function __construct(
        AttendanceRepositoryInterface $attendanceRepository,
        EmployeeRepositoryInterface $employeeRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->attendanceRepository = $attendanceRepository;
        $this->employeeRepository = $employeeRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function createSession(array $data): array
    {
        $sessionId = 'QRS-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        $session = [
            'Session_ID' => $sessionId,
            'Title' => $data['Title'] ?? 'Presensi Kehadiran Pegawai',
            'Date' => now()->toDateString(),
            'Start_Time' => $data['Start_Time'] ?? '08:00',
            'End_Time' => $data['End_Time'] ?? '17:00',
            'Grace_Period' => (int) ($data['Grace_Period'] ?? 15),
            'Status' => 'ACTIVE',
            'Created_By' => auth()->user()->Name ?? auth()->user()->Email ?? 'HR Manager',
            'Created_At' => now()->toDateTimeString(),
            'Notes' => $data['Notes'] ?? ''
        ];

        Cache::forever("qr_session_{$sessionId}", $session);
        
        $sessionsList = Cache::get('qr_sessions_list', []);
        $sessionsList[$sessionId] = $session;
        Cache::forever('qr_sessions_list', $sessionsList);

        $this->enterpriseEvent->dispatch(
            'HR', 
            'CREATE', 
            'ATTENDANCE_SESSION', 
            $sessionId, 
            auth()->id() ?? 'SYSTEM', 
            ['HR', 'ADMINISTRATOR'], 
            [], 
            $session
        );

        return $session;
    }

    public function getSession(string $sessionId): ?array
    {
        $session = Cache::get("qr_session_{$sessionId}");
        if (!$session) {
            $sessionsList = Cache::get('qr_sessions_list', []);
            $session = $sessionsList[$sessionId] ?? null;
        }
        return $session;
    }

    public function generateDynamicToken(string $sessionId): array
    {
        $session = $this->getSession($sessionId);
        if (!$session || ($session['Status'] ?? '') !== 'ACTIVE') {
            throw new Exception("Sesi kehadiran QR #{$sessionId} telah ditutup atau tidak aktif.");
        }

        $expiresAt = now()->addSeconds(25)->timestamp;
        $nonce = Str::random(16);
        $signature = hash_hmac('sha256', "{$sessionId}|{$expiresAt}|{$nonce}", config('app.key', 'WakamiyaKey321'));

        $payload = [
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
            'sig' => $signature
        ];

        $tokenString = base64_encode(json_encode($payload));

        // Store nonce in cache store for single-use validation (valid 60 seconds)
        Cache::put("qr_nonce_{$nonce}", $sessionId, 60);

        return [
            'token' => $tokenString,
            'expires_in' => 25,
            'session' => $session
        ];
    }

    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    public function processScan(string $tokenString, ?string $deviceInfo = null, ?float $userLat = null, ?float $userLon = null): array
    {
        // 1. Decode Payload
        $decodedJson = base64_decode($tokenString, true);
        if (!$decodedJson) {
            throw new Exception("Payload QR Code tidak valid.");
        }

        $payload = json_decode($decodedJson, true);
        if (!is_array($payload) || empty($payload['session_id']) || empty($payload['expires_at']) || empty($payload['nonce']) || empty($payload['sig'])) {
            throw new Exception("Format data Token QR Code tidak valid.");
        }

        $qrType = $payload['qr_type'] ?? 'EMPLOYEE';
        $sessionId = $payload['session_id'];
        $expiresAt = (int) $payload['expires_at'];
        $nonce = $payload['nonce'];
        $sig = $payload['sig'];

        // Strict QR Type Check
        if (strtoupper($qrType) === 'STUDENT') {
            throw new Exception("Akses Ditolak: QR Code ini khusus untuk Presensi Siswa.");
        }

        // 2. Security Validation: Expiration TTL Check
        if ($expiresAt < now()->timestamp) {
            throw new Exception("Token QR Code telah kadaluarsa. Mohon pindai ulang Token QR terbaru di layar.");
        }

        // 3. Security Validation: Single-use Nonce Replay Check
        if (!Cache::has("qr_nonce_{$nonce}")) {
            throw new Exception("Token QR Code telah digunakan atau tidak valid. Silakan pindai QR terbaru.");
        }

        // 4. Security Validation: HMAC Signature Verification
        $expectedSig = hash_hmac('sha256', "{$sessionId}|{$expiresAt}|{$nonce}", config('app.key', 'WakamiyaKey321'));
        if (!hash_equals($expectedSig, $sig)) {
            $expectedSigWithType = hash_hmac('sha256', "{$qrType}|{$sessionId}|{$expiresAt}|{$nonce}", config('app.key', 'WakamiyaKey321'));
            if (!hash_equals($expectedSigWithType, $sig)) {
                throw new Exception("Tanda tangan digital QR Code tidak valid atau telah dimanipulasi.");
            }
        }

        // 5. Security Validation: Session Active Check
        $session = $this->getSession($sessionId);
        if (!$session || ($session['Status'] ?? '') !== 'ACTIVE') {
            throw new Exception("Sesi kehadiran QR #{$sessionId} telah ditutup atau tidak aktif.");
        }

        // 6. Security Validation: Server-side Authenticated Employee Resolution (NO CLIENT IDOR TRUST)
        $user = auth()->user();
        $roleName = strtoupper(trim($user->Role ?? ''));
        if (!$user || $roleName === 'STUDENT' || str_contains($roleName, 'STUDENT')) {
            throw new Exception("Akses Ditolak: Hanya Pegawai/Guru yang dapat melakukan presensi HR.");
        }

        // Geofencing Check if coordinates are provided
        $settingService = app(\App\Services\Core\SystemSettingService::class);
        $geofenceEnabled = strtoupper(trim((string) $settingService->get('LPK_GEOFENCE_ENABLED', 'TRUE'))) !== 'FALSE';
        if ($geofenceEnabled && $userLat !== null && $userLon !== null) {
            $lpkLat = (float) $settingService->get('LPK_LATITUDE', -6.812391);
            $lpkLon = (float) $settingService->get('LPK_LONGITUDE', 107.194458);
            $allowedRadius = (float) $settingService->get('LPK_ALLOWED_RADIUS_METERS', 20);
            $distance = $this->calculateDistance($userLat, $userLon, $lpkLat, $lpkLon);
            if ($distance > $allowedRadius) {
                throw new Exception("Anda berada di luar area LPK. Jarak Anda: {$distance} meter. Maksimal jarak yang diizinkan: 20 meter.");
            }
        }

        $allEmployees = collect($this->employeeRepository->fetchAll());
        $employee = $allEmployees->firstWhere('User_ID', $user->User_ID);
        if (!$employee || strtoupper(trim($employee['Is_Active'] ?? 'TRUE')) === 'FALSE') {
            throw new Exception("Akses Ditolak: Profil pegawai Anda tidak ditemukan atau sedang tidak aktif.");
        }

        $employeeId = $employee['Employee_ID'];

        // 7. Atomic Concurrency Lock & Duplicate Check
        $lockKey = "qr_scan_{$sessionId}_{$employeeId}";

        return Cache::lock($lockKey, 10)->block(3, function () use ($sessionId, $nonce, $session, $user, $employee, $employeeId, $deviceInfo) {
            // Check Duplicate Attendance for this Employee in this Session
            $allAttendances = collect($this->attendanceRepository->fetchAll());
            $existing = $allAttendances->first(function ($att) use ($sessionId, $employeeId) {
                return ($att['Employee_ID'] ?? '') === $employeeId && 
                       ($att['Session_ID'] ?? '') === $sessionId && 
                       strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
            });

            if ($existing) {
                throw new Exception("Presensi gagal: Anda ({$employee['Full_Name']}) telah melakukan presensi untuk sesi ini.");
            }

            // Consume Nonce immediately to prevent replay
            Cache::forget("qr_nonce_{$nonce}");

            // 8. Server-side Time Rules (Asia/Jakarta)
            $nowCarbon = now();
            $currentTimeStr = $nowCarbon->format('H:i:s');
            $startTimeStr = $session['Start_Time'] ?? '08:00';
            $gracePeriod = (int) ($session['Grace_Period'] ?? 15);
            
            $lateThreshold = Carbon::parse($startTimeStr)->addMinutes($gracePeriod);
            $isLate = $nowCarbon->gt($lateThreshold);
            $status = $isLate ? 'LATE' : 'PRESENT';
            $lateMinutes = $isLate ? max(1, $nowCarbon->diffInMinutes(Carbon::parse($startTimeStr))) : 0;

            // 9. Persistence
            $attendanceId = 'ATT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            $record = [
                'Attendance_ID' => $attendanceId,
                'Employee_ID' => $employeeId,
                'User_ID' => $user->User_ID,
                'Session_ID' => $sessionId,
                'Attendance_Date' => now()->toDateString(),
                'Check_In_Time' => $currentTimeStr,
                'Status' => $status,
                'Late_Minutes' => $lateMinutes,
                'Verification_Method' => 'EMPLOYEE_GEO_QR',
                'Device_Info' => $deviceInfo ?? request()->header('User-Agent', 'Mobile Scanner'),
                'Is_Active' => 'TRUE',
                'Created_At' => now()->toDateTimeString()
            ];

            $res = $this->attendanceRepository->create($record);
            $this->attendanceRepository->clearCache();

            Cache::forget("employee_attendance_{$employeeId}");
            Cache::forget('hr_dashboard');

            // 10. Unified Event Dispatch
            $this->enterpriseEvent->dispatch(
                'HR', 
                'CREATE', 
                'ATTENDANCE', 
                $attendanceId, 
                auth()->id() ?? 'SYSTEM', 
                ['HR', 'ADMINISTRATOR'], 
                [$employeeId], 
                [
                    'Employee_Name' => $employee['Full_Name'] ?? $employeeId,
                    'Status' => $status,
                    'Late_Minutes' => $lateMinutes,
                    'Check_In_Time' => $currentTimeStr
                ]
            );

            return [
                'attendance' => $record,
                'employee' => [
                    'id' => $employeeId,
                    'name' => $employee['Full_Name'] ?? $employeeId
                ],
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'check_in_time' => $currentTimeStr
            ];
        });
    }

    public function closeSession(string $sessionId): array
    {
        $session = $this->getSession($sessionId);
        if (!$session) {
            throw new Exception("Sesi kehadiran QR #{$sessionId} tidak ditemukan.");
        }

        $session['Status'] = 'CLOSED';
        $session['Closed_At'] = now()->toDateTimeString();

        Cache::forever("qr_session_{$sessionId}", $session);

        $sessionsList = Cache::get('qr_sessions_list', []);
        $sessionsList[$sessionId] = $session;
        Cache::forever('qr_sessions_list', $sessionsList);

        $this->enterpriseEvent->dispatch(
            'HR', 
            'UPDATE', 
            'ATTENDANCE_SESSION', 
            $sessionId, 
            auth()->id() ?? 'SYSTEM', 
            ['HR', 'ADMINISTRATOR'], 
            [], 
            ['Status' => 'CLOSED']
        );

        return $session;
    }

    public function getSessionSummary(string $sessionId): array
    {
        $session = $this->getSession($sessionId);
        $allAttendances = collect($this->attendanceRepository->fetchAll())
            ->where('Session_ID', $sessionId)
            ->where('Is_Active', '!=', 'FALSE');

        $employees = collect($this->employeeRepository->fetchAll())->keyBy('Employee_ID');

        $attended = $allAttendances->map(function ($att) use ($employees) {
            $empId = $att['Employee_ID'] ?? null;
            $att['Employee_Name'] = ($empId && isset($employees[$empId])) ? $employees[$empId]['Full_Name'] : ($empId ?? '-');
            return $att;
        })->values();

        return [
            'session' => $session,
            'total_scanned' => $attended->count(),
            'present_count' => $attended->where('Status', 'PRESENT')->count(),
            'late_count' => $attended->where('Status', 'LATE')->count(),
            'attendances' => $attended
        ];
    }
}
