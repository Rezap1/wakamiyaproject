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

    public function getProfilePhotoPath(string $employeeId): ?string
    {
        if (empty($employeeId)) return null;

        $mapFile = storage_path('app/employee_photos.json');
        if (file_exists($mapFile)) {
            $map = json_decode(file_get_contents($mapFile), true) ?? [];
            if (!empty($map[$employeeId]) && file_exists(public_path($map[$employeeId]))) {
                return $map[$employeeId];
            }
        }

        // Fallback: check filesystem directly for employee_{Employee_ID}.*
        $files = glob(storage_path('app/public/profiles/employee_' . $employeeId . '.*'));
        if (!empty($files)) {
            $filename = basename($files[0]);
            return 'storage/profiles/' . $filename;
        }

        return null;
    }

    public function saveProfilePhoto(string $employeeId, \Illuminate\Http\UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $filename = 'employee_' . $employeeId . '.' . $ext;
        
        $dir = storage_path('app/public/profiles');
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        // Clean old files for this employee
        $oldFiles = glob($dir . '/employee_' . $employeeId . '.*');
        if ($oldFiles) {
            foreach ($oldFiles as $old) {
                @unlink($old);
            }
        }

        $file->storeAs('profiles', $filename, 'public');
        $photoPath = 'storage/profiles/' . $filename;

        // Save in JSON map
        $mapFile = storage_path('app/employee_photos.json');
        $map = file_exists($mapFile) ? (json_decode(file_get_contents($mapFile), true) ?? []) : [];
        $map[$employeeId] = $photoPath;
        file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT));

        return $photoPath;
    }

    public function getAllEmployees()
    {
        $employees = $this->employeeRepository->fetchAll();
        return $employees->map(function ($employee) {
            if (empty($employee['Full_Name']) && !empty($employee['User_ID'])) {
                $employee['Full_Name'] = \App\Helpers\UserResolverHelper::getName($employee['User_ID']);
            }
            if (empty($employee['Profile_Photo']) && !empty($employee['Employee_ID'])) {
                $employee['Profile_Photo'] = $this->getProfilePhotoPath($employee['Employee_ID']);
            }
            $employee['Completeness_Score'] = $this->calculateCompleteness($employee);
            return $employee;
        });
    }

    public function getEmployeeById($id)
    {
        $employee = $this->employeeRepository->findById($id);
        if ($employee) {
            if (empty($employee['Full_Name']) && !empty($employee['User_ID'])) {
                $employee['Full_Name'] = \App\Helpers\UserResolverHelper::getName($employee['User_ID']);
            }
            if (empty($employee['Profile_Photo']) && !empty($employee['Employee_ID'])) {
                $employee['Profile_Photo'] = $this->getProfilePhotoPath($employee['Employee_ID']);
            }
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
            if (empty($employee['Full_Name']) && !empty($employee['User_ID'])) {
                $employee['Full_Name'] = \App\Helpers\UserResolverHelper::getName($employee['User_ID']);
            }
            if (empty($employee['Profile_Photo']) && !empty($employee['Employee_ID'])) {
                $employee['Profile_Photo'] = $this->getProfilePhotoPath($employee['Employee_ID']);
            }
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

        // Auto-populate Full_Name, Email, Phone_Number from User if User_ID is provided
        if (!empty($data['User_ID'])) {
            try {
                $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
                $user = $userRepo->findById($data['User_ID']);
                if (!$user) {
                    throw new \Exception("User tidak ditemukan.");
                }
                if ($this->userHasRole($user, 'STUDENT')) {
                    throw new \Exception("Akun siswa tidak dapat digunakan sebagai profil karyawan.");
                }

                $existingEmployee = collect($this->employeeRepository->fetchAll())
                    ->firstWhere('User_ID', $data['User_ID']);
                if ($existingEmployee) {
                    throw new \Exception("User ini sudah terdaftar sebagai karyawan.");
                }

                if (empty($data['Full_Name'])) {
                    $data['Full_Name'] = $user['Full_Name'] ?? $user['Username'] ?? '';
                }
                if (empty($data['Email'])) {
                    $data['Email'] = $user['Email'] ?? '';
                }
                if (empty($data['Phone_Number'])) {
                    $data['Phone_Number'] = $user['Phone_Number'] ?? '';
                }
            } catch (\Exception $e) {
                throw $e;
            }
        }

        if (empty($data['Full_Name']) && !empty($data['User_ID'])) {
            $data['Full_Name'] = \App\Helpers\UserResolverHelper::getName($data['User_ID']);
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
            $savedPath = $this->saveProfilePhoto($data['Employee_ID'], $data['Profile_Photo']);
            $data['Profile_Photo'] = $savedPath;
        }

        // Remove UploadedFile instance if any remaining before repository call
        unset($data['Profile_Photo']);

        $res = $this->employeeRepository->create($data);
        $this->syncUserEmployeeLink($data['User_ID'] ?? '', $data['Employee_ID']);
        $this->employeeRepository->clearCache();
        if (class_exists('App\Helpers\UserResolverHelper')) {
            \App\Helpers\UserResolverHelper::clearCache();
        }

        $this->enterpriseEvent->dispatch(
            'HR',
            'CREATE',
            'EMPLOYEE',
            $data['Employee_ID'],
            \App\Support\ActorIdentity::required(),
            ['HR', 'ADMINISTRATOR'],
            [$data['Employee_ID']],
            $data
        );

        return $data;
    }

    public function updateEmployee(string $id, array $data): bool
    {
        $data['Updated_At'] = now()->toDateTimeString();

        $employee = $this->getEmployeeById($id);
        if (!$employee) {
            throw new \Exception("Karyawan #{$id} tidak ditemukan.");
        }

        $userId = $data['User_ID'] ?? ($employee['User_ID'] ?? '');
        if (!empty($userId)) {
            $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
            $user = $userRepo->findById($userId);
            if (!$user) {
                throw new \Exception("User tidak ditemukan.");
            }
            if ($this->userHasRole($user, 'STUDENT')) {
                throw new \Exception("Akun siswa tidak dapat digunakan sebagai profil karyawan.");
            }

            $duplicate = collect($this->employeeRepository->fetchAll())
                ->first(function ($row) use ($id, $userId) {
                    return ($row['Employee_ID'] ?? '') !== $id && ($row['User_ID'] ?? '') === $userId;
                });
            if ($duplicate) {
                throw new \Exception("User ini sudah terdaftar sebagai karyawan lain.");
            }

            $data['Full_Name'] = $user['Full_Name'] ?? $user['Username'] ?? ($employee['Full_Name'] ?? '');
            $data['Email'] = $user['Email'] ?? ($employee['Email'] ?? '');
            $data['Phone_Number'] = $user['Phone_Number'] ?? ($employee['Phone_Number'] ?? '');
        }

        if (isset($data['Profile_Photo']) && $data['Profile_Photo'] instanceof \Illuminate\Http\UploadedFile) {
            $savedPath = $this->saveProfilePhoto($id, $data['Profile_Photo']);
            $data['Profile_Photo'] = $savedPath;
        }

        unset($data['Profile_Photo']);

        $res = $this->employeeRepository->updateRow($id, $data);
        $this->syncUserEmployeeLink($userId, $id);
        $oldUserId = $employee['User_ID'] ?? '';
        if (!empty($oldUserId) && $oldUserId !== $userId) {
            $this->syncUserEmployeeLink($oldUserId, '');
        }
        $this->employeeRepository->clearCache();
        if (class_exists('App\Helpers\UserResolverHelper')) {
            \App\Helpers\UserResolverHelper::clearCache();
        }

        $this->enterpriseEvent->dispatch(
            'HR',
            'UPDATE',
            'EMPLOYEE',
            $id,
            \App\Support\ActorIdentity::required(),
            ['HR', 'ADMINISTRATOR'],
            [$id],
            $data
        );

        return (bool) $res;
    }

    public function deleteEmployee(string $id): bool
    {
        $res = $this->employeeRepository->delete($id);
        
        // Clean photo
        $dir = storage_path('app/public/profiles');
        $oldFiles = glob($dir . '/employee_' . $id . '.*');
        if ($oldFiles) {
            foreach ($oldFiles as $old) {
                @unlink($old);
            }
        }

        $mapFile = storage_path('app/employee_photos.json');
        if (file_exists($mapFile)) {
            $map = json_decode(file_get_contents($mapFile), true) ?? [];
            if (isset($map[$id])) {
                unset($map[$id]);
                file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT));
            }
        }

        $this->employeeRepository->clearCache();
        if (class_exists('App\Helpers\UserResolverHelper')) {
            \App\Helpers\UserResolverHelper::clearCache();
        }

        $this->enterpriseEvent->dispatch(
            'HR',
            'DELETE',
            'EMPLOYEE',
            $id,
            \App\Support\ActorIdentity::required(),
            ['HR', 'ADMINISTRATOR'],
            [$id],
            ['Status' => 'DELETED']
        );

        return (bool) $res;
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
            $sender->User_ID ?? \App\Support\ActorIdentity::required(),
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

        $this->employeeRepository->updateRow($employeeId, $data);
        $this->employeeRepository->clearCache();
        if (class_exists('App\Helpers\UserResolverHelper')) {
            \App\Helpers\UserResolverHelper::clearCache();
        }

        $this->enterpriseEvent->dispatch(
            'HR', 
            'UPDATE', 
            'EMPLOYEE_LIFECYCLE', 
            $employeeId, 
            \App\Support\ActorIdentity::required(), 
            ['HR', 'ADMINISTRATOR'], 
            [$employeeId], 
            ['Status' => $upperStatus, 'Notes' => $notes]
        );

        return $this->getEmployeeById($employeeId) ?? $data;
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

    private function syncUserEmployeeLink(string $userId, string $employeeId): void
    {
        if (empty($userId)) {
            return;
        }

        try {
            $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
            $userRepo->update($userId, [
                'Employee_ID' => $employeeId,
                'Updated_At' => now()->toDateTimeString(),
                'Updated_By' => \App\Support\ActorIdentity::required(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to sync Employee_ID {$employeeId} to user {$userId}: " . $e->getMessage());
        }
    }

    private function userHasRole(array $user, string $expectedRole): bool
    {
        $roleName = \App\Helpers\UserResolverHelper::getRoleName($user['Role_ID'] ?? '');
        return strtoupper(trim($roleName)) === strtoupper($expectedRole);
    }
}
