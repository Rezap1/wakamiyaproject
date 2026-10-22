<?php

namespace App\Support\Attendance;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class EmployeeAttendanceIncident
{
    private const ATTRIBUTE = 'employee_attendance_report_id';

    public static function report(Request $request, Throwable $exception, string $message): string
    {
        $reportId = self::id($request);
        Log::error($message, [
            'report_id' => $reportId,
            'request_path' => $request->path(),
            'request_method' => $request->method(),
            'user_id' => auth()->user()?->User_ID,
            'exception_class' => $exception::class,
            'exception' => $exception,
        ]);

        return $reportId;
    }

    private static function id(Request $request): string
    {
        $existing = $request->attributes->get(self::ATTRIBUTE);
        if (is_string($existing) && preg_match('/^EQR-[A-Z0-9]{10}$/D', $existing)) {
            return $existing;
        }

        $reportId = 'EQR-'.strtoupper(Str::random(10));
        $request->attributes->set(self::ATTRIBUTE, $reportId);

        return $reportId;
    }
}
