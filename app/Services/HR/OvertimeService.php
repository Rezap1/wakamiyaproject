<?php

namespace App\Services\HR;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\OvertimeRepositoryInterface;
use App\Services\Core\EmployeeService;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class OvertimeService
{
    protected $overtimeRepo;
    protected $employeeRepo;
    protected $employeeService;
    protected $enterpriseEvent;

    public function __construct(
        OvertimeRepositoryInterface $overtimeRepo,
        EmployeeRepositoryInterface $employeeRepo,
        EmployeeService $employeeService,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->overtimeRepo = $overtimeRepo;
        $this->employeeRepo = $employeeRepo;
        $this->employeeService = $employeeService;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllOvertimes(): array
    {
        return collect($this->overtimeRepo->getAll())
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();
    }

    public function getOvertimeById(string $overtimeId): ?array
    {
        return $this->overtimeRepo->getById($overtimeId);
    }

    public function getApprovedOvertimePayForPeriod(string $employeeId, string $period): float
    {
        $allOvertimes = $this->getAllOvertimes();
        $totalPay = 0.0;

        foreach ($allOvertimes as $ot) {
            if (($ot['Employee_ID'] ?? '') !== $employeeId) continue;
            if (!in_array(strtoupper($ot['Status'] ?? ''), ['APPROVED', 'INCLUDED_IN_PAYROLL'], true)) continue;

            $date = $ot['Date'] ?? '';
            if (str_starts_with($date, $period)) {
                $totalPay += (float) ($ot['Overtime_Pay'] ?? 0);
            }
        }

        return $totalPay;
    }

    public function createOvertimeRequest(array $data): array
    {
        // 1. Server-Side Identity Resolution (NO CLIENT IDOR TRUST)
        $user = auth()->user();
        if (!$user || strtoupper(trim($user->Role ?? '')) === 'STUDENT') {
            throw new Exception("Akses Ditolak: Siswa tidak diperbolehkan mengajukan lembur HR.");
        }

        $allEmployees = collect($this->employeeRepo->fetchAll());
        $employee = $allEmployees->firstWhere('User_ID', $user->User_ID);
        if (!$employee) {
            throw new Exception("Profil pegawai Anda tidak ditemukan.");
        }

        $employeeId = $employee['Employee_ID'];

        if (!$this->employeeService->isEmployeeActive($employeeId)) {
            throw new Exception("Pegawai yang tidak aktif / resigned tidak dapat mengajukan lembur.");
        }

        $date = $data['Date'];
        $startTime = $data['Start_Time'];
        $endTime = $data['End_Time'];
        $reason = $data['Reason'];

        // 2. 100% Server-Side Duration & Overtime Pay Calculation (Client Amount Ignored)
        $startCarbon = Carbon::parse("{$date} {$startTime}");
        $endCarbon = Carbon::parse("{$date} {$endTime}");

        if ($endCarbon->lte($startCarbon)) {
            throw new Exception("Jam selesai lembur harus lebih besar dari jam mulai.");
        }

        $durationHours = max(0.5, round($startCarbon->diffInMinutes($endCarbon) / 60, 2));
        $hourlyRate = (float) config('finance.overtime_rate_per_hour', 25000); // Rp 25.000 / jam
        $overtimePay = $durationHours * $hourlyRate;

        // 3. Concurrency Lock & Duplicate Request Guard
        $lockKey = "overtime_{$employeeId}_{$date}_{$startTime}";
        return Cache::lock($lockKey, 10)->block(3, function () use ($employee, $employeeId, $date, $startTime, $endTime, $durationHours, $hourlyRate, $overtimePay, $reason) {
            $existing = $this->getAllOvertimes();
            foreach ($existing as $ot) {
                if (($ot['Employee_ID'] ?? '') !== $employeeId) continue;
                if (in_array(strtoupper($ot['Status'] ?? ''), ['REJECTED', 'CANCELLED'])) continue;

                if ($ot['Date'] === $date && $ot['Start_Time'] === $startTime) {
                    throw new Exception("Pengajuan lembur ditolak: Anda sudah memiliki pengajuan lembur pada tanggal dan jam tersebut.");
                }
            }

            $overtimeId = 'OVT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            $docNumber = 'DOC-OVT-' . date('Y') . '-' . sprintf("%06d", rand(1, 999999));

            $actorId = \App\Support\ActorIdentity::required();
            $timestamp = now()->toDateTimeString();
            $record = [
                'Overtime_ID' => $overtimeId,
                'Document_Number' => $docNumber,
                'Employee_ID' => $employeeId,
                'Employee_Name' => $employee['Full_Name'] ?? $employeeId,
                'Date' => $date,
                'Start_Time' => $startTime,
                'End_Time' => $endTime,
                'Duration_Hours' => $durationHours,
                'Hourly_Rate' => $hourlyRate,
                'Overtime_Pay' => $overtimePay,
                'Reason' => $reason,
                'Status' => 'SUBMITTED',
                'Submitted_At' => $timestamp,
                'Created_At' => $timestamp,
                'Updated_At' => $timestamp,
            ];

            $this->overtimeRepo->create($record);

            Cache::forget("employee_overtime_{$employeeId}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR', 
                'CREATE', 
                'OVERTIME', 
                $overtimeId, 
                $actorId,
                ['HR', 'ADMINISTRATOR'], 
                [$employeeId], 
                $record
            );

            return $record;
        });
    }

    public function approveOvertime(string $overtimeId, string $approver): array
    {
        return Cache::lock("overtime_status_{$overtimeId}", 10)->block(3, function () use ($overtimeId) {
            $ot = $this->getOvertimeById($overtimeId);
            if (!$ot) {
                throw new Exception("Pengajuan lembur #{$overtimeId} tidak ditemukan.");
            }

            $currentStatus = strtoupper(trim($ot['Status'] ?? 'SUBMITTED'));
            if ($currentStatus !== 'SUBMITTED') {
                throw new Exception("Status lembur saat ini ({$currentStatus}) tidak dapat disetujui.");
            }

            $actorId = \App\Support\ActorIdentity::required();
            $timestamp = now()->toDateTimeString();
            $changes = [
                'Status' => 'APPROVED',
                'Approved_By' => $actorId,
                'Approved_At' => $timestamp,
                'Updated_At' => $timestamp,
            ];
            $this->overtimeRepo->update($overtimeId, $changes);
            $ot = array_merge($ot, $changes);

            Cache::forget("employee_overtime_{$ot['Employee_ID']}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR',
                'UPDATE',
                'OVERTIME',
                $overtimeId,
                $actorId,
                ['HR', 'ADMINISTRATOR', 'EMPLOYEE'],
                [$ot['Employee_ID']],
                ['Status' => 'APPROVED', 'Approved_By' => $actorId]
            );

            return $ot;
        });
    }

    public function rejectOvertime(string $overtimeId, string $approver, ?string $reason = null): array
    {
        return Cache::lock("overtime_status_{$overtimeId}", 10)->block(3, function () use ($overtimeId, $reason) {
            $ot = $this->getOvertimeById($overtimeId);
            if (!$ot) {
                throw new Exception("Pengajuan lembur #{$overtimeId} tidak ditemukan.");
            }

            $currentStatus = strtoupper(trim($ot['Status'] ?? 'SUBMITTED'));
            if ($currentStatus !== 'SUBMITTED') {
                throw new Exception("Status lembur saat ini ({$currentStatus}) tidak dapat ditolak.");
            }

            $actorId = \App\Support\ActorIdentity::required();
            $timestamp = now()->toDateTimeString();
            $changes = [
                'Status' => 'REJECTED',
                'Rejected_By' => $actorId,
                'Rejection_Reason' => $reason ?? 'Tidak disetujui atasan.',
                'Rejected_At' => $timestamp,
                'Updated_At' => $timestamp,
            ];
            $this->overtimeRepo->update($overtimeId, $changes);
            $ot = array_merge($ot, $changes);

            Cache::forget("employee_overtime_{$ot['Employee_ID']}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR',
                'UPDATE',
                'OVERTIME',
                $overtimeId,
                $actorId,
                ['HR', 'EMPLOYEE'],
                [$ot['Employee_ID']],
                ['Status' => 'REJECTED', 'Reason' => $reason]
            );

            return $ot;
        });
    }

    public function getOvertimeDocumentData(string $overtimeId, bool $allowPublicVerification = false): array
    {
        $ot = $this->getOvertimeById($overtimeId);
        if (!$ot) {
            throw new Exception("Dokumen Lembur #{$overtimeId} tidak ditemukan.");
        }

        // Public access is reserved for the signed verification endpoint.
        $user = auth()->user();
        if (!$allowPublicVerification) {
            if (!$user) {
                throw new Exception("Akses Ditolak: Identitas pengguna tidak dapat dipastikan.");
            }

            $role = strtoupper(trim((string) ($user->Role ?? '')));
            if (in_array($role, ['TEACHER', 'EMPLOYEE'], true)) {
                $emp = collect($this->employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
                if (!$emp || ($ot['Employee_ID'] ?? '') !== ($emp['Employee_ID'] ?? '')) {
                    throw new Exception("Akses Ditolak: Dokumen lembur #{$overtimeId} bukan milik akun Anda.");
                }
            } elseif (!in_array($role, ['ADMINISTRATOR', 'HR'], true)) {
                throw new Exception("Akses Ditolak: Role pengguna tidak diizinkan mengakses dokumen lembur.");
            }
        }

        $employee = $this->employeeRepo->findById($ot['Employee_ID'] ?? '') ?? [];

        $verificationUrl = \App\Helpers\PublicVerificationUrl::make('overtimes.verify-public', $overtimeId);

        $qrCodeSvg = null;
        if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
            try {
                $qrCodeSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(90)->margin(1)->generate($verificationUrl);
            } catch (\Exception $e) {
                $qrCodeSvg = null;
            }
        }

        $systemSettingService = app(\App\Services\Core\SystemSettingService::class);
        $companyProfile = $systemSettingService->getCompanyProfile();

        return [
            'companyProfile' => $companyProfile,
            'company' => $companyProfile['company'],
            'bank' => $companyProfile['bank'],
            'document' => $companyProfile['document'],
            'overtime' => $ot,
            'employee' => $employee,
            'verificationUrl' => $verificationUrl,
            'qrCodeSvg' => $qrCodeSvg
        ];
    }
}
