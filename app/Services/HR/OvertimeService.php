<?php

namespace App\Services\HR;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\EmployeeService;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class OvertimeService
{
    protected $employeeRepo;
    protected $employeeService;
    protected $enterpriseEvent;

    public function __construct(
        EmployeeRepositoryInterface $employeeRepo,
        EmployeeService $employeeService,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->employeeRepo = $employeeRepo;
        $this->employeeService = $employeeService;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllOvertimes(): array
    {
        $overtimesList = Cache::get('overtime_records_list', []);
        return array_values($overtimesList);
    }

    public function getOvertimeById(string $overtimeId): ?array
    {
        $overtimes = Cache::get('overtime_records_list', []);
        return $overtimes[$overtimeId] ?? null;
    }

    public function getApprovedOvertimePayForPeriod(string $employeeId, string $period): float
    {
        $allOvertimes = $this->getAllOvertimes();
        $totalPay = 0.0;

        foreach ($allOvertimes as $ot) {
            if (($ot['Employee_ID'] ?? '') !== $employeeId) continue;
            if (in_array(strtoupper($ot['Status'] ?? ''), ['REJECTED', 'CANCELLED'])) continue;

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

        $durationHours = max(0.5, round($endCarbon->diffInMinutes($startCarbon) / 60, 2));
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
                'Submitted_At' => now()->toDateTimeString(),
                'Created_At' => now()->toDateTimeString()
            ];

            $overtimesList = Cache::get('overtime_records_list', []);
            $overtimesList[$overtimeId] = $record;
            Cache::forever('overtime_records_list', $overtimesList);

            Cache::forget("employee_overtime_{$employeeId}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR', 
                'CREATE', 
                'OVERTIME', 
                $overtimeId, 
                auth()->id() ?? 'SYSTEM', 
                ['HR', 'ADMINISTRATOR'], 
                [$employeeId], 
                $record
            );

            return $record;
        });
    }

    public function approveOvertime(string $overtimeId, string $approver): array
    {
        $ot = $this->getOvertimeById($overtimeId);
        if (!$ot) {
            throw new Exception("Pengajuan lembur #{$overtimeId} tidak ditemukan.");
        }

        $currentStatus = strtoupper(trim($ot['Status'] ?? 'SUBMITTED'));
        if (in_array($currentStatus, ['APPROVED', 'INCLUDED_IN_PAYROLL', 'CANCELLED'])) {
            throw new Exception("Status lembur saat ini ({$currentStatus}) tidak dapat disetujui lagi.");
        }

        $ot['Status'] = 'APPROVED';
        $ot['Approved_By'] = $approver;
        $ot['Approved_At'] = now()->toDateTimeString();

        $overtimesList = Cache::get('overtime_records_list', []);
        $overtimesList[$overtimeId] = $ot;
        Cache::forever('overtime_records_list', $overtimesList);

        Cache::forget("employee_overtime_{$ot['Employee_ID']}");
        Cache::forget('hr_dashboard');

        $this->enterpriseEvent->dispatch(
            'HR', 
            'UPDATE', 
            'OVERTIME', 
            $overtimeId, 
            auth()->id() ?? 'SYSTEM', 
            ['HR', 'ADMINISTRATOR', 'EMPLOYEE'], 
            [$ot['Employee_ID']], 
            ['Status' => 'APPROVED', 'Approved_By' => $approver]
        );

        return $ot;
    }

    public function rejectOvertime(string $overtimeId, string $approver, ?string $reason = null): array
    {
        $ot = $this->getOvertimeById($overtimeId);
        if (!$ot) {
            throw new Exception("Pengajuan lembur #{$overtimeId} tidak ditemukan.");
        }

        $ot['Status'] = 'REJECTED';
        $ot['Rejected_By'] = $approver;
        $ot['Rejection_Reason'] = $reason ?? 'Tidak disetujui atasan.';
        $ot['Rejected_At'] = now()->toDateTimeString();

        $overtimesList = Cache::get('overtime_records_list', []);
        $overtimesList[$overtimeId] = $ot;
        Cache::forever('overtime_records_list', $overtimesList);

        Cache::forget("employee_overtime_{$ot['Employee_ID']}");
        Cache::forget('hr_dashboard');

        $this->enterpriseEvent->dispatch(
            'HR', 
            'UPDATE', 
            'OVERTIME', 
            $overtimeId, 
            auth()->id() ?? 'SYSTEM', 
            ['HR', 'EMPLOYEE'], 
            [$ot['Employee_ID']], 
            ['Status' => 'REJECTED', 'Reason' => $reason]
        );

        return $ot;
    }

    public function getOvertimeDocumentData(string $overtimeId): array
    {
        $ot = $this->getOvertimeById($overtimeId);
        if (!$ot) {
            throw new Exception("Dokumen Lembur #{$overtimeId} tidak ditemukan.");
        }

        // IDOR Protection for Student & Employee Users
        $user = auth()->user();
        if ($user && ($user->Role ?? '') === 'STUDENT') {
            throw new Exception("Akses Ditolak: Siswa tidak memiliki akses ke dokumen lembur.");
        }
        if ($user && in_array(strtoupper($user->Role ?? ''), ['TEACHER', 'EMPLOYEE'])) {
            $emp = collect($this->employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (!$emp || ($ot['Employee_ID'] ?? '') !== ($emp['Employee_ID'] ?? '')) {
                throw new Exception("Akses Ditolak: Dokumen lembur #{$overtimeId} bukan milik akun Anda.");
            }
        }

        $employee = $this->employeeRepo->findById($ot['Employee_ID'] ?? '') ?? [];

        $verificationUrl = route('overtimes.verify-public', $overtimeId);

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
