<?php

namespace App\Services\HR;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\EmployeeService;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class LeaveService
{
    protected $attendanceRepo;
    protected $employeeRepo;
    protected $employeeService;
    protected $enterpriseEvent;

    public function __construct(
        AttendanceRepositoryInterface $attendanceRepo,
        EmployeeRepositoryInterface $employeeRepo,
        EmployeeService $employeeService,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->attendanceRepo = $attendanceRepo;
        $this->employeeRepo = $employeeRepo;
        $this->employeeService = $employeeService;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllLeaves(): array
    {
        $leavesList = Cache::get('leave_records_list', []);
        return array_values($leavesList);
    }

    public function getLeaveById(string $leaveId): ?array
    {
        $leaves = Cache::get('leave_records_list', []);
        return $leaves[$leaveId] ?? null;
    }

    public function getApprovedLeavesForPeriod(string $employeeId, string $period): int
    {
        $allLeaves = $this->getAllLeaves();
        $approvedDays = 0;

        foreach ($allLeaves as $leave) {
            if (($leave['Employee_ID'] ?? '') !== $employeeId) continue;
            if (strtoupper($leave['Status'] ?? '') !== 'APPROVED' && strtoupper($leave['Status'] ?? '') !== 'COMPLETED') continue;

            $startDate = $leave['Start_Date'] ?? '';
            $endDate = $leave['End_Date'] ?? '';

            if (str_starts_with($startDate, $period) || str_starts_with($endDate, $period)) {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);
                $approvedDays += ($start->diffInDays($end) + 1);
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
                'Submitted_At' => now()->toDateTimeString(),
                'Created_At' => now()->toDateTimeString()
            ];

            $leavesList = Cache::get('leave_records_list', []);
            $leavesList[$leaveId] = $record;
            Cache::forever('leave_records_list', $leavesList);

            Cache::forget("employee_leave_{$employeeId}");
            Cache::forget('hr_dashboard');

            $this->enterpriseEvent->dispatch(
                'HR', 
                'CREATE', 
                'LEAVE', 
                $leaveId, 
                auth()->id() ?? 'SYSTEM', 
                ['HR', 'ADMINISTRATOR'], 
                [$employeeId], 
                $record
            );

            return $record;
        });
    }

    public function approveLeave(string $leaveId, string $approver): array
    {
        $leave = $this->getLeaveById($leaveId);
        if (!$leave) {
            throw new Exception("Pengajuan cuti #{$leaveId} tidak ditemukan.");
        }

        $currentStatus = strtoupper(trim($leave['Status'] ?? 'SUBMITTED'));
        if (in_array($currentStatus, ['APPROVED', 'COMPLETED', 'CANCELLED'])) {
            throw new Exception("Status cuti saat ini ({$currentStatus}) tidak dapat disetujui lagi.");
        }

        $leave['Status'] = 'APPROVED';
        $leave['Approved_By'] = $approver;
        $leave['Approved_At'] = now()->toDateTimeString();

        $leavesList = Cache::get('leave_records_list', []);
        $leavesList[$leaveId] = $leave;
        Cache::forever('leave_records_list', $leavesList);

        Cache::forget("employee_leave_{$leave['Employee_ID']}");
        Cache::forget('hr_dashboard');

        $this->enterpriseEvent->dispatch(
            'HR', 
            'UPDATE', 
            'LEAVE', 
            $leaveId, 
            auth()->id() ?? 'SYSTEM', 
            ['HR', 'ADMINISTRATOR', 'EMPLOYEE'], 
            [$leave['Employee_ID']], 
            ['Status' => 'APPROVED', 'Approved_By' => $approver]
        );

        return $leave;
    }

    public function rejectLeave(string $leaveId, string $approver, ?string $reason = null): array
    {
        $leave = $this->getLeaveById($leaveId);
        if (!$leave) {
            throw new Exception("Pengajuan cuti #{$leaveId} tidak ditemukan.");
        }

        $leave['Status'] = 'REJECTED';
        $leave['Rejected_By'] = $approver;
        $leave['Rejection_Reason'] = $reason ?? 'Tidak disetujui atasan.';
        $leave['Rejected_At'] = now()->toDateTimeString();

        $leavesList = Cache::get('leave_records_list', []);
        $leavesList[$leaveId] = $leave;
        Cache::forever('leave_records_list', $leavesList);

        Cache::forget("employee_leave_{$leave['Employee_ID']}");
        Cache::forget('hr_dashboard');

        $this->enterpriseEvent->dispatch(
            'HR', 
            'UPDATE', 
            'LEAVE', 
            $leaveId, 
            auth()->id() ?? 'SYSTEM', 
            ['HR', 'EMPLOYEE'], 
            [$leave['Employee_ID']], 
            ['Status' => 'REJECTED', 'Reason' => $reason]
        );

        return $leave;
    }

    public function cancelLeave(string $leaveId, string $user): array
    {
        $leave = $this->getLeaveById($leaveId);
        if (!$leave) {
            throw new Exception("Pengajuan cuti #{$leaveId} tidak ditemukan.");
        }

        if (in_array(strtoupper($leave['Status'] ?? ''), ['COMPLETED', 'PAID'])) {
            throw new Exception("Pengajuan cuti yang telah selesai tidak dapat dibatalkan.");
        }

        $leave['Status'] = 'CANCELLED';
        $leave['Cancelled_By'] = $user;
        $leave['Cancelled_At'] = now()->toDateTimeString();

        $leavesList = Cache::get('leave_records_list', []);
        $leavesList[$leaveId] = $leave;
        Cache::forever('leave_records_list', $leavesList);

        Cache::forget("employee_leave_{$leave['Employee_ID']}");
        Cache::forget('hr_dashboard');

        $this->enterpriseEvent->dispatch(
            'HR', 
            'UPDATE', 
            'LEAVE', 
            $leaveId, 
            auth()->id() ?? 'SYSTEM', 
            ['HR', 'EMPLOYEE'], 
            [$leave['Employee_ID']], 
            ['Status' => 'CANCELLED']
        );

        return $leave;
    }

    public function getLeaveDocumentData(string $leaveId): array
    {
        $leave = $this->getLeaveById($leaveId);
        if (!$leave) {
            throw new Exception("Dokumen Cuti #{$leaveId} tidak ditemukan.");
        }

        $employee = $this->employeeRepo->findById($leave['Employee_ID'] ?? '') ?? [];

        $verificationUrl = route('leaves.verify-public', $leaveId);

        $qrCodeSvg = null;
        if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
            try {
                $qrCodeSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(90)->margin(1)->generate($verificationUrl);
            } catch (\Exception $e) {
                $qrCodeSvg = null;
            }
        }

        return [
            'company' => [
                'name' => 'WAKAMIYA MANAGEMENT SYSTEM',
                'tagline' => 'Enterprise Human Resource Engine',
                'address' => 'Jl. Raya Wakamiya No. 88, Jakarta Selatan 12930',
                'contact' => 'Telp: (021) 8000-9999 | Email: hr@wakamiya.ac.id'
            ],
            'leave' => $leave,
            'employee' => $employee,
            'verificationUrl' => $verificationUrl,
            'qrCodeSvg' => $qrCodeSvg
        ];
    }
}
