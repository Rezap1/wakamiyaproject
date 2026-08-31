<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class LoginRateLimiter
{
    public const IDENTIFIER_MAX_ATTEMPTS = 5;

    public const IP_MAX_ATTEMPTS = 30;

    public const DECAY_MINUTES = 1;

    public static function limits(Request $request): array
    {
        $failedResponse = static function ($response) use ($request): bool {
            return $request->attributes->get('login_failed') === true;
        };

        return [
            Limit::perMinute(self::IDENTIFIER_MAX_ATTEMPTS, self::DECAY_MINUTES)
                ->by(self::identifierKey($request))
                ->after($failedResponse)
                ->response(self::tooManyResponse(...)),
            Limit::perMinute(self::IP_MAX_ATTEMPTS, self::DECAY_MINUTES)
                ->by(self::ipKey($request))
                ->after($failedResponse)
                ->response(self::tooManyResponse(...)),
        ];
    }

    public static function clearIdentifier(Request $request): void
    {
        // Named limiters hash keys in Laravel's ThrottleRequests middleware.
        RateLimiter::clear(self::storageKey(self::identifierKey($request)));
        RateLimiter::clear('login:'.self::identifierKey($request));
    }

    public static function identifierKey(Request $request): string
    {
        $identifier = strtolower(trim((string) $request->input('login', '')));
        $ip = (string) ($request->ip() ?: 'unknown');

        return 'login:identifier-ip:'.hash('sha256', $identifier.'|'.$ip);
    }

    public static function ipKey(Request $request): string
    {
        return 'login:ip:'.hash('sha256', (string) ($request->ip() ?: 'unknown'));
    }

    private static function storageKey(string $key): string
    {
        return md5('login'.$key);
    }

    private static function tooManyResponse(Request $request, array $headers)
    {
        return back()
            ->withErrors(['login' => 'Terlalu banyak percobaan login. Silakan coba lagi nanti.'])
            ->onlyInput('login')
            ->withHeaders($headers)
            ->setStatusCode(429);
    }
}
