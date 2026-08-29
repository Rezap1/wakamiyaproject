<?php

namespace App\Services\HR;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\LeaveRepositoryInterface;
use App\Services\Core\EmployeeService;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class LeaveService
{
    protected $leaveRepo;
    protected $employeeRepo;
    protected $employeeService;
    protected $enterpriseEvent;

    public function __construct(
        LeaveRepositoryInterface $leaveRepo,
        EmployeeRepositoryInterface $employeeRepo,
        EmployeeService $employeeService,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->leaveRepo = $leaveRepo;
        $this->employeeRepo = $employeeRepo;
        $this->employeeService = $employeeService;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllLeaves(): array
    {
        return collect($this->leaveRepo->getAll())
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();
    }

    public function getLeaveById(string $leaveId): ?array
    {
        return $this->leaveRepo->getById($leaveId);
    }

    public function getApprovedLeavesForPeriod(string $employeeId, string $period): int
    {
        $allLeaves = $this->getAllLeaves();
        $approvedDays = 0;
        $periodStart = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->startOfDay();

        foreach ($allLeaves as $leave) {
            if (($leave['Employee_ID'] ?? '') !== $employeeId) continue;
            if (strtoupper($leave['Status'] ?? '') !== 'APPROVED' && strtoupper($leave['Status'] ?? '') !== 'COMPLETED') continue;

            $startDate = $leave['Start_Date'] ?? '';
            $endDate = $leave['End_Date'] ?? '';

            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->startOfDay();
            if ($start->lte($periodEnd) && $end->gte($periodStart)) {
                $overlapStart = $start->gt($periodStart) ? $start : $periodStart;
                $overlapEnd = $end->lt($periodEnd) ? $end : $periodEnd;
                $approvedDays += ($overlapStart->diffInDays($overlapEnd) + 1);
            }
        }

        return $approvedDays;
    }

    public function createLeaveRequest(array $data): array
    {
        // 1. Server-Side Identity Resolution (NO CLIENT IDOR TRUST)
        $user = auth()->user();
        if (!$user || strtoupper(trim($user->Role ?? '')) === 'STUDENT') {
            throw new Exception("Akses Ditolak: Siswa tidak diperbolehkan mengajukan cuti HR.");
        }

        $allEmployees = collect($this->employeeRepo->fetchAll());
        $employee = $allEmployees->firstWhere('User_ID', $user->User_ID);
        if (!$employee) {
            throw new Exception("Profil pegawai Anda tidak ditemukan.");
        }

        $employeeId = $employee['Employee_ID'];

        // 2. Active Employee Guard (Sub-Phase H1 Guard)
        if (!$this->employeeService->isEmployeeActive($employeeId)) {
            throw new Exception("Pegawai yang sedang tidak aktif / resigned tidak dapat mengajukan cuti.");
        }

        $startDate = $data['Start_Date'];
        $endDate = $data['End_Date'];
        $leaveType = $data['Leave_Type'];
        $reason = $data['Reason'];

        if (Carbon::parse($startDate)->gt(Carbon::parse($endDate))) {
            throw new Exception("Tanggal mulai cuti tidak boleh lebih besar dari tanggal selesai.");
        }

        // 3. Overlapping Dates & Concurrency Lock
        $lockKey = "leave_{$employeeId}_{$startDate}_{$endDate}";
        return Cache::lock($lockKey, 10)->block(3, function () use ($employee, $employeeId, $startDate, $endDate, $leaveType, $reason) {
            $existingLeaves = $this->getAllLeaves();
            foreach ($existingLeaves as $l) {
                if (($l['Employee_ID'] ?? '') !== $employeeId) continue;
                if (in_array(strtoupper($l['Status'] ?? ''), ['REJECTED', 'CANCELLED'])) continue;

                $lStart = $l['Start_Date'];
                $lEnd = $l['End_Date'];

                // Overlap Check
                if (($startDate <= $lEnd) && ($endDate >= $lStart)) {
                    throw new Exception("Pengajuan cuti ditolak: Anda memiliki pengajuan cuti yang bentrok pada rentang tanggal {$lStart} s/d {$lEnd}.");
                }
            }

            $leaveId = 'LEV-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            $docNumber = 'DOC-LEV-' . date('Y') . '-' . sprintf("%06d", rand(1, 999999));

            $actorId = \App\Support\ActorIdentity::required();
            $timestamp = now()->toDateTimeString();
            $record = [
                'Leave_ID' => $leaveId,
                'Document_Number' => $docNumber,
                'Employee_ID' => $employeeId,
                'Employee_Name' => $employee['Full_Name'] ?? $employeeId,
                'Leave_Type' => $leaveType,
                'Start_Date' => $startDate,
                'End_Date' => $endDate,
                'Duration_Days' => (Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1),
                'Reason' => $reason,
                'Status' => 'SUBMITTED',
                'Submitted_At' => $timestamp,
                'Created_At' => $timestamp,
                'Updated_At' => $timestamp,
            ];

            $this->leaveRepo->create($record);

            Cache::forget("employee_leave_{$employeeId}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR', 
                'CREATE', 
                'LEAVE', 
                $leaveId, 
                $actorId,
                ['HR', 'ADMINISTRATOR'], 
                [$employeeId], 
                $record
            );

            return $record;
        });
    }

    public function approveLeave(string $leaveId, string $approver): array
    {
        return Cache::lock("leave_status_{$leaveId}", 10)->block(3, function () use ($leaveId) {
            $leave = $this->getLeaveById($leaveId);
            if (!$leave) {
                throw new Exception("Pengajuan cuti #{$leaveId} tidak ditemukan.");
            }

            $currentStatus = strtoupper(trim($leave['Status'] ?? 'SUBMITTED'));
            if ($currentStatus !== 'SUBMITTED') {
                throw new Exception("Status cuti saat ini ({$currentStatus}) tidak dapat disetujui.");
            }

            $actorId = \App\Support\ActorIdentity::required();
            $timestamp = now()->toDateTimeString();
            $changes = [
                'Status' => 'APPROVED',
                'Approved_By' => $actorId,
                'Approved_At' => $timestamp,
                'Updated_At' => $timestamp,
            ];
            $this->leaveRepo->update($leaveId, $changes);
            $leave = array_merge($leave, $changes);

            Cache::forget("employee_leave_{$leave['Employee_ID']}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR',
                'UPDATE',
                'LEAVE',
                $leaveId,
                $actorId,
                ['HR', 'ADMINISTRATOR', 'EMPLOYEE'],
                [$leave['Employee_ID']],
                ['Status' => 'APPROVED', 'Approved_By' => $actorId]
            );

            return $leave;
        });
    }

    public function rejectLeave(string $leaveId, string $approver, ?string $reason = null): array
    {
        return Cache::lock("leave_status_{$leaveId}", 10)->block(3, function () use ($leaveId, $reason) {
            $leave = $this->getLeaveById($leaveId);
            if (!$leave) {
                throw new Exception("Pengajuan cuti #{$leaveId} tidak ditemukan.");
            }

            $currentStatus = strtoupper(trim($leave['Status'] ?? 'SUBMITTED'));
            if ($currentStatus !== 'SUBMITTED') {
                throw new Exception("Status cuti saat ini ({$currentStatus}) tidak dapat ditolak.");
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
            $this->leaveRepo->update($leaveId, $changes);
            $leave = array_merge($leave, $changes);

            Cache::forget("employee_leave_{$leave['Employee_ID']}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR',
                'UPDATE',
                'LEAVE',
                $leaveId,
                $actorId,
                ['HR', 'EMPLOYEE'],
                [$leave['Employee_ID']],
                ['Status' => 'REJECTED', 'Reason' => $reason]
            );

            return $leave;
        });
    }

    public function cancelLeave(string $leaveId, string $user): array
    {
        return Cache::lock("leave_status_{$leaveId}", 10)->block(3, function () use ($leaveId) {
            $leave = $this->getLeaveById($leaveId);
            if (!$leave) {
                throw new Exception("Pengajuan cuti #{$leaveId} tidak ditemukan.");
            }

            $currentStatus = strtoupper(trim($leave['Status'] ?? 'SUBMITTED'));
            if ($currentStatus !== 'SUBMITTED') {
                throw new Exception("Status cuti saat ini ({$currentStatus}) tidak dapat dibatalkan.");
            }

            $actorId = \App\Support\ActorIdentity::required();
            $timestamp = now()->toDateTimeString();
            $changes = [
                'Status' => 'CANCELLED',
                'Cancelled_By' => $actorId,
                'Cancelled_At' => $timestamp,
                'Updated_At' => $timestamp,
            ];
            $this->leaveRepo->update($leaveId, $changes);
            $leave = array_merge($leave, $changes);

            Cache::forget("employee_leave_{$leave['Employee_ID']}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR',
                'UPDATE',
                'LEAVE',
                $leaveId,
                $actorId,
                ['HR', 'EMPLOYEE'],
                [$leave['Employee_ID']],
                ['Status' => 'CANCELLED']
            );

            return $leave;
        });
    }

    public function getLeaveDocumentData(string $leaveId, bool $allowPublicVerification = false): array
    {
        $leave = $this->getLeaveById($leaveId);
        if (!$leave) {
            throw new Exception("Dokumen Cuti #{$leaveId} tidak ditemukan.");
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
                if (!$emp || ($leave['Employee_ID'] ?? '') !== ($emp['Employee_ID'] ?? '')) {
                    throw new Exception("Akses Ditolak: Dokumen cuti #{$leaveId} bukan milik akun Anda.");
                }
            } elseif (!in_array($role, ['ADMINISTRATOR', 'HR'], true)) {
                throw new Exception("Akses Ditolak: Role pengguna tidak diizinkan mengakses dokumen cuti.");
            }
        }

        $employee = $this->employeeRepo->findById($leave['Employee_ID'] ?? '') ?? [];

        $verificationUrl = \App\Helpers\PublicVerificationUrl::make('leaves.verify-public', $leaveId);

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
            'leave' => $leave,
            'employee' => $employee,
            'verificationUrl' => $verificationUrl,
            'qrCodeSvg' => $qrCodeSvg
        ];
    }
}
