<?php

namespace App\Console\Commands;

use App\Services\Quiz\QuizCleanupService;
use Illuminate\Console\Command;

class CleanupQuizContent extends Command
{
    protected $signature = 'quiz:cleanup {--limit= : Maximum quiz rows to remove}';

    protected $description = 'Remove eligible closed-period quiz content while retaining canonical result summaries';

    public function handle(QuizCleanupService $cleanup): int
    {
        $summary = $cleanup->run(limit: $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null);
        $this->info(sprintf('Quiz cleanup complete: %d quiz, %d questions, %d attempts, %d expired results finalized.', $summary['quizzes'], $summary['questions'], $summary['attempts'], $summary['results_created']));

        return self::SUCCESS;
    }
}
