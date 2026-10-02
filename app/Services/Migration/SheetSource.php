<?php

namespace App\Services\Migration;

interface SheetSource
{
    /**
     * @return array{headers: list<string>, rows: list<array{source_row: int, values: array<string, mixed>}>}
     */
    public function read(string $sheet): array;
}
