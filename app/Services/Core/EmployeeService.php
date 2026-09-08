<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
        $employeeId = trim($employeeId);
        if ($employeeId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $employeeId)) {
            return null;
        }

        $mapFile = storage_path('app/employee_photos.json');
        if (file_exists($mapFile)) {
            $map = json_decode((string) file_get_contents($mapFile), true);
            $map = is_array($map) ? $map : [];
            $mapped = $this->normalizePhotoPath($map[$employeeId] ?? null);
            if ($mapped !== null && Storage::disk('public')->exists($mapped)) {
                return 'storage/' . $mapped;
            }
        }

        // Fallback for records created before the atomic map was introduced.
        foreach (Storage::disk('public')->files('profiles') as $path) {
            if (preg_match('/^employee_' . preg_quote($employeeId, '/') . '(?:_[A-Za-z0-9-]+)?\.(?:jpe?g|png|webp)$/i', basename($path))) {
                return 'storage/' . $path;
            }
        }

        return null;
    }

    public function saveProfilePhoto(string $employeeId, UploadedFile $file): string
    {
        $stage = $this->stageProfilePhoto($employeeId, $file);
        try {
            return $this->commitStagedPhoto($stage);
        } catch (Throwable $e) {
            $this->discardStagedPhoto($stage);
            throw $e;
        }
    }

    /**
     * Store an upload under a non-user-controlled name. The returned stage is
     * intentionally not visible in the photo map until the related sheet
     * writes and read-back have succeeded.
     */
    private function stageProfilePhoto(string $employeeId, UploadedFile $file): array
    {
        $employeeId = trim($employeeId);
        if ($employeeId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $employeeId)) {
            throw new RuntimeException('ID karyawan tidak valid untuk foto profil.');
        }

        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mime = strtolower((string) $file->getMimeType());
        $size = (int) ($file->getSize() ?? 0);
        $maxBytes = ((int) config('upload.max_kb', 5120)) * 1024;
        if (!$file->isValid() || !isset($allowedMime[$mime]) || $size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('Foto profil harus JPG, PNG, atau WEBP dan berukuran maksimal 5MB.');
        }

        $extension = $allowedMime[$mime];
        $token = (string) Str::uuid();
        $filename = '.pending_employee_' . $employeeId . '_' . $token . '.' . $extension;
        $stored = $file->storeAs('profiles', $filename, 'public');
        if (!$stored || !Storage::disk('public')->exists($stored)) {
            throw new RuntimeException('Foto profil gagal diunggah.');
        }

        $oldPath = $this->normalizePhotoPath($this->readPhotoMap()[$employeeId] ?? null);
        if ($oldPath === null) {
            $oldPath = $this->normalizePhotoPath($this->getProfilePhotoPath($employeeId));
        }
        return [
            'employee_id' => $employeeId,
            'staged_path' => $stored,
            'final_path' => 'profiles/employee_' . $employeeId . '_' . $token . '.' . $extension,
            'old_path' => $oldPath,
        ];
    }

    private function commitStagedPhoto(array $stage): string
    {
        $disk = Storage::disk('public');
        $staged = (string) ($stage['staged_path'] ?? '');
        $final = (string) ($stage['final_path'] ?? '');
        $employeeId = (string) ($stage['employee_id'] ?? '');
        if ($staged === '' || $final === '' || $employeeId === '' || !$disk->exists($staged)) {
            throw new RuntimeException('Tahap penyimpanan foto profil tidak valid.');
        }

        if (!$disk->move($staged, $final)) {
            throw new RuntimeException('Foto profil gagal dipindahkan ke lokasi final.');
        }

        $map = $this->readPhotoMap();
        $previous = $map[$employeeId] ?? null;
        $map[$employeeId] = 'storage/' . $final;
        try {
            $this->writePhotoMap($map);
        } catch (Throwable $e) {
            $disk->delete($final);
            throw $e;
        }

        // Retire the old file only after the new metadata is durable. A
        // cleanup failure must not roll back the authoritative new mapping.
        $old = $this->normalizePhotoPath($stage['old_path'] ?? $previous);
        if ($old !== null && $old !== $final && $disk->exists($old)) {
            $disk->delete($old);
        }

        return 'storage/' . $final;
    }

    private function discardStagedPhoto(array $stage): void
    {
        $path = (string) ($stage['staged_path'] ?? '');
        if ($path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    private function readPhotoMap(): array
    {
        $mapFile = storage_path('app/employee_photos.json');
        if (!is_file($mapFile)) {
            return [];
        }
        $map = json_decode((string) file_get_contents($mapFile), true);
        return is_array($map) ? $map : [];
    }

    private function writePhotoMap(array $map): void
    {
        $mapFile = storage_path('app/employee_photos.json');
        $dir = dirname($mapFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Direktori metadata foto tidak dapat dibuat.');
        }
        $temporary = $mapFile . '.' . Str::uuid() . '.tmp';
        $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $mapFile)) {
            @unlink($temporary);
            throw new RuntimeException('Metadata foto profil gagal disimpan.');
        }
    }

    private function normalizePhotoPath($path): ?string
    {
        if (!is_string($path)) {
            return null;
        }
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'profiles/')) {
            return null;
        }
        return $path;
    }

    public function getAllEmployees()
    {
        $employees = $this->employeeRepository->fetchAll();
        return $employees->map(function ($employee) {
            $employee = $this->enrichEmployee($employee);
            $employee['Completeness_Score'] = $this->calculateCompleteness($employee);
            return $employee;
        });
    }

    public function getEmployeeById($id)
    {
        $employee = $this->employeeRepository->findById($id);
        if ($employee) {
            $employee = $this->enrichEmployee($employee);
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
            $employee = $this->enrichEmployee($employee);
            $employee['Completeness_Score'] = $this->calculateCompleteness($employee);
        }
        return $employee;
    }

    public function createEmployee(array $data): array
    {
        // Employee_ID is generated by this service and is never accepted from
        // a request payload. This keeps the route key/primary key immutable.
        unset($data['Employee_ID']);

        // Auto-generate Employee_ID (EMP00000X) if empty
        $all = $this->employeeRepository->fetchAll();
        $lastId = collect($all)->pluck('Employee_ID')->map(function($id) {
            return (int) preg_replace('/[^0-9]/', '', (string) $id);
        })->max() ?? 0;
        $data['Employee_ID'] = 'EMP' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);

        // Auto-generate Employee_Number (NIK) if empty
        if (empty($data['Employee_Number'])) {
            $year = date('Y');
            $data['Employee_Number'] = $this->employeeRepository->generateEmployeeNumber('EMP', $year, 3);
        }

        $userRepo = null;
        $user = null;
        $phoneProvided = array_key_exists('Phone_Number', $data);

        // MASTER_USER is the SSOT for identity/contact. The employee sheet
        // keeps a synchronized denormalized copy for existing reports.
        if (!empty($data['User_ID'])) {
            $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
            $user = $userRepo->findById((string) $data['User_ID']);
            if (!$user) {
                throw new \Exception("User tidak ditemukan.");
            }
            if ($this->userHasRole((array) $user, 'STUDENT')) {
                throw new \Exception("Akun siswa tidak dapat digunakan sebagai profil karyawan.");
            }

            $existingEmployee = collect($this->employeeRepository->fetchAll())
                ->first(fn ($row) => strcasecmp((string) ($row['User_ID'] ?? ''), (string) $data['User_ID']) === 0);
            if ($existingEmployee) {
                throw new \Exception("User ini sudah terdaftar sebagai karyawan.");
            }

            $contact = $this->contactFromUser((array) $user, []);
            $data['Full_Name'] = $contact['Full_Name'];
            $data['Email'] = $contact['Email'];
            $data['Phone_Number'] = $phoneProvided
                ? (string) $data['Phone_Number']
                : $contact['Phone_Number'];
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

        $photoStage = null;
        $created = false;
        $createdUserTouched = false;
        $createdUserId = trim((string) ($data['User_ID'] ?? ''));
        $createdOriginalUserPhone = $user['Phone_Number'] ?? null;
        try {
            if (($data['Profile_Photo'] ?? null) instanceof UploadedFile) {
                $photoStage = $this->stageProfilePhoto($data['Employee_ID'], $data['Profile_Photo']);
            }

            $payload = $data;
            unset($payload['Profile_Photo'], $payload['Employee_ID']);
            // Mark before the call: an ambiguous Google response can mean the
            // write reached Sheets even though the client observed an error.
            $created = true;
            $res = $this->employeeRepository->create(array_merge(['Employee_ID' => $data['Employee_ID']], $payload));
            if ($res === false || $res === null) {
                throw new RuntimeException('Penyimpanan data karyawan gagal.');
            }

            if ($createdUserId !== '') {
                $attributes = [];
                if ($phoneProvided && $user !== null) {
                    $attributes['Phone_Number'] = (string) $data['Phone_Number'];
                }
                $createdUserTouched = true;
                $this->syncUserEmployeeLink($createdUserId, $data['Employee_ID'], $attributes);
                $this->verifyFreshRow($userRepo, $createdUserId, array_merge($attributes, ['Employee_ID' => $data['Employee_ID']]), 'user');
            }

            $this->verifyFreshRow($this->employeeRepository, $data['Employee_ID'], $payload, 'employee');
            if ($photoStage !== null) {
                $data['Profile_Photo'] = $this->commitStagedPhoto($photoStage);
                $photoStage = null;
            }
        } catch (Throwable $e) {
            if ($created && method_exists($this->employeeRepository, 'delete')) {
                try { $this->employeeRepository->delete($data['Employee_ID']); } catch (Throwable $rollback) {
                    Log::error('Employee create compensation failed', ['employee_id' => $data['Employee_ID'], 'exception' => get_class($rollback)]);
                }
            }
            if ($createdUserTouched && $userRepo !== null && $createdUserId !== '') {
                try {
                    $userRollback = ['Employee_ID' => ''];
                    if ($phoneProvided) {
                        $userRollback['Phone_Number'] = (string) ($createdOriginalUserPhone ?? '');
                    }
                    $userRepo->update($createdUserId, $userRollback);
                } catch (Throwable $rollback) {
                    Log::error('Employee create user compensation failed', ['user_id' => $createdUserId, 'exception' => get_class($rollback)]);
                }
            }
            if ($photoStage !== null) {
                $this->discardStagedPhoto($photoStage);
            }
            Log::error('Employee create integrity failure', ['employee_id' => $data['Employee_ID'], 'stage' => 'persist_or_verify', 'exception' => get_class($e)]);
            throw $e;
        }

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
            $this->auditPayload($data)
        );

        return $data;
    }

    public function updateEmployee(string $id, array $data): bool
    {
        $data['Updated_At'] = now()->toDateTimeString();
        unset($data['Employee_ID']);

        $employee = $this->getEmployeeById($id);
        if (!$employee) {
            throw new \Exception("Karyawan #{$id} tidak ditemukan.");
        }

        $oldUserId = trim((string) ($employee['User_ID'] ?? ''));
        $userId = array_key_exists('User_ID', $data)
            ? trim((string) $data['User_ID'])
            : $oldUserId;
        if (array_key_exists('User_ID', $data) && $userId === '') {
            throw new RuntimeException('User_ID wajib dipertahankan dan harus merujuk akun yang valid.');
        }
        $userRepo = null;
        $user = null;
        $userTouched = false;
        $employeeTouched = false;
        $oldUserTouched = false;
        $originalUserPhone = null;
        $photoStage = null;
        $phoneProvided = array_key_exists('Phone_Number', $data);
        if (!empty($userId)) {
            $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
            $user = $userRepo->findById($userId);
            if (!$user) {
                throw new \Exception("User tidak ditemukan.");
            }
            if ($this->userHasRole((array) $user, 'STUDENT')) {
                throw new \Exception("Akun siswa tidak dapat digunakan sebagai profil karyawan.");
            }
            $originalUserPhone = $user['Phone_Number'] ?? null;

            $duplicate = collect($this->employeeRepository->fetchAll())
                ->first(function ($row) use ($id, $userId) {
                    return ($row['Employee_ID'] ?? '') !== $id && ($row['User_ID'] ?? '') === $userId;
                });
            if ($duplicate) {
                throw new \Exception("User ini sudah terdaftar sebagai karyawan lain.");
            }

            $contact = $this->contactFromUser((array) $user, $employee);
            $data['Full_Name'] = $contact['Full_Name'];
            $data['Email'] = $contact['Email'];
            $data['Phone_Number'] = $phoneProvided
                ? (string) $data['Phone_Number']
                : $contact['Phone_Number'];
        }

        try {
            if (($data['Profile_Photo'] ?? null) instanceof UploadedFile) {
                $photoStage = $this->stageProfilePhoto($id, $data['Profile_Photo']);
            }

            $payload = $data;
            unset($payload['Profile_Photo']);

            if ($userRepo !== null && $userId !== '') {
                $attributes = [];
                if ($phoneProvided) {
                    $attributes['Phone_Number'] = (string) $data['Phone_Number'];
                }
                $userTouched = true;
                $this->syncUserEmployeeLink($userId, $id, $attributes);
                $this->verifyFreshRow($userRepo, $userId, array_merge($attributes, ['Employee_ID' => $id]), 'user');
            }

            $employeeTouched = true;
            $res = $this->employeeRepository->updateRow($id, $payload);
            if ($res === false || $res === null) {
                throw new RuntimeException('Penyimpanan perubahan karyawan gagal.');
            }

            if ($oldUserId !== '' && $oldUserId !== $userId) {
                $oldUserTouched = true;
                $this->syncUserEmployeeLink($oldUserId, '');
            }

            $this->verifyFreshRow($this->employeeRepository, $id, $payload, 'employee');
            if ($photoStage !== null) {
                $this->commitStagedPhoto($photoStage);
                $photoStage = null;
            }
        } catch (Throwable $e) {
            if ($employeeTouched) {
                try {
                    $rollback = $employee;
                    unset($rollback['Employee_ID']);
                    $this->employeeRepository->updateRow($id, $rollback);
                } catch (Throwable $rollbackError) {
                    Log::error('Employee update compensation failed', ['employee_id' => $id, 'exception' => get_class($rollbackError)]);
                }
            }
            if ($userTouched && $userRepo !== null && $userId !== '') {
                try {
                    $userRollback = ['Employee_ID' => $oldUserId];
                    if ($phoneProvided) {
                        $userRollback['Phone_Number'] = (string) ($originalUserPhone ?? '');
                    }
                    $userRepo->update($userId, $userRollback);
                } catch (Throwable $rollbackError) {
                    Log::error('User link compensation failed', ['user_id' => $userId, 'exception' => get_class($rollbackError)]);
                }
            }
            if ($oldUserTouched && $userRepo !== null && $oldUserId !== '') {
                try { $userRepo->update($oldUserId, ['Employee_ID' => $id]); } catch (Throwable $rollbackError) {
                    Log::error('Previous user link compensation failed', ['user_id' => $oldUserId, 'exception' => get_class($rollbackError)]);
                }
            }
            if ($photoStage !== null) {
                $this->discardStagedPhoto($photoStage);
            }
            Log::error('Employee update integrity failure', ['employee_id' => $id, 'stage' => 'persist_or_verify', 'exception' => get_class($e)]);
            throw $e;
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
            $this->auditPayload($payload)
        );

        return true;
    }

    public function deleteEmployee(string $id): bool
    {
        $res = $this->employeeRepository->delete($id);
        if ($res === false || $res === null) {
            throw new RuntimeException("Penghapusan karyawan {$id} gagal.");
        }
        
        // Clean only this employee's generated profile files; never touch a
        // shared/default asset.
        if (preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            $disk = Storage::disk('public');
            foreach ($disk->files('profiles') as $path) {
                if (preg_match('/^employee_' . preg_quote($id, '/') . '(?:_[A-Za-z0-9-]+)?\.(?:jpe?g|png|webp)$/i', basename($path))) {
                    $disk->delete($path);
                }
            }
        }

        $map = $this->readPhotoMap();
        if (array_key_exists($id, $map)) {
            unset($map[$id]);
            $this->writePhotoMap($map);
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

    private function enrichEmployee(array $employee): array
    {
        $userId = trim((string) ($employee['User_ID'] ?? ''));
        // Only perform a secondary lookup for missing contact cells. This
        // avoids turning every HR index request into an N+1 Google read while
        // still making legacy rows human-readable.
        if ($userId !== '' && (empty($employee['Full_Name']) || empty($employee['Email']) || empty($employee['Phone_Number']))) {
            try {
                $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
                $user = $userRepo->findById($userId);
                if (is_array($user)) {
                    $employee = array_merge($employee, $this->contactFromUser($user, $employee));
                }
            } catch (Throwable $e) {
                Log::warning('Employee contact enrichment failed', ['employee_id' => $employee['Employee_ID'] ?? null, 'exception' => get_class($e)]);
            }
        }

        if (empty($employee['Full_Name']) && $userId !== '') {
            $employee['Full_Name'] = \App\Helpers\UserResolverHelper::getName($userId);
        }
        if (empty($employee['Profile_Photo']) && !empty($employee['Employee_ID'])) {
            $employee['Profile_Photo'] = $this->getProfilePhotoPath((string) $employee['Employee_ID']);
        }

        return $employee;
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

    private function syncUserEmployeeLink(string $userId, string $employeeId, array $attributes = []): void
    {
        if (empty($userId)) {
            return;
        }

        $userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
        $payload = array_merge($attributes, [
            'Employee_ID' => $employeeId,
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
        ]);
        $result = $userRepo->update($userId, $payload);
        if ($result === false || $result === null) {
            throw new RuntimeException("Gagal menyinkronkan User_ID {$userId} ke karyawan {$employeeId}.");
        }
    }

    /**
     * Resolve contact values without replacing a previously known value with
     * an empty cell from MASTER_USER. MASTER_USER remains authoritative when
     * populated; the employee copy is a safe legacy fallback.
     */
    private function contactFromUser(array $user, array $employee = []): array
    {
        $name = trim((string) ($user['Full_Name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($user['Username'] ?? $employee['Full_Name'] ?? ''));
        }
        $email = trim((string) ($user['Email'] ?? ''));
        if ($email === '') {
            $email = trim((string) ($employee['Email'] ?? ''));
        }
        $phone = trim((string) ($user['Phone_Number'] ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($employee['Phone_Number'] ?? ''));
        }

        return [
            'Full_Name' => $name,
            'Email' => $email,
            'Phone_Number' => $phone,
        ];
    }

    private function auditPayload(array $payload): array
    {
        return array_diff_key($payload, array_flip([
            'National_ID', 'Tax_Number', 'Bank_Account_Number', 'Profile_Photo',
        ]));
    }

    /**
     * Concrete Google Sheet repositories expose findByIdFresh; test doubles
     * built against the historical interfaces do not. Use the live read-back
     * whenever available while keeping those doubles backwards compatible.
     */
    private function verifyFreshRow(object $repository, string $id, array $expected, string $label): void
    {
        if (!method_exists($repository, 'findByIdFresh')) {
            return;
        }

        $row = $repository->findByIdFresh($id);
        if (!$row) {
            throw new RuntimeException("Read-back {$label} {$id} tidak ditemukan.");
        }
        foreach ($expected as $key => $value) {
            if ($key === 'Updated_At' || $key === 'Updated_By' || $key === 'Created_At') {
                continue;
            }
            if ((string) ($row[$key] ?? '') !== (string) $value) {
                throw new RuntimeException("Read-back {$label} {$id} tidak sesuai pada {$key}.");
            }
        }
    }

    private function userHasRole(array $user, string $expectedRole): bool
    {
        $roleName = \App\Helpers\UserResolverHelper::getRoleName($user['Role_ID'] ?? '');
        return strtoupper(trim($roleName)) === strtoupper($expectedRole);
    }
}
