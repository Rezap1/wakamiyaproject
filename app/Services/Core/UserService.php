<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UserService
{
    protected $userRepository;
    protected $enterpriseEvent;

    public function __construct(
        UserRepositoryInterface $userRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->userRepository = $userRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllUsers()
    {
        return $this->userRepository->fetchAll();
    }

    public function getUserById($id)
    {
        return $this->userRepository->findById($id);
    }

    public function getNextUserId()
    {
        return $this->userRepository->generateNewId('USR', 6);
    }
    
    public function getUserByEmail($email)
    {
        return $this->userRepository->findByEmail($email);
    }

    public function getUserByUsername($username)
    {
        return $this->userRepository->findByUsername($username);
    }

    public function createUser(array $data)
    {
        $newId = $this->userRepository->generateNewId('USR', 6);

        $mappedData = [
            'User_ID' => $newId,
            'Username' => $data['Username'] ?? $data['Email'],
            'Password' => Hash::make($data['Password']),
            'Full_Name' => $data['Full_Name'],
            'Phone_Number' => $data['Phone_Number'] ?? '',
            'Email' => $data['Email'],
            'Employee_ID' => $data['Employee_ID'] ?? '',
            'Role_ID' => $data['Role_ID'],
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Last_Login' => '',
            'Failed_Login' => '0',
            'Last_Password_Change' => now()->toDateTimeString(),
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => \App\Support\ActorIdentity::required(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->userRepository->create($mappedData);

        $this->enterpriseEvent->dispatch(
            'USER',
            'CREATE',
            'USER',
            $newId,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR'],
            [],
            ['Username' => $mappedData['Username'], 'Email' => $mappedData['Email']]
        );
        
        return $mappedData;
    }
    
    public function updateUser($id, array $data)
    {
        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
        ];
        
        if (isset($data['Username'])) $mappedData['Username'] = $data['Username'];
        if (isset($data['Full_Name'])) $mappedData['Full_Name'] = $data['Full_Name'];
        if (isset($data['Phone_Number'])) $mappedData['Phone_Number'] = $data['Phone_Number'];
        if (isset($data['Email'])) $mappedData['Email'] = $data['Email'];
        if (isset($data['Employee_ID'])) $mappedData['Employee_ID'] = $data['Employee_ID'];
        if (isset($data['Role_ID'])) $mappedData['Role_ID'] = $data['Role_ID'];
        if (isset($data['Is_Active'])) $mappedData['Is_Active'] = $data['Is_Active'];
        if (isset($data['Notes'])) $mappedData['Notes'] = $data['Notes'];

        if (!empty($data['Password'])) {
            $mappedData['Password'] = Hash::make($data['Password']);
            $mappedData['Last_Password_Change'] = now()->toDateTimeString();
        }

        $res = $this->userRepository->update($id, $mappedData);

        // Never send credentials (including hashes) to audit/notification
        // metadata. The password has already been persisted by this point.
        $eventData = array_diff_key($mappedData, array_flip(['Password']));

        $this->enterpriseEvent->dispatch(
            'USER',
            'UPDATE',
            'USER',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR'],
            [],
            $eventData
        );

        return $res;
    }

    public function deleteUser($id)
    {
        \App\Support\ActorIdentity::required();

        $user = $this->userRepository->findById($id);
        if (!$user) {
            return false;
        }

        $userId = $id;
        $email = strtolower(trim($user['Email'] ?? ''));
        $username = strtolower(trim($user['Username'] ?? ''));
        $employeeSeedIds = $this->compactIds([$user['Employee_ID'] ?? null]);
        $studentSeedIds = $this->compactIds([$user['Student_ID'] ?? null]);
        $teacherSeedIds = $this->compactIds([$user['Teacher_ID'] ?? null]);
        $userIds = $this->compactIds([$userId]);
        $emailAliases = [$email];
        if (str_contains($username, '@')) {
            $emailAliases[] = $username;
        }
        $emails = $this->compactIds($emailAliases);

        $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
        $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
        $teacherRepo = app(\App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class);
        $payrollRepo = app(\App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class);
        $leaveRepo = app(\App\Interfaces\GoogleSheets\LeaveRepositoryInterface::class);
        $overtimeRepo = app(\App\Interfaces\GoogleSheets\OvertimeRepositoryInterface::class);
        $invoiceRepo = app(\App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class);
        $paymentRepo = app(\App\Interfaces\GoogleSheets\PaymentRepositoryInterface::class);
        $transactionRepo = app(\App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class);
        $scoreRepo = app(\App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class);
        $attendanceRepo = app(\App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class);
        $attendanceRequestRepo = app(\App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface::class);
        $classEnrollmentRepo = app(\App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface::class);
        $scheduleRepo = app(\App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class);
        $assignmentRepo = app(\App\Interfaces\GoogleSheets\AssignmentRepositoryInterface::class);
        $assessmentRepo = app(\App\Interfaces\GoogleSheets\AssessmentRepositoryInterface::class);
        $documentRepo = app(\App\Interfaces\GoogleSheets\DocumentRepositoryInterface::class);
        $approvalRepo = app(\App\Interfaces\GoogleSheets\ApprovalRepositoryInterface::class);
        $approvalHistoryRepo = app(\App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface::class);
        $workflowRepo = app(\App\Interfaces\GoogleSheets\WorkflowRepositoryInterface::class);
        $auditLogRepo = app(\App\Interfaces\GoogleSheets\AuditLogRepositoryInterface::class);
        $notificationRepo = app(\App\Interfaces\GoogleSheets\NotificationRepositoryInterface::class);

        $employeeRows = $this->matchingRows($this->repoRows($employeeRepo), function (array $row) use ($userIds, $employeeSeedIds) {
            return $this->matchesAny($row, ['User_ID'], $userIds)
                || $this->matchesAny($row, ['Employee_ID'], $employeeSeedIds);
        });
        $employeeIds = $this->compactIds($this->idsFromRows($employeeRows, ['Employee_ID']), $employeeSeedIds);

        $studentRows = $this->matchingRows($this->repoRows($studentRepo), function (array $row) use ($userIds, $studentSeedIds) {
            return $this->matchesAny($row, ['User_ID'], $userIds)
                || $this->matchesAny($row, ['Student_ID'], $studentSeedIds);
        });
        $studentIds = $this->compactIds($this->idsFromRows($studentRows, ['Student_ID']), $studentSeedIds);

        $teacherRows = $this->matchingRows($this->repoRows($teacherRepo), function (array $row) use ($userIds, $employeeIds, $teacherSeedIds) {
            return $this->matchesAny($row, ['User_ID'], $userIds)
                || $this->matchesAny($row, ['Employee_ID'], $employeeIds)
                || $this->matchesAny($row, ['Teacher_ID'], $teacherSeedIds);
        });
        $teacherIds = $this->compactIds($this->idsFromRows($teacherRows, ['Teacher_ID']), $teacherSeedIds);

        $ownedIds = $this->compactIds($userIds, $employeeIds, $studentIds, $teacherIds);
        $leaveRows = $this->matchingRows(
            $this->repoRows($leaveRepo),
            fn(array $row) => $this->matchesAny($row, ['Employee_ID'], $employeeIds)
        );
        $leaveIds = $this->idsFromRows($leaveRows, ['Leave_ID', 'id']);
        $overtimeRows = $this->matchingRows(
            $this->repoRows($overtimeRepo),
            fn(array $row) => $this->matchesAny($row, ['Employee_ID'], $employeeIds)
        );
        $overtimeIds = $this->idsFromRows($overtimeRows, ['Overtime_ID', 'id']);

        $invoiceRows = $this->matchingRows($this->repoRows($invoiceRepo), fn(array $row) => $this->matchesAny($row, ['Student_ID'], $studentIds));
        $invoiceIds = $this->idsFromRows($invoiceRows, ['Invoice_ID', 'id']);

        $paymentRows = $this->matchingRows($this->repoRows($paymentRepo), function (array $row) use ($studentIds, $invoiceIds) {
            return $this->matchesAny($row, ['Student_ID'], $studentIds)
                || $this->matchesAny($row, ['Invoice_ID'], $invoiceIds);
        });
        $paymentIds = $this->idsFromRows($paymentRows, ['Payment_ID', 'id']);

        $payrollRows = $this->matchingRows($this->repoRows($payrollRepo), fn(array $row) => $this->matchesAny($row, ['Employee_ID'], $employeeIds));
        $payrollIds = $this->idsFromRows($payrollRows, ['Payroll_ID', 'id']);

        $attendanceRequestRows = $this->matchingRows($this->repoRows($attendanceRequestRepo), function (array $row) use ($userIds, $studentIds) {
            return $this->matchesAny($row, ['User_ID', 'Created_By'], $userIds)
                || $this->matchesAny($row, ['Student_ID'], $studentIds);
        });

        $documentRows = $this->matchingRows($this->repoRows($documentRepo), function (array $row) use ($ownedIds, $invoiceIds, $paymentIds, $payrollIds, $leaveIds, $overtimeIds) {
            $references = $this->compactIds($ownedIds, $invoiceIds, $paymentIds, $payrollIds, $leaveIds, $overtimeIds);
            return $this->matchesAny($row, ['User_ID', 'Employee_ID', 'Student_ID', 'Teacher_ID'], $ownedIds)
                || $this->matchesAny($row, ['Reference_ID', 'Owner_ID'], $references);
        });

        $this->deleteStoredFiles($paymentRows, ['Proof_File', 'Proof_Image']);
        $this->deleteStoredFiles($payrollRows, ['Payment_Proof']);
        $this->deleteStoredFiles($attendanceRequestRows, ['Evidence_URL']);
        $this->deleteStoredFiles($documentRows, ['File_Path']);

        $financeReferenceIds = $this->compactIds($ownedIds, $invoiceIds, $paymentIds, $payrollIds, $leaveIds, $overtimeIds);

        $this->deleteMatchingRows($transactionRepo, $this->repoRows($transactionRepo), function (array $row) use ($ownedIds, $financeReferenceIds) {
            return $this->matchesAny($row, ['User_ID', 'Employee_ID', 'Student_ID', 'Teacher_ID'], $ownedIds)
                || $this->matchesAny($row, ['Reference_ID', 'Source_ID'], $financeReferenceIds);
        }, ['Transaction_ID', 'id'], 'transaction');

        $this->deleteRows($paymentRepo, $paymentRows, ['Payment_ID', 'id'], 'payment');
        $this->deleteRows($invoiceRepo, $invoiceRows, ['Invoice_ID', 'id'], 'invoice');
        $this->deleteRows($payrollRepo, $payrollRows, ['Payroll_ID', 'id'], 'payroll');
        $this->deleteRows($leaveRepo, $leaveRows, ['Leave_ID', 'id'], 'leave');
        $this->deleteRows($overtimeRepo, $overtimeRows, ['Overtime_ID', 'id'], 'overtime');

        $this->deleteMatchingRows($scoreRepo, $this->repoRows($scoreRepo), function (array $row) use ($studentIds, $teacherIds) {
            return $this->matchesAny($row, ['Student_ID'], $studentIds)
                || $this->matchesAny($row, ['Teacher_ID'], $teacherIds);
        }, ['Score_ID', 'id'], 'score');

        $this->deleteMatchingRows($attendanceRepo, $this->repoRows($attendanceRepo), function (array $row) use ($userIds, $employeeIds, $studentIds) {
            return $this->matchesAny($row, ['User_ID'], $userIds)
                || $this->matchesAny($row, ['Employee_ID'], $employeeIds)
                || $this->matchesAny($row, ['Student_ID'], $studentIds);
        }, ['Attendance_ID', 'id'], 'attendance');

        $this->deleteRows($attendanceRequestRepo, $attendanceRequestRows, ['Request_ID', 'id'], 'attendance request');

        $this->deleteMatchingRows($classEnrollmentRepo, $this->repoRows($classEnrollmentRepo), fn(array $row) => $this->matchesAny($row, ['Student_ID'], $studentIds), ['Enrollment_ID', 'id'], 'class enrollment');
        $this->deleteMatchingRows($scheduleRepo, $this->repoRows($scheduleRepo), fn(array $row) => $this->matchesAny($row, ['Teacher_ID'], $teacherIds), ['Schedule_ID', 'id'], 'schedule');
        $this->deleteMatchingRows($assignmentRepo, $this->repoRows($assignmentRepo), fn(array $row) => $this->matchesAny($row, ['Teacher_ID'], $teacherIds), ['Assignment_ID', 'id'], 'assignment');
        $this->deleteMatchingRows($assessmentRepo, $this->repoRows($assessmentRepo), fn(array $row) => $this->matchesAny($row, ['Teacher_ID'], $teacherIds), ['Assessment_ID', 'id'], 'assessment');

        $this->deleteRows($documentRepo, $documentRows, ['Document_ID', 'id'], 'document');

        $approvalKeys = ['User_ID', 'Requester_ID', 'Requested_By', 'Approver_ID', 'Approved_By', 'Actor_ID', 'Employee_ID', 'Student_ID', 'Teacher_ID', 'Reference_ID'];
        $this->deleteMatchingRows($approvalRepo, $this->repoRows($approvalRepo), fn(array $row) => $this->matchesAny($row, $approvalKeys, $financeReferenceIds), ['Approval_ID', 'id'], 'approval');
        $this->deleteMatchingRows($approvalHistoryRepo, $this->repoRows($approvalHistoryRepo), fn(array $row) => $this->matchesAny($row, $approvalKeys, $financeReferenceIds), ['History_ID', 'id'], 'approval history');
        $this->deleteMatchingRows($workflowRepo, $this->repoRows($workflowRepo), fn(array $row) => $this->matchesAny($row, $approvalKeys, $financeReferenceIds), ['Workflow_ID', 'id'], 'workflow');

        $this->deleteMatchingRows($auditLogRepo, $this->repoRows($auditLogRepo), function (array $row) use ($ownedIds, $financeReferenceIds) {
            return $this->matchesAny($row, ['User_ID', 'Actor_ID', 'Actor_User_ID', 'Employee_ID', 'Student_ID', 'Teacher_ID'], $ownedIds)
                || $this->matchesAny($row, ['Reference_ID', 'Entity_ID', 'Target_ID'], $financeReferenceIds);
        }, ['Audit_ID', 'id'], 'audit log');

        $this->deleteMatchingRows($notificationRepo, $this->repoRows($notificationRepo), function (array $row) use ($ownedIds, $financeReferenceIds, $emails) {
            return $this->matchesAny($row, ['User_ID', 'Recipient_User_ID', 'Recipient_ID', 'Actor_ID'], $ownedIds)
                || $this->matchesAny($row, ['Reference_ID', 'Target_ID'], $financeReferenceIds)
                || $this->matchesAny($row, ['Recipient_Email', 'Email'], $emails);
        }, ['Notification_ID', 'id'], 'notification');

        $this->deleteRows($teacherRepo, $teacherRows, ['Teacher_ID', 'id'], 'teacher');
        $this->deleteRows($studentRepo, $studentRows, ['Student_ID', 'id'], 'student');
        $this->deleteRows($employeeRepo, $employeeRows, ['Employee_ID', 'id'], 'employee');

        $res = $this->deleteRecord($this->userRepository, $userId, 'user');
        $this->clearUserCascadeCaches($employeeIds, $studentIds);

        return $res;
    }

    /**
     * Verify and persist a password for the identified account. The lookup is
     * authoritative (MASTER_USER), rather than relying on a stale session copy.
     */
    public function changePassword(string $id, string $currentPassword, string $newPassword): bool
    {
        $storedUser = $this->userRepository->findById($id);
        $storedHash = is_array($storedUser) ? ($storedUser['Password'] ?? $storedUser['password'] ?? '') : '';

        if (!$storedUser || $storedHash === '' || !Hash::check($currentPassword, $storedHash)) {
            return false;
        }

        $newHash = Hash::make($newPassword);
        $updated = $this->userRepository->update($id, [
            'Password' => $newHash,
            'Last_Password_Change' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
        ]);

        if ($updated === false || $updated === null) {
            return false;
        }

        $this->enterpriseEvent->dispatch(
            'USER',
            'UPDATE',
            'USER',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR'],
            [],
            ['Password_Changed' => true]
        );

        return true;
    }

    private function repoRows(object $repository)
    {
        if (method_exists($repository, 'fetchAll')) {
            return collect($repository->fetchAll())->map(fn($row) => (array) $row);
        }

        if (method_exists($repository, 'getAll')) {
            return collect($repository->getAll())->map(fn($row) => (array) $row);
        }

        return collect();
    }

    private function matchingRows($rows, callable $predicate)
    {
        return collect($rows)->map(fn($row) => (array) $row)
            ->filter(fn(array $row) => $predicate($row))
            ->values();
    }

    private function deleteMatchingRows(object $repository, $rows, callable $predicate, array $idKeys, string $label): void
    {
        $this->deleteRows($repository, $this->matchingRows($rows, $predicate), $idKeys, $label);
    }

    private function deleteRows(object $repository, $rows, array $idKeys, string $label): void
    {
        $seen = [];

        foreach (collect($rows) as $row) {
            $recordId = $this->firstValue((array) $row, $idKeys);
            if ($recordId === null || isset($seen[$recordId])) {
                continue;
            }

            $this->deleteRecord($repository, $recordId, $label);
            $seen[$recordId] = true;
        }
    }

    private function deleteRecord(object $repository, string $id, string $label): bool
    {
        if (method_exists($repository, 'hardDelete')) {
            $result = $repository->hardDelete($id);
        } elseif (is_callable([$repository, 'delete'])) {
            $result = $repository->delete($id);
        } else {
            throw new RuntimeException("Repository {$label} tidak mendukung penghapusan.");
        }

        if ($result === false) {
            throw new RuntimeException("Gagal menghapus {$label}: {$id}");
        }

        return true;
    }

    private function deleteStoredFiles($rows, array $pathKeys): void
    {
        $paths = [];
        foreach (collect($rows) as $row) {
            foreach ($pathKeys as $key) {
                $path = $this->normalizeStoredPath(((array) $row)[$key] ?? null);
                if ($path !== null) {
                    $paths[$path] = true;
                }
            }
        }

        foreach (array_keys($paths) as $path) {
            foreach (['local', 'public'] as $diskName) {
                $disk = Storage::disk($diskName);
                if ($disk->exists($path) && !$disk->delete($path)) {
                    throw new RuntimeException("Gagal menghapus file terkait pengguna: {$path}");
                }
            }

            $legacyPath = realpath(storage_path('app/' . $path));
            $storageRoot = realpath(storage_path('app'));
            if ($legacyPath && $storageRoot) {
                $rootPrefix = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (str_starts_with($legacyPath, $rootPrefix) && is_file($legacyPath) && !File::delete($legacyPath)) {
                    throw new RuntimeException("Gagal menghapus file legacy terkait pengguna: {$path}");
                }
            }
        }
    }

    private function normalizeStoredPath($path): ?string
    {
        if (!is_string($path)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path) || preg_match('/^[A-Za-z]:/', $path)) {
            return null;
        }

        return $path;
    }

    private function idsFromRows($rows, array $keys): array
    {
        $ids = [];
        foreach (collect($rows) as $row) {
            $value = $this->firstValue((array) $row, $keys);
            if ($value !== null) {
                $ids[] = $value;
            }
        }

        return $this->compactIds($ids);
    }

    private function firstValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function matchesAny(array $row, array $keys, array $values): bool
    {
        $normalizedValues = [];
        foreach ($this->compactIds($values) as $value) {
            $normalizedValues[strtolower($value)] = true;
        }

        if (empty($normalizedValues)) {
            return false;
        }

        foreach ($keys as $key) {
            $value = strtolower(trim((string) ($row[$key] ?? '')));
            if ($value !== '' && isset($normalizedValues[$value])) {
                return true;
            }
        }

        return false;
    }

    private function compactIds(...$groups): array
    {
        $values = [];
        foreach ($groups as $group) {
            foreach ((array) $group as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $values[strtolower($value)] = $value;
                }
            }
        }

        return array_values($values);
    }

    private function clearUserCascadeCaches(array $employeeIds, array $studentIds): void
    {
        foreach ([
            'users_sheet_all',
            'employees_sheet_all',
            'students_sheet_all',
            'teachers_sheet_all',
            'payroll_sheet_all',
            'leave_sheet_all',
            'overtime_sheet_all',
            'scores_sheet_all',
            'attendances_sheet_all',
            'finance_invoice_sheet_all',
            'finance_payment_sheet_all',
            'finance_transaction_sheet_all',
            'class_enrollments_sheet_all',
            'attendance_requests_sheet_all',
            'schedules_sheet_all',
            'assignments_sheet_all',
            'assessments_sheet_all',
            'documents_sheet_all',
            'approvals_sheet_all',
            'approval_history_sheet_all',
            'workflows_sheet_all',
            'audit_log_sheet_all',
            'notification_list_all',
            'hr_dashboard',
            'finance_dashboard',
            'dashboard_finance',
        ] as $cacheKey) {
            Cache::forget($cacheKey);
        }

        foreach ($employeeIds as $employeeId) {
            Cache::forget('employee_leave_' . $employeeId);
            Cache::forget('employee_overtime_' . $employeeId);
        }

        foreach ($studentIds as $studentId) {
            Cache::forget('student_billing_' . $studentId);
            Cache::forget('student_attendance_' . $studentId);
        }
    }
}
