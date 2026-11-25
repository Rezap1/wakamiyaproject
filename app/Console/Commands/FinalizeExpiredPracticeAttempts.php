<?php

namespace App\Console\Commands;

use App\Services\PracticeQuiz\PracticeQuizService;
use Illuminate\Console\Command;

class FinalizeExpiredPracticeAttempts extends Command
{
    protected $signature = 'practice-quiz:finalize-expired {--limit=100}';

    protected $description = 'Finalisasi attempt kuis latihan yang melewati deadline server';

    public function handle(PracticeQuizService $service): int
    {
        $count = $service->finalizeExpired(max(1, min(1000, (int) $this->option('limit'))));
        $this->info("{$count} attempt difinalisasi.");

        return self::SUCCESS;
    }
}
