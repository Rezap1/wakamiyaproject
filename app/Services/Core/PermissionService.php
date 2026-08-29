<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\PermissionRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;
use Exception;

class PermissionService
{
    protected $permissionRepository;
    protected $roleRepository;
    protected $moduleRepository;

    public function __construct(
        PermissionRepositoryInterface $permissionRepository,
        RoleRepositoryInterface $roleRepository,
        ModuleRepositoryInterface $moduleRepository
    ) {
        $this->permissionRepository = $permissionRepository;
        $this->roleRepository = $roleRepository;
        $this->moduleRepository = $moduleRepository;
    }

    public function getAllPermissions()
    {
        return $this->permissionRepository->fetchAll();
    }

    public function getPermissionById($id)
    {
        return $this->permissionRepository->findById($id);
    }
    
    public function getAllRoles()
    {
        return $this->roleRepository->fetchAll();
    }
    
    public function getAllModules()
    {
        return $this->moduleRepository->fetchAll();
    }

    public function createPermission(array $data)
    {
        $createdPermissions = [];
        $modules = is_array($data['Module_ID']) ? $data['Module_ID'] : [$data['Module_ID']];

        foreach ($modules as $moduleId) {
            // Validasi Duplikasi: 1 Role dan 1 Modul hanya boleh punya 1 baris konfigurasi
            $existing = $this->permissionRepository->findByRoleAndModule($data['Role_ID'], $moduleId);
            
            if ($existing) {
                throw new Exception("Konfigurasi Hak Akses untuk Role {$data['Role_ID']} dan Modul {$moduleId} sudah ada.");
            }

            $newId = $this->permissionRepository->generateNewId('PRM', 6);

            $mappedData = [
                'Permission_ID' => $newId,
                'Role_ID' => $data['Role_ID'],
                'Module_ID' => $moduleId,
                'Can_View' => !empty($data['Can_View']) ? 'TRUE' : 'FALSE',
                'Can_Create' => !empty($data['Can_Create']) ? 'TRUE' : 'FALSE',
                'Can_Edit' => !empty($data['Can_Edit']) ? 'TRUE' : 'FALSE',
                'Can_Delete' => !empty($data['Can_Delete']) ? 'TRUE' : 'FALSE',
                'Can_Print' => !empty($data['Can_Print']) ? 'TRUE' : 'FALSE',
                'Can_Export_PDF' => !empty($data['Can_Export_PDF']) ? 'TRUE' : 'FALSE',
                'Is_Active' => $data['Is_Active'] ?? 'TRUE',
                'Created_At' => now()->toDateTimeString(),
                'Updated_At' => now()->toDateTimeString(),
                'Created_By' => \App\Support\ActorIdentity::required(),
                'Updated_By' => \App\Support\ActorIdentity::required(),
                'Notes' => $data['Notes'] ?? ''
            ];

            $this->permissionRepository->create($mappedData);
            $createdPermissions[] = $mappedData;
        }
        
        return $createdPermissions;
    }
    
    public function updatePermission($id, array $data)
    {
        $permission = $this->getPermissionById($id);
        if (!$permission) {
            throw new Exception("Data Hak Akses tidak ditemukan.");
        }

        // Validate uniqueness if changing Role or Module
        if ((isset($data['Role_ID']) && $data['Role_ID'] !== $permission['Role_ID']) || 
            (isset($data['Module_ID']) && $data['Module_ID'] !== $permission['Module_ID'])) {
            
            $checkRoleId = $data['Role_ID'] ?? $permission['Role_ID'];
            $checkModuleId = $data['Module_ID'] ?? $permission['Module_ID'];
            
            $existing = $this->permissionRepository->findByRoleAndModule($checkRoleId, $checkModuleId);
            if ($existing && $existing['Permission_ID'] !== $id) {
                throw new Exception("Konfigurasi Hak Akses untuk Role dan Modul tersebut sudah terdaftar pada ID lain.");
            }
        }

        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
        ];

        if (array_key_exists('Role_ID', $data)) $mappedData['Role_ID'] = $data['Role_ID'];
        if (array_key_exists('Module_ID', $data)) $mappedData['Module_ID'] = $data['Module_ID'];
        if (array_key_exists('Can_View', $data)) $mappedData['Can_View'] = !empty($data['Can_View']) ? 'TRUE' : 'FALSE';
        if (array_key_exists('Can_Create', $data)) $mappedData['Can_Create'] = !empty($data['Can_Create']) ? 'TRUE' : 'FALSE';
        if (array_key_exists('Can_Edit', $data)) $mappedData['Can_Edit'] = !empty($data['Can_Edit']) ? 'TRUE' : 'FALSE';
        if (array_key_exists('Can_Delete', $data)) $mappedData['Can_Delete'] = !empty($data['Can_Delete']) ? 'TRUE' : 'FALSE';
        if (array_key_exists('Can_Print', $data)) $mappedData['Can_Print'] = !empty($data['Can_Print']) ? 'TRUE' : 'FALSE';
        if (array_key_exists('Can_Export_PDF', $data)) $mappedData['Can_Export_PDF'] = !empty($data['Can_Export_PDF']) ? 'TRUE' : 'FALSE';
        if (array_key_exists('Is_Active', $data)) $mappedData['Is_Active'] = $data['Is_Active'];
        if (array_key_exists('Notes', $data)) $mappedData['Notes'] = $data['Notes'];

        return $this->permissionRepository->update($id, $mappedData);
    }

    public function deletePermission($id)
    {
        return $this->permissionRepository->delete($id);
    }
}
