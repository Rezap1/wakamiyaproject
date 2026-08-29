<?php

namespace App\Services\Attendance;

use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Services\Core\ActivityLogService;
use Exception;

class AttendanceRequestService
{
    protected $requestRepo;
    protected $attendanceRepo;
    protected $activityLog;

    public function __construct(
        AttendanceRequestRepositoryInterface $requestRepo,
        AttendanceRepositoryInterface $attendanceRepo,
        ActivityLogService $activityLog
    ) {
        $this->requestRepo = $requestRepo;
        $this->attendanceRepo = $attendanceRepo;
        $this->activityLog = $activityLog;
    }

    public function getAllPending()
    {
        return collect($this->requestRepo->fetchAll())->where('Status', 'PENDING')->values();
    }

    public function getAll()
    {
        return $this->requestRepo->fetchAll();
    }

    public function getStudentRequests($studentId)
    {
        return collect($this->requestRepo->findByStudent($studentId));
    }

    public function findById($requestId)
    {
        return $this->requestRepo->findById($requestId);
    }

    public function createRequest(array $data, $user)
    {
        // 1. Validation for Duplicate Request
        $existing = $this->getStudentRequests($data['Student_ID'])->firstWhere('Attendance_ID', $data['Attendance_ID']);
        if ($existing) {
            if (in_array($existing['Status'], ['PENDING', 'APPROVED'])) {
                throw new Exception("Anda sudah memiliki pengajuan aktif untuk presensi ini.");
            }
        }

        // 2. Insert Request
        $requestId = $this->requestRepo->generateNewId('REQ', 7);
        $insertData = [
            'Request_ID' => $requestId,
            'Attendance_ID' => $data['Attendance_ID'],
            'Student_ID' => $data['Student_ID'],
            'Schedule_ID' => $data['Schedule_ID'],
            'Attendance_Date' => $data['Attendance_Date'],
            'Request_Type' => $data['Request_Type'], // SAKIT or IZIN
            'Reason' => $data['Reason'],
            'Evidence_URL' => $data['Evidence_URL'],
            'Status' => 'PENDING',
            'Academic_Notes' => '',
            'Reviewed_By' => '',
            'Reviewed_At' => '',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
        ];

        $result = $this->requestRepo->create($insertData);
        $this->requestRepo->clearCache();

        // 3. Audit Log
        $this->activityLog->log(
            'ATTENDANCE',
            'ATTENDANCE_REQUEST_CREATED',
            "Student {$data['Student_ID']} created request {$requestId}",
            null,
            ['Request_ID' => $requestId, 'Student_ID' => $data['Student_ID']]
        );

        return $result;
    }

    public function approveRequest($requestId, $statusToApply, $notes, $user)
    {
        $request = $this->findById($requestId);
        if (!$request) {
            throw new Exception("Pengajuan tidak ditemukan.");
        }
        if ($request['Status'] !== 'PENDING') {
            throw new Exception("Pengajuan sudah diproses sebelumnya.");
        }

        if (!in_array($statusToApply, ['SAKIT', 'IZIN'])) {
            throw new Exception("Status approval harus berupa SAKIT atau IZIN.");
        }

        // Update Request Status
        $updateData = [
            'Status' => 'APPROVED',
            'Academic_Notes' => $notes,
            'Reviewed_By' => $user->User_ID,
            'Reviewed_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
        ];
        
        $this->requestRepo->update($requestId, $updateData);
        $this->requestRepo->clearCache();

        // Sync with Official Attendance
        if (!empty($request['Attendance_ID'])) {
            $attendance = $this->attendanceRepo->findById($request['Attendance_ID']);
            if ($attendance) {
                $this->attendanceRepo->update($request['Attendance_ID'], [
                    'Status' => $statusToApply,
                    'Updated_At' => now()->toDateTimeString(),
                ]);
                $this->attendanceRepo->clearCache();
            } else {
                // If it doesn't exist, create it via service
                $this->attendanceRepo->create([
                    'Attendance_ID' => $request['Attendance_ID'],
                    'Student_ID' => $request['Student_ID'],
                    'Schedule_ID' => $request['Schedule_ID'],
                    'Attendance_Date' => $request['Attendance_Date'],
                    'Status' => $statusToApply,
                    'Created_At' => now()->toDateTimeString(),
                    'Updated_At' => now()->toDateTimeString(),
                ]);
                $this->attendanceRepo->clearCache();
            }
        }

        // Audit Log
        $this->activityLog->log(
            'ATTENDANCE',
            'ATTENDANCE_REQUEST_APPROVED',
            "Academic {$user->User_ID} approved request {$requestId} as {$statusToApply}",
            null,
            ['Request_ID' => $requestId, 'Student_ID' => $request['Student_ID'], 'New_Status' => 'APPROVED', 'Notes' => $notes]
        );

        return true;
    }

    public function rejectRequest($requestId, $notes, $user)
    {
        $request = $this->findById($requestId);
        if (!$request) {
            throw new Exception("Pengajuan tidak ditemukan.");
        }
        if ($request['Status'] !== 'PENDING') {
            throw new Exception("Pengajuan sudah diproses sebelumnya.");
        }

        if (empty(trim($notes))) {
            throw new Exception("Alasan penolakan wajib diisi.");
        }

        // Update Request Status
        $updateData = [
            'Status' => 'REJECTED',
            'Academic_Notes' => $notes,
            'Reviewed_By' => $user->User_ID,
            'Reviewed_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
        ];
        
        $this->requestRepo->update($requestId, $updateData);
        $this->requestRepo->clearCache();

        // Sync with Official Attendance (Mark as ALPA)
        if (!empty($request['Attendance_ID'])) {
            $attendance = $this->attendanceRepo->findById($request['Attendance_ID']);
            if ($attendance) {
                $this->attendanceRepo->update($request['Attendance_ID'], [
                    'Status' => 'ALPA',
                    'Updated_At' => now()->toDateTimeString(),
                ]);
                $this->attendanceRepo->clearCache();
            } else {
                $this->attendanceRepo->create([
                    'Attendance_ID' => $request['Attendance_ID'],
                    'Student_ID' => $request['Student_ID'],
                    'Schedule_ID' => $request['Schedule_ID'],
                    'Attendance_Date' => $request['Attendance_Date'],
                    'Status' => 'ALPA',
                    'Created_At' => now()->toDateTimeString(),
                    'Updated_At' => now()->toDateTimeString(),
                ]);
                $this->attendanceRepo->clearCache();
            }
        }

        // Audit Log
        $this->activityLog->log(
            'ATTENDANCE',
            'ATTENDANCE_REQUEST_REJECTED',
            "Academic {$user->User_ID} rejected request {$requestId}",
            null,
            ['Request_ID' => $requestId, 'Student_ID' => $request['Student_ID'], 'New_Status' => 'REJECTED', 'Notes' => $notes]
        );

        return true;
    }
}
