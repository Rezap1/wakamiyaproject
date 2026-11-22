<?php

namespace App\Services\Quiz;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class QuizBoundedProcessRunner
{
    /** @return array{stdout:string,stderr:string,exit_code:int} */
    public function run(Process $process, float $timeoutSeconds, int $outputLimitBytes): array
    {
        if ($timeoutSeconds <= 0 || $outputLimitBytes <= 0) {
            throw new \InvalidArgumentException('Process limits must be positive.');
        }

        // Timeouts are enforced here instead of delegated to Process::run(),
        // allowing the same force-stop path to cover timeout and overflow.
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $stdout = '';
        $stderr = '';
        $receivedBytes = 0;
        $outputExceeded = false;
        $startedAt = microtime(true);
        $lastOutputAt = $startedAt;

        try {
            $process->start(function (string $type, string $chunk) use (
                $process,
                $outputLimitBytes,
                &$stdout,
                &$stderr,
                &$receivedBytes,
                &$outputExceeded,
                &$lastOutputAt,
            ): void {
                // Symfony writes each chunk into its own internal temporary
                // stream before invoking this callback. Clear that stream on
                // every callback so only this explicitly bounded copy remains.
                if ($type === Process::OUT) {
                    $process->clearOutput();
                } else {
                    $process->clearErrorOutput();
                }

                if ($outputExceeded) {
                    return;
                }

                $chunkBytes = strlen($chunk);
                if ($chunkBytes > $outputLimitBytes - $receivedBytes) {
                    $outputExceeded = true;
                    throw new QuizProcessOutputLimitException('Subprocess output limit exceeded.');
                }
                $receivedBytes += $chunkBytes;
                $lastOutputAt = microtime(true);
                if ($type === Process::OUT) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }
            });

            while ($process->isRunning()) {
                $now = microtime(true);
                if ($now - $startedAt > $timeoutSeconds || $now - $lastOutputAt > $timeoutSeconds) {
                    $this->forceStop($process);
                    throw new ProcessTimedOutException($process, ProcessTimedOutException::TYPE_GENERAL);
                }
                usleep(10_000);
            }
            $exitCode = $process->getExitCode() ?? -1;
        } finally {
            if ($process->isRunning()) {
                $this->forceStop($process);
            }
        }

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => $exitCode];
    }

    private function forceStop(Process $process): void
    {
        // proc_terminate targets the process resource directly. This avoids a
        // Windows taskkill race observed when Process::stop() is called while
        // its output callback is unwinding.
        try {
            $reflection = new \ReflectionProperty(Process::class, 'process');
            $resource = $reflection->getValue($process);
            if (is_resource($resource)) {
                @proc_terminate($resource, 9);
            }
        } catch (\Throwable) {
            // Symfony's own tree-aware stop remains the portable fallback.
        }

        $process->stop(0.05, 9);
    }
}
