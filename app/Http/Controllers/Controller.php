<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class Controller
{
    private const DEFAULT_SAFE_MESSAGE = 'Permintaan tidak dapat diproses. Silakan coba kembali atau hubungi administrator.';

    /**
     * Report the real exception while keeping infrastructure details out of
     * production responses. Local/testing environments retain the original
     * message so developers can diagnose failures without weakening the
     * production boundary.
     */
    protected function safeExceptionMessage(
        \Throwable $exception,
        string $fallback = self::DEFAULT_SAFE_MESSAGE
    ): string {
        $reportId = null;
        if (app()->bound('request')) {
            $reportId = request()->attributes->get('wms_report_id');
        }
        $reportId = is_string($reportId) && $reportId !== ''
            ? $reportId
            : 'WMS-'.Str::upper(Str::random(10));
        if (app()->bound('request')) {
            request()->attributes->set('wms_report_id', $reportId);
        }

        try {
            Log::error('Handled application failure', [
                'report_id' => $reportId,
                'request_path' => app()->bound('request') ? request()->path() : null,
                'request_method' => app()->bound('request') ? request()->method() : null,
                'user_id' => auth()->user()->User_ID ?? auth()->id(),
                'exception_class' => $exception::class,
                'exception' => $exception,
            ]);
        } catch (\Throwable) {
            report($exception);
        }

        if (! app()->environment('production') && config('app.debug')) {
            return $exception->getMessage();
        }

        return $fallback === self::DEFAULT_SAFE_MESSAGE
            ? "{$fallback} Kode laporan: {$reportId}."
            : $fallback;
    }
}
