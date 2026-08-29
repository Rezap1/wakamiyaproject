<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Services\Core\UserService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Hash;

class GoogleSheetsUserProvider implements UserProvider
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function retrieveById($identifier)
    {
        $user = $this->userService->getUserById($identifier);
        return $user && $this->isActive($user) ? $this->getGenericUser($user) : null;
    }

    public function retrieveByToken($identifier, $token)
    {
        return null; // Not implemented for basic auth without remember token DB support
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // Not implemented
    }

    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        $loginValue = $credentials['login'] ?? $credentials['email'] ?? '';
        
        // Try fetching by email first
        $user = $this->userService->getUserByEmail($loginValue);
        
        // If not found, try fetching by username
        if (!$user) {
            $user = $this->userService->getUserByUsername($loginValue);
        }

        return $user && $this->isActive($user) ? $this->getGenericUser($user) : null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return $this->isActive($user)
            && Hash::check($credentials['password'], $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        // Not implemented for Google Sheets auth
    }

    protected function getGenericUser($user)
    {
        if (is_object($user) && method_exists($user, 'toArray')) {
            $user = $user->toArray();
        } elseif (is_object($user)) {
            $user = (array) $user;
        }
        if (isset($user['User_ID']) && !isset($user['id'])) {
            $user['id'] = $user['User_ID'];
        }
        if (isset($user['Password']) && !isset($user['password'])) {
            $user['password'] = $user['Password'];
        }
        if (!array_key_exists('remember_token', $user)) {
            $user['remember_token'] = '';
        }
        
        return new GenericUser($user);
    }

    private function isActive($user): bool
    {
        if (is_object($user) && isset($user->Is_Active)) {
            $status = strtoupper(trim((string) $user->Is_Active));

            return !in_array($status, ['FALSE', '0', 'INACTIVE', 'DISABLED'], true);
        }

        if (is_object($user) && method_exists($user, 'toArray')) {
            $user = $user->toArray();
        } elseif (is_object($user)) {
            $user = (array) $user;
        }

        $status = strtoupper(trim((string) ($user['Is_Active'] ?? 'TRUE')));

        return !in_array($status, ['FALSE', '0', 'INACTIVE', 'DISABLED'], true);
    }
}
