<?php

namespace Tests\Unit;

use App\Services\Quiz\QuizBoundedProcessRunner;
use App\Services\Quiz\QuizProcessOutputLimitException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class QuizBoundedProcessRunnerTest extends TestCase
{
    #[DataProvider('overflowStreams')]
    public function test_actual_process_is_stopped_before_completing_excess_output(string $mode): void
    {
        $marker = tempnam(sys_get_temp_dir(), 'quiz-process-marker-');
        @unlink($marker);
        $this->beforeApplicationDestroyed(static fn () => @unlink($marker));
        $script = <<<'PHP'
$mode = $argv[1];
$marker = $argv[2];
for ($i = 0; $i < 10000; $i++) {
    $chunk = str_repeat('x', 4096);
    if ($mode === 'stdout' || $mode === 'combined') { fwrite(STDOUT, $chunk); }
    if ($mode === 'stderr' || $mode === 'combined') { fwrite(STDERR, $chunk); }
    usleep(1000);
}
file_put_contents($marker, 'completed');
PHP;
        $process = new Process([PHP_BINARY, '-n', '-r', $script, $mode, $marker]);

        try {
            (new QuizBoundedProcessRunner)->run($process, 10, 64 * 1024);
            $this->fail('The output limit must stop the subprocess.');
        } catch (QuizProcessOutputLimitException) {
            $this->assertFalse($process->isRunning());
            usleep(100_000);
            $this->assertFileDoesNotExist($marker);
        }
    }

    public static function overflowStreams(): array
    {
        return ['stdout' => ['stdout'], 'stderr' => ['stderr'], 'combined' => ['combined']];
    }

    public function test_actual_timeout_stops_child_process(): void
    {
        $marker = tempnam(sys_get_temp_dir(), 'quiz-timeout-marker-');
        @unlink($marker);
        $this->beforeApplicationDestroyed(static fn () => @unlink($marker));
        $process = new Process([PHP_BINARY, '-n', '-r', 'usleep(1000000); file_put_contents($argv[1], "completed");', $marker]);

        try {
            (new QuizBoundedProcessRunner)->run($process, 0.05, 1024);
            $this->fail('The timeout must stop the subprocess.');
        } catch (ProcessTimedOutException) {
            $this->assertFalse($process->isRunning());
            usleep(100_000);
            $this->assertFileDoesNotExist($marker);
        }
    }

    public function test_bounded_runner_returns_small_stdout_and_stderr(): void
    {
        $result = (new QuizBoundedProcessRunner)->run(new Process([
            PHP_BINARY,
            '-n',
            '-r',
            'fwrite(STDOUT, "ok"); fwrite(STDERR, "warning");',
        ]), 5, 1024);

        $this->assertSame('ok', $result['stdout']);
        $this->assertSame('warning', $result['stderr']);
        $this->assertSame(0, $result['exit_code']);
    }
}
