<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Report the real exception while keeping infrastructure details out of
     * production responses. Local/testing environments retain the original
     * message so developers can diagnose failures without weakening the
     * production boundary.
     */
    protected function safeExceptionMessage(
        \Throwable $exception,
        string $fallback = 'Permintaan tidak dapat diproses. Silakan coba kembali atau hubungi administrator.'
    ): string {
        report($exception);

        if (!app()->environment('production') && config('app.debug')) {
            return $exception->getMessage();
        }

        return $fallback;
    }
}
