<?php

namespace App\Services\Attendance;

use RuntimeException;

class AttendanceWindowException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }
}
