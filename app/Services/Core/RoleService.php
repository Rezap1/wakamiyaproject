<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\RoleRepositoryInterface;

class RoleService
{
    protected $roleRepository;

    public function __construct(RoleRepositoryInterface $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    public function getAllRoles()
    {
        return $this->rememberRequestLookup('roles_all', function () {
            return $this->roleRepository->fetchAll();
        });
    }

    public function getRoleById($id)
    {
        if (strtoupper($id) === 'MASTER') {
            return [
                'Role_ID' => 'MASTER',
                'Role_Name' => 'MASTER',
                'Is_Active' => 'TRUE'
            ];
        }

        return $this->rememberRequestLookup('role_by_id_' . md5((string) $id), function () use ($id) {
            return $this->roleRepository->findById($id);
        });
    }

    public function createRole(array $data)
    {
        $data['created_at'] = now()->toDateTimeString();
        return $this->roleRepository->create($data);
    }

    private function rememberRequestLookup(string $key, callable $callback)
    {
        if (function_exists('request')) {
            try {
                $request = request();
                if ($request && $request->attributes->has($key)) {
                    return $request->attributes->get($key);
                }

                $value = $callback();
                if ($request) {
                    $request->attributes->set($key, $value);
                }

                return $value;
            } catch (\Throwable) {
                return $callback();
            }
        }

        return $callback();
    }
}
