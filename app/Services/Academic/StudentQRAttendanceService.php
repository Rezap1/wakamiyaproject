<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\SystemSettingService;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class StudentQRAttendanceService
{
    protected $attendanceRepository;
    protected $studentRepository;
    protected $settingService;
    protected $enterpriseEvent;

    public function __construct(
        AttendanceRepositoryInterface $attendanceRepository,
        StudentRepositoryInterface $studentRepository,
        SystemSettingService $settingService,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->attendanceRepository = $attendanceRepository;
        $this->studentRepository = $studentRepository;
        $this->settingService = $settingService;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    /**
     * Calculate Great-Circle distance using Haversine formula (in meters)
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Earth radius in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    /**
     * Create or retrieve active Student QR Session
     */
    public function getOrCreateActiveStudentSession(): array
    {
        $today = now()->toDateString();
        $sessionId = "STUDENT-QRS-{$today}";
        
        $session = Cache::get("student_qr_session_{$sessionId}");
        if (!$session) {
            $session = [
                'Session_ID' => $sessionId,
                'Title' => 'Presensi Kehadiran Siswa LPK',
                'Type' => 'STUDENT',
                'Date' => $today,
                'Start_Time' => '07:00',
                'End_Time' => '18:00',
                'Grace_Period' => 30,
                'Status' => 'ACTIVE',
                'Created_At' => now()->toDateTimeString()
            ];
            Cache::forever("student_qr_session_{$sessionId}", $session);
        }
        
        return $session;
    }

    /**
     * Generate dynamic QR token for Student QR
     */
    public function generateStudentDynamicToken(): array
    {
        $session = $this->getOrCreateActiveStudentSession();
        $sessionId = $session['Session_ID'];
        
        $ttlSeconds = (int) $this->settingService->get('QR_TOKEN_TTL_SECONDS', 25);
        $expiresAt = now()->addSeconds($ttlSeconds)->timestamp;
        $nonce = Str::random(16);
        $qrType = 'STUDENT';
        
        $signature = hash_hmac('sha256', "{$qrType}|{$sessionId}|{$expiresAt}|{$nonce}", config('app.key', 'WakamiyaKey321'));
        
        $payload = [
            'qr_type' => $qrType,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
            'sig' => $signature
        ];

        $tokenString = base64_encode(json_encode($payload));
        Cache::put("qr_student_nonce_{$nonce}", $sessionId, $ttlSeconds + 35);

        return [
            'token' => $tokenString,
            'expires_in' => $ttlSeconds,
            'session' => $session
        ];
    }

    /**
     * Process Scan for Student Attendance
     */
    public function processStudentScan(string $tokenString, float $userLat, float $userLon, ?string $deviceInfo = null): array
    {
        // 1. Decode Payload
        $decodedJson = base64_decode($tokenString, true);
        if (!$decodedJson) {
            throw new Exception("QR Code tidak valid atau sudah kedaluwarsa.");
        }

        $payload = json_decode($decodedJson, true);
        if (!is_array($payload) || empty($payload['session_id']) || empty($payload['expires_at']) || empty($payload['nonce']) || empty($payload['sig'])) {
            throw new Exception("Format data QR Code tidak valid.");
        }

        $qrType = $payload['qr_type'] ?? 'STUDENT';
        $sessionId = $payload['session_id'];
        $expiresAt = (int) $payload['expires_at'];
        $nonce = $payload['nonce'];
        $sig = $payload['sig'];

        // 2. Strict QR Type Check
        if (strtoupper($qrType) !== 'STUDENT') {
            throw new Exception("Akses Ditolak: QR Code ini khusus untuk Presensi Pegawai.");
        }

        // 3. Expiration Check
        if ($expiresAt < now()->timestamp) {
            throw new Exception("QR Code telah kedaluwarsa. Silakan pindai ulang QR Code terbaru pada layar.");
        }

        // 4. Single-use Nonce Replay Check
        if (!Cache::has("qr_student_nonce_{$nonce}")) {
            throw new Exception("QR Code telah digunakan atau kedaluwarsa. Silakan pindai QR terbaru.");
        }

        // 5. Cryptographic Signature Verification
        $expectedSig = hash_hmac('sha256', "{$qrType}|{$sessionId}|{$expiresAt}|{$nonce}", config('app.key', 'WakamiyaKey321'));
        if (!hash_equals($expectedSig, $sig)) {
            throw new Exception("Tanda tangan digital QR Code tidak valid atau telah dimanipulasi.");
        }

        // 6. Geofence Location Verification (Server-Side Haversine)
        $geofenceEnabled = strtoupper(trim((string) $this->settingService->get('LPK_GEOFENCE_ENABLED', 'TRUE'))) !== 'FALSE';
        $lpkLat = (float) $this->settingService->get('LPK_LATITUDE', -6.812391);
        $lpkLon = (float) $this->settingService->get('LPK_LONGITUDE', 107.194458);
        $allowedRadius = (float) $this->settingService->get('LPK_ALLOWED_RADIUS_METERS', 20);

        $distance = $this->calculateDistance($userLat, $userLon, $lpkLat, $lpkLon);

        if ($geofenceEnabled && $distance > $allowedRadius) {
            throw new Exception("Anda berada di luar area LPK. Jarak Anda: {$distance} meter. Maksimal jarak yang diizinkan: 20 meter.");
        }

        // 7. Server-Side Identity Resolution (NO CLIENT IDOR TRUST)
        $user = auth()->user();
        if (!$user) {
            throw new Exception("Sesi pengguna tidak valid. Silakan login kembali.");
        }

        $roleName = strtoupper(trim($user->Role ?? ''));
        if ($roleName !== 'STUDENT' && !str_contains($roleName, 'STUDENT')) {
            throw new Exception("Akses Ditolak: Fitur ini khusus untuk akun Siswa.");
        }

        $allStudents = collect($this->studentRepository->fetchAll());
        $student = $allStudents->firstWhere('User_ID', $user->User_ID);

        if (!$student || strtoupper(trim($student['Is_Active'] ?? 'TRUE')) === 'FALSE') {
            throw new Exception("Akun Anda tidak dapat melakukan absensi. Profil siswa tidak ditemukan atau tidak aktif.");
        }

        // 8. Strict Student Status Rules
        $enrollmentStatus = strtoupper(trim($student['Enrollment_Status'] ?? ''));
        $graduationStatus = strtoupper(trim($student['Graduation_Status'] ?? ''));
        $batchId = trim((string) ($student['Batch_ID'] ?? ''));

        if ($graduationStatus === 'LULUS' || str_contains($enrollmentStatus, 'ALUMNI')) {
            throw new Exception("Absensi Gagal: Siswa berstatus Lulus / Alumni tidak diperkenankan presensi harian.");
        }

        if (str_contains($enrollmentStatus, 'CUTI') || str_contains($enrollmentStatus, 'DROPOUT') || str_contains($enrollmentStatus, 'NON-AKTIF') || str_contains($enrollmentStatus, 'OUT')) {
            throw new Exception("Absensi Gagal: Status pendaftaran siswa saat ini ({$student['Enrollment_Status']}) tidak aktif.");
        }

        if (empty($batchId)) {
            throw new Exception("Absensi Gagal: Batch siswa belum dikonfigurasi pada sistem.");
        }

        $studentId = $student['Student_ID'];
        $classId = $student['Class_ID'] ?? '';

        // 9. Atomic Concurrency Locking & Duplicate Check
        $lockKey = "student_qr_scan_{$sessionId}_{$studentId}";

        return Cache::lock($lockKey, 10)->block(3, function () use ($sessionId, $nonce, $student, $studentId, $batchId, $classId, $user, $distance, $deviceInfo) {
            // Check Duplicate Attendance for this Student today
            $todayStr = now()->toDateString();
            $allAttendances = collect($this->attendanceRepository->fetchAll());
            $existing = $allAttendances->first(function ($att) use ($studentId, $todayStr) {
                return ($att['Student_ID'] ?? '') === $studentId && 
                       ($att['Attendance_Date'] ?? '') === $todayStr && 
                       strtoupper(trim($att['Is_Active'] ?? 'TRUE')) !== 'FALSE';
            });

            if ($existing) {
                throw new Exception("Anda sudah melakukan absensi hari ini (" . Carbon::parse($existing['Created_At'] ?? now())->format('H:i') . ").");
            }

            // Consume Nonce immediately to prevent replay
            Cache::forget("qr_student_nonce_{$nonce}");

            // Status Calculation (Late check after 08:15)
            $nowCarbon = now();
            $lateThreshold = Carbon::today()->setHour(8)->setMinute(15);
            $isLate = $nowCarbon->gt($lateThreshold);
            $status = $isLate ? 'LATE' : 'PRESENT';
            $lateMinutes = $isLate ? max(1, $nowCarbon->diffInMinutes(Carbon::today()->setHour(8)->setMinute(0))) : 0;

            // 10. Master Attendance Persistence
            $attendanceId = 'ATT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            $record = [
                'Attendance_ID' => $attendanceId,
                'User_ID' => $user->User_ID,
                'Student_ID' => $studentId,
                'Batch_ID' => $batchId,
                'Class_ID' => $classId,
                'Schedule_ID' => $classId,
                'Attendance_Date' => $todayStr,
                'Check_In_Time' => $nowCarbon->format('H:i:s'),
                'Status' => $status,
                'Late_Minutes' => $lateMinutes,
                'Verification_Method' => 'STUDENT_GEO_QR',
                'Device_Info' => ($deviceInfo ?? request()->header('User-Agent', 'Mobile Browser')) . " [Dist: {$distance}m]",
                'Is_Active' => 'TRUE',
                'Created_At' => $nowCarbon->toDateTimeString(),
                'Updated_At' => $nowCarbon->toDateTimeString(),
                'Created_By' => $user->User_ID
            ];

            $this->attendanceRepository->create($record);
            $this->attendanceRepository->clearCache();

            // Dispatch Enterprise Event
            $this->enterpriseEvent->dispatch(
                'ACADEMIC', 
                'CREATE', 
                'STUDENT_ATTENDANCE', 
                $attendanceId, 
                $user->User_ID, 
                ['STUDENT', 'TEACHER', 'ACADEMIC'], 
                [$studentId], 
                [
                    'Student_Name' => $student['Full_Name'] ?? $studentId,
                    'Batch_ID' => $batchId,
                    'Status' => $status,
                    'Distance_Meters' => $distance
                ]
            );

            return [
                'attendance_id' => $attendanceId,
                'student_name' => $student['Full_Name'] ?? $studentId,
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'check_in_time' => $nowCarbon->format('H:i:s'),
                'distance_meters' => $distance
            ];
        });
    }
}
