<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

final class ActorIdentity
{
    public static function required(): string
    {
        $user = Auth::user();
        $actorId = trim((string) ($user->User_ID ?? Auth::id() ?? ''));

        if ($actorId === '') {
            throw new AuthorizationException('Identitas pengguna terautentikasi tidak dapat dipastikan.');
        }

        return $actorId;
    }

    public static function resolve(?string $explicitActorId = null): string
    {
        $explicitActorId = trim((string) $explicitActorId);

        if (Auth::check()) {
            return self::required();
        }

        if ($explicitActorId === '') {
            return self::required();
        }

        if (strcasecmp($explicitActorId, 'SYSTEM') === 0 && request()->route() !== null) {
            throw new AuthorizationException('Aksi web tanpa identitas pengguna tidak boleh dicatat sebagai SYSTEM.');
        }

        return $explicitActorId;
    }
}
