<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class StudentAttendanceRejectedException extends RuntimeException
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $reasonCode,
        public readonly string $userMessage,
        public readonly int $httpStatus = 422,
        public readonly array $data = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($userMessage, 0, $previous);
    }
}
