<?php

namespace App\Console\Commands;

use App\Services\Attendance\AutomaticStudentAbsenceService;
use Illuminate\Console\Command;

final class MarkAutomaticStudentAbsences extends Command
{
    protected $signature = 'attendance:mark-absent {--date= : Tanggal target YYYY-MM-DD} {--dry-run : Hitung tanpa menulis data}';

    protected $description = 'Catat Alpa siswa yang belum memiliki status setelah seluruh jendela QR ditutup';

    public function handle(AutomaticStudentAbsenceService $service): int
    {
        $result = $service->run(
            date: $this->option('date') ?: null,
            dryRun: (bool) $this->option('dry-run')
        );

        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
