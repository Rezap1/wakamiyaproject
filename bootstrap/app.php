<?php

use App\Http\Middleware\AgendaAccess;
use App\Http\Middleware\ProtectMassAssignment;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ShareUserRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ShareUserRole::class,
            ProtectMassAssignment::class,
        ]);

        $middleware->alias([
            'agenda.access' => AgendaAccess::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isStudentAttendanceApi = static fn (Request $request): bool => $request->is('attendance/student/scan') && $request->expectsJson();

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isStudentAttendanceApi) {
            if (! $isStudentAttendanceApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'status' => 'rejected',
                'reason_code' => 'AUTHENTICATION_REQUIRED',
                'message' => 'Presensi ditolak: sesi Anda sudah berakhir. Silakan login kembali.',
                'data' => null,
            ], 401);
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) use ($isStudentAttendanceApi) {
            if (! $isStudentAttendanceApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'status' => 'rejected',
                'reason_code' => 'CSRF_TOKEN_INVALID',
                'message' => 'Presensi ditolak: sesi halaman sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
                'data' => null,
            ], 419);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) use ($isStudentAttendanceApi) {
            if (! $isStudentAttendanceApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'status' => 'rejected',
                'reason_code' => 'RATE_LIMITED',
                'message' => 'Presensi ditolak sementara karena terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.',
                'data' => null,
            ], 429, $exception->getHeaders());
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($isStudentAttendanceApi) {
            if (! $isStudentAttendanceApi($request)) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403) {
                return response()->json([
                    'success' => false,
                    'status' => 'rejected',
                    'reason_code' => 'ACCESS_DENIED',
                    'message' => 'Presensi ditolak: akun Anda tidak memiliki akses untuk presensi siswa.',
                    'data' => null,
                ], 403);
            }

            $reportId = 'SQR-'.strtoupper(Str::random(10));
            Log::error('Unexpected student QR attendance middleware failure', [
                'report_id' => $reportId,
                'user_id' => auth()->user()?->User_ID,
                'exception_class' => $exception::class,
                'exception' => $exception,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'reason_code' => 'SYSTEM_ERROR',
                'message' => "Presensi belum dapat diproses karena terjadi gangguan sistem. Silakan coba kembali nanti atau hubungi administrator dengan menyebutkan kode laporan {$reportId}.",
                'report_id' => $reportId,
                'data' => null,
            ], 500);
        });
    })->create();
