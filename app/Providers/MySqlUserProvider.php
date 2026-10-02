<?php

namespace App\Providers;

use App\Services\Core\UserService;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

class MySqlUserProvider implements UserProvider
{
    public function __construct(protected UserService $userService) {}

    public function retrieveById($identifier)
    {
        $user = $this->userService->getUserById($identifier);

        return $user && $this->isActive($user) ? $this->getGenericUser($user) : null;
    }

    public function retrieveByToken($identifier, $token)
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // WMS intentionally does not persist remember tokens.
    }

    public function retrieveByCredentials(array $credentials)
    {
        if ($credentials === [] || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        $login = trim((string) ($credentials['login'] ?? $credentials['email'] ?? ''));
        $user = $this->userService->getUserByEmail($login)
            ?: $this->userService->getUserByUsername($login);

        return $user && $this->isActive($user) ? $this->getGenericUser($user) : null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return $this->isActive($user)
            && Hash::check((string) ($credentials['password'] ?? ''), $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Password upgrades remain an explicit account-management operation.
    }

    protected function getGenericUser($user): GenericUser
    {
        if (is_object($user) && method_exists($user, 'toArray')) {
            $user = $user->toArray();
        } elseif (is_object($user)) {
            $user = (array) $user;
        }
        if (isset($user['User_ID']) && ! isset($user['id'])) {
            $user['id'] = $user['User_ID'];
        }
        if (isset($user['Password']) && ! isset($user['password'])) {
            $user['password'] = $user['Password'];
        }
        $user['remember_token'] ??= '';

        return new GenericUser($user);
    }

    protected function isActive($user): bool
    {
        if (is_object($user) && isset($user->Is_Active)) {
            $status = strtoupper(trim((string) $user->Is_Active));

            return ! in_array($status, ['FALSE', '0', 'INACTIVE', 'DISABLED'], true);
        }

        if (is_object($user) && method_exists($user, 'toArray')) {
            $user = $user->toArray();
        } elseif (is_object($user)) {
            $user = (array) $user;
        }

        $status = strtoupper(trim((string) ($user['Is_Active'] ?? 'TRUE')));

        return ! in_array($status, ['FALSE', '0', 'INACTIVE', 'DISABLED'], true);
    }
}
