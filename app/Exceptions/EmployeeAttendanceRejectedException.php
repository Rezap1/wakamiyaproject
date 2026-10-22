<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class EmployeeAttendanceRejectedException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        public readonly string $userMessage,
        public readonly int $httpStatus = 422,
        public readonly array $data = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($userMessage, 0, $previous);
    }
}
