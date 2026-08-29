<?php

namespace App\Helpers;

use Illuminate\Support\Facades\URL;

class PublicVerificationUrl
{
    public const EXPIRY_DAYS = 30;

    public static function make(string $routeName, string $documentId): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            now()->addDays(self::EXPIRY_DAYS),
            ['id' => $documentId]
        );
    }
}
