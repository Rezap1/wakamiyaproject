<?php

namespace App\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AcademicMonitoringRepository
{
    /** @return array{batches:Collection,classes:Collection,students:Collection,teachers:Collection,schedules:Collection,subjects:Collection,categories:Collection} */
    public function catalog(): array;

    public function observations(array $filters): Collection;

    public function studentsForClass(string $classId, array $filters): LengthAwarePaginator;
}
