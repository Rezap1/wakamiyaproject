<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Carbon\Carbon;

class EmployeeService
{
    protected $employeeRepository;
    protected $enterpriseEvent;

    public function __construct(
        EmployeeRepositoryInterface $employeeRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->employeeRepository = $employeeRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllEmployees()
    {
        $employees = $this->employeeRepository->fetchAll();
        return $employees->map(function ($employee) {
            $employee['Completeness_Score'] = $this->calculateCompleteness($employee);
            return $employee;
        });
    }

    public function getEmployeeById($id)
    {
        $employee = $this->employeeRepository->findById($id);
        if ($employee) {
            $employee['Completeness_Score'] = $this->calculateCompleteness($employee);
        }
        return $employee;
    }

    public function getEmployeeByNationalId(string $nationalId)
    {
        if (empty($nationalId)) {
            return null;
        }
        $employee = $this->employeeRepository->findByNationalId($nationalId);
        if ($employee) {
            $employee['Completeness_Score'] = $this->calculateCompleteness($employee);
        }
        return $employee;
    }

    public function createEmployee(array $data): array
    {
        // Auto-generate Employee_ID (EMP00000X) if empty
        if (empty($data['Employee_ID'])) {
            $all = $this->employeeRepository->fetchAll();
            $lastId = $all->pluck('Employee_ID')->map(function($id) {
                return (int) preg_replace('/[^0-9]/', '', $id);
            })->max() ?? 0;
            $data['Employee_ID'] = 'EMP' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
        }

        // Auto-generate Employee_Number (NIK) if empty
        if (empty($data['Employee_Number'])) {
            $year = date('Y');
            $data['Employee_Number'] = $this->employeeRepository->generateEmployeeNumber('EMP', $year, 3);
        }

        if (!isset($data['Is_Active'])) {
            $data['Is_Active'] = 'TRUE';
        }
        if (!isset($data['Employment_Status'])) {
            $data['Employment_Status'] = 'ACTIVE';
        }
        if (!isset($data['Created_At'])) {
            $data['Created_At'] = now()->toDateTimeString();
        }

        // Handle profile photo upload
        if (isset($data['Profile_Photo']) && $data['Profile_Photo'] instanceof \Illuminate\Http\UploadedFile) {
            $file = $data['Profile_Photo'];
            $filename = 'employee_' . $data['Employee_ID'] . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('profiles', $filename, 'public');
            $data['Profile_Photo'] = 'storage/profiles/' . $filename;
        }

        $res = $this->employeeRepository->create($data);
        $this->employeeRepository->clearCache();
        if (class_exists('App\Helpers\UserResolverHelper')) {
            \App\Helpers\UserResolverHelper::clearCache();
        }

        $this->enterpriseEvent->dispatch(
            'HR',
            'CREATE',
            'EMPLOYEE',
            $data['Employee_ID'],
            auth()->id() ?? 'SYSTEM',
            ['HR', 'ADMINISTRATOR'],
            [$data['Employee_ID']],
            $data
        );

        return $res;
    }

    public function updateEmployee(string $id, array $data): bool
    {
        $data['Updated_At'] = now()->toDateTimeString();

        if (isset($data['Profile_Photo']) && $data['Profile_Photo'] instanceof \Illuminate\Http\UploadedFile) {
            $file = $data['Profile_Photo'];
            $filename = 'employee_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('profiles', $filename, 'public');
            $data['Profile_Photo'] = 'storage/profiles/' . $filename;
        }

        $res = $this->employeeRepository->updateRow($id, $data);
        $this->employeeRepository->clearCache();
        if (class_exists('App\Helpers\UserResolverHelper')) {
            \App\Helpers\UserResolverHelper::clearCache();
        }

        $this->enterpriseEvent->dispatch(
            'HR',
            'UPDATE',
            'EMPLOYEE',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['HR', 'ADMINISTRATOR'],
            [$id],
            $data
        );

        return $res;
    }

    public function deleteEmployee(string $id): bool
    {
        $res = $this->employeeRepository->delete($id);
        $this->employeeRepository->clearCache();
        if (class_exists('App\Helpers\UserResolverHelper')) {
            \App\Helpers\UserResolverHelper::clearCache();
        }

        $this->enterpriseEvent->dispatch(
            'HR',
            'DELETE',
            'EMPLOYEE',
            $id,
            auth()->id() ?? 'SYSTEM',
            ['HR', 'ADMINISTRATOR'],
            [$id],
            ['Status' => 'DELETED']
        );

        return $res;
    }

    public function sendEmployeeDataEmail(string $id, string $email, $sender = null): bool
    {
        $employee = $this->getEmployeeById($id);
        if (!$employee) {
            throw new \Exception("Karyawan #{$id} tidak ditemukan.");
        }

        $this->enterpriseEvent->dispatch(
            'HR',
            'SEND_EMAIL',
            'EMPLOYEE_DATA',
            $id,
            $sender->User_ID ?? 'SYSTEM',
            ['HR', 'ADMINISTRATOR'],
            [$id],
            ['Target_Email' => $email]
        );

        return true;
    }

    public function isEmployeeActive($id): bool
    {
        $employee = $this->getEmployeeById($id);
        if (!$employee) return false;

        $status = strtoupper(trim($employee['Employment_Status'] ?? $employee['Is_Active'] ?? 'ACTIVE'));
        $isActiveFlag = strtoupper(trim($employee['Is_Active'] ?? 'TRUE'));

        if ($isActiveFlag === 'FALSE') return false;
        
        return !in_array($status, ['INACTIVE', 'RESIGNED', 'TERMINATED']);
    }

    public function updateLifecycleStatus(string $employeeId, string $status, ?string $notes = null): array
    {
        $employee = $this->getEmployeeById($employeeId);
        if (!$employee) {
            throw new \Exception("Pegawai #{$employeeId} tidak ditemukan.");
        }

        $validStatuses = ['ACTIVE', 'INACTIVE', 'RESIGNED', 'TERMINATED', 'TRANSFERRED'];
        $upperStatus = strtoupper(trim($status));
        if (!in_array($upperStatus, $validStatuses)) {
            throw new \Exception("Status kepegawaian '{$status}' tidak valid.");
        }

        $data = [
            'Employment_Status' => $upperStatus,
            'Updated_At' => now()->toDateTimeString()
        ];

        if (in_array($upperStatus, ['INACTIVE', 'RESIGNED', 'TERMINATED'])) {
            $data['Is_Active'] = 'FALSE';
            $data['Exit_Date'] = now()->toDateString();
        } else {
            $data['Is_Active'] = 'TRUE';
        }

        if ($notes) {
            $data['Notes'] = $notes;
        }

        $res = $this->employeeRepository->updateRow($employeeId, $data);
        $this->employeeRepository->clearCache();
        if (class_exists('App\Helpers\UserResolverHelper')) {
            \App\Helpers\UserResolverHelper::clearCache();
        }

        $this->enterpriseEvent->dispatch(
            'HR', 
            'UPDATE', 
            'EMPLOYEE_LIFECYCLE', 
            $employeeId, 
            auth()->id() ?? 'SYSTEM', 
            ['HR', 'ADMINISTRATOR'], 
            [$employeeId], 
            ['Status' => $upperStatus, 'Notes' => $notes]
        );

        return $res;
    }

    protected function calculateCompleteness($employee)
    {
        $fieldsToCheck = [
            'User_ID', 'Department_ID', 'Position_ID', 'Employee_Number',
            'Full_Name', 'Gender', 'Birth_Place', 'Birth_Date',
            'National_ID', 'Phone_Number', 'Email', 'Address', 
            'Join_Date', 'Employment_Status', 'Tax_Number', 'Bank_Name', 'Bank_Account_Number',
            'Profile_Photo'
        ];
        
        $filledCount = 0;
        foreach ($fieldsToCheck as $field) {
            if (!empty($employee[$field])) {
                $filledCount++;
            }
        }
        
        return round(($filledCount / count($fieldsToCheck)) * 100);
    }

    public function isAuthorizedForSensitiveData($user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return false;
        }

        try {
            $roleService = app(\App\Services\Core\RoleService::class);
            $roleId = $user->Role_ID ?? '';
            if (!empty($roleId)) {
                $role = $roleService->getRoleById($roleId);
                $roleName = strtoupper(trim($role['Role_Name'] ?? ''));
                if (str_contains($roleName, 'ADMIN') || str_contains($roleName, 'HR') || str_contains($roleName, 'DIRECTOR')) {
                    return true;
                }
            }
        } catch (\Exception $e) {}

        $rawRole = strtoupper(trim($user->Role ?? ''));
        return str_contains($rawRole, 'ADMIN') || str_contains($rawRole, 'HR') || str_contains($rawRole, 'DIRECTOR');
    }

    public function maskSensitiveFields(array $employee, bool $isAuthorized = false): array
    {
        if ($isAuthorized) {
            return $employee;
        }

        if (!empty($employee['National_ID'])) {
            $employee['National_ID'] = $this->maskNik($employee['National_ID']);
        }
        if (!empty($employee['Tax_Number'])) {
            $employee['Tax_Number'] = $this->maskNpwp($employee['Tax_Number']);
        }
        if (!empty($employee['Bank_Account_Number'])) {
            $employee['Bank_Account_Number'] = $this->maskBankAccount($employee['Bank_Account_Number']);
        }

        return $employee;
    }

    protected function maskNik(string $nik): string
    {
        if (strlen($nik) <= 6) return '******';
        return substr($nik, 0, 4) . '********' . substr($nik, -4);
    }

    protected function maskNpwp(string $npwp): string
    {
        if (strlen($npwp) <= 6) return '******';
        return substr($npwp, 0, 3) . '.***.***.*-' . substr($npwp, -3);
    }

    protected function maskBankAccount(string $account): string
    {
        if (strlen($account) <= 4) return '****';
        return '******' . substr($account, -4);
    }
}
