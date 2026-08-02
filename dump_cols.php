<?php
$classes = app(App\Interfaces\GoogleSheets\ClassRepositoryInterface::class)->fetchAll()->first() ?? [];
$schedules = app(App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class)->fetchAll()->first() ?? [];
print_r(array_keys((array)$classes));
print_r(array_keys((array)$schedules));
