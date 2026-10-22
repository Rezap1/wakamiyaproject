<?php

namespace App\Console\Commands;

use App\Services\Attendance\AutomaticStudentAbsenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class MarkAutomaticStudentAbsences extends Command
{
    protected $signature = 'attendance:mark-absent {--date= : Tanggal target YYYY-MM-DD} {--dry-run : Hitung tanpa menulis data}';

    protected $description = 'Catat Alpa siswa yang belum memiliki status setelah seluruh jendela QR ditutup';

    public function handle(AutomaticStudentAbsenceService $service): int
    {
        $runId = 'ALP-'.strtoupper(Str::random(10));

        try {
            $result = $service->run(
                date: $this->option('date') ?: null,
                dryRun: (bool) $this->option('dry-run')
            );
        } catch (Throwable $failure) {
            Log::error('Automatic student absence run failed', [
                'run_id' => $runId,
                'target_date' => $this->option('date') ?: now('Asia/Jakarta')->toDateString(),
                'dry_run' => (bool) $this->option('dry-run'),
                'exception_class' => $failure::class,
            ]);
            $this->error("Proses Alpa otomatis gagal. Kode laporan: {$runId}");

            return self::FAILURE;
        }

        $this->line(json_encode(['run_id' => $runId] + $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
