<?php

namespace App\Services\Quiz;

use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class QuizAudioInspector
{
    private ?QuizBoundedProcessRunner $processRunner = null;

    /** @return array{mime:string,extension:string,duration_ms:int,codec:string} */
    public function inspect(string $absolutePath, string $extension, string $mime): array
    {
        $realPath = realpath($absolutePath);
        if ($realPath === false || ! is_file($realPath)) {
            $this->invalid();
        }

        $extension = strtolower($extension);
        $allowed = [
            'mp3' => ['mimes' => ['audio/mpeg'], 'codecs' => ['mp3']],
            'm4a' => ['mimes' => ['audio/mp4', 'audio/x-m4a'], 'codecs' => ['aac']],
        ];
        if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension]['mimes'], true)) {
            throw ValidationException::withMessages(['file' => 'Format audio yang didukung adalah MP3 dan M4A.']);
        }

        $payload = $this->probe($realPath);
        if (! is_array($payload)) {
            $this->invalid();
        }

        $streams = collect($payload['streams'] ?? []);
        if ($streams->contains(fn ($stream) => ($stream['codec_type'] ?? '') === 'video')) {
            throw ValidationException::withMessages(['file' => 'File audio tidak boleh mengandung video stream.']);
        }
        $audioStreams = $streams->filter(fn ($stream) => ($stream['codec_type'] ?? '') === 'audio')->values();
        if ($audioStreams->count() !== 1) {
            $this->invalid();
        }

        $audio = (array) $audioStreams->first();
        $codec = strtolower((string) ($audio['codec_name'] ?? ''));
        if (! in_array($codec, $allowed[$extension]['codecs'], true)) {
            $this->invalid();
        }

        $decodedSamples = $this->decodeEffectiveSamples($realPath);
        $sampleRate = $this->integer($audio['sample_rate'] ?? null);
        if ($sampleRate === null || $sampleRate <= 0) {
            $this->invalid();
        }
        $effectiveSamples = $this->assertPacketTimeline($payload, $audio, $decodedSamples);
        $maximumMilliseconds = (int) config('quiz.media.audio_max_duration_ms', 90000);
        if ($effectiveSamples > intdiv(PHP_INT_MAX, 1000)
            || $maximumMilliseconds > intdiv(PHP_INT_MAX, $sampleRate)
            || ($effectiveSamples * 1000) > ($maximumMilliseconds * $sampleRate)) {
            throw ValidationException::withMessages(['file' => 'Durasi audio maksimal 1 menit 30 detik.']);
        }
        // Ceiling is deliberate for persisted millisecond metadata. The limit
        // itself is enforced above using exact integer samples before conversion.
        $durationMilliseconds = intdiv(($effectiveSamples * 1000) + $sampleRate - 1, $sampleRate);

        return compact('mime', 'extension', 'codec') + ['duration_ms' => $durationMilliseconds];
    }

    /** @return array<string,mixed> */
    protected function probe(string $realPath): array
    {
        $configured = (string) config('quiz.media.ffprobe_path', 'ffprobe');
        $configuredPath = realpath($configured);
        $executable = $configuredPath !== false
            && is_file($configuredPath)
            && ('\\' === DIRECTORY_SEPARATOR || is_executable($configuredPath))
                ? $configuredPath
                : (new ExecutableFinder)->find($configured);
        if ($executable === null) {
            throw ValidationException::withMessages([
                'file' => 'Pemeriksaan durasi audio tidak tersedia di server. Hubungi administrator.',
            ]);
        }
        $process = new Process([
            $executable,
            '-v', 'error',
            '-protocol_whitelist', 'file,pipe',
            '-show_packets',
            '-show_entries', 'stream=index,codec_type,codec_name,sample_rate,time_base,duration_ts:packet=stream_index,pts,duration,side_data_list',
            '-of', 'json',
            $realPath,
        ]);
        try {
            $result = $this->processRunner()->run(
                $process,
                (float) config('quiz.media.ffprobe_timeout_seconds', 15),
                (int) config('quiz.media.ffprobe_max_output_bytes', 8 * 1024 * 1024),
            );
        } catch (ProcessTimedOutException) {
            $this->invalid();
        } catch (QuizProcessOutputLimitException) {
            $this->invalid();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => 'Pemeriksaan durasi audio tidak tersedia di server. Hubungi administrator.',
            ]);
        }
        if ($result['exit_code'] !== 0) {
            $this->invalid();
        }
        $payload = json_decode($result['stdout'], true);
        if (! is_array($payload)) {
            $this->invalid();
        }

        return $payload;
    }

    protected function decodeEffectiveSamples(string $realPath): int
    {
        $executable = $this->resolveExecutable((string) config('quiz.media.ffmpeg_path', 'ffmpeg'));
        if ($executable === null) {
            $this->unavailable();
        }
        $process = new Process([
            $executable,
            '-nostdin',
            '-v', 'info',
            '-xerror',
            '-err_detect', 'explode',
            '-threads', '1',
            '-filter_threads', '1',
            '-protocol_whitelist', 'file,pipe',
            '-i', $realPath,
            '-map', '0:a:0',
            '-vn', '-sn', '-dn',
            '-af', 'asetpts=N/SR/TB,astats=metadata=0:reset=0',
            '-progress', 'pipe:1',
            '-nostats',
            '-f', 'null',
            '-',
        ]);
        try {
            $result = $this->processRunner()->run(
                $process,
                (float) config('quiz.media.ffmpeg_timeout_seconds', 20),
                (int) config('quiz.media.ffmpeg_max_output_bytes', 64 * 1024),
            );
        } catch (ProcessTimedOutException) {
            $this->invalid();
        } catch (QuizProcessOutputLimitException) {
            $this->invalid();
        } catch (\Throwable) {
            $this->unavailable();
        }
        if ($result['exit_code'] !== 0) {
            $this->invalid();
        }
        if (! preg_match('/^out_time_us=\d+$/m', $result['stdout'])
            || ! str_contains($result['stdout'], 'progress=end')
            || ! preg_match_all('/Number of samples:\s*(\d+)/', $result['stderr'], $sampleMatches)) {
            $this->invalid();
        }
        $samples = array_values(array_unique(array_map('intval', $sampleMatches[1])));
        if (count($samples) !== 1 || $samples[0] <= 0) {
            $this->invalid();
        }

        return $samples[0];
    }

    /** @param array<string,mixed> $audio */
    private function assertPacketTimeline(array $payload, array $audio, int $decodedSamples): int
    {
        $streamIndex = $this->integer($audio['index'] ?? null);
        $sampleRate = $this->integer($audio['sample_rate'] ?? null);
        $timeBase = trim((string) ($audio['time_base'] ?? ''));
        if ($streamIndex === null || $sampleRate === null || $sampleRate <= 0
            || ! preg_match('/^(\d+)\/(\d+)$/', $timeBase, $timeBaseParts)) {
            $this->invalid();
        }
        $timeBaseNumerator = (int) $timeBaseParts[1];
        $timeBaseDenominator = (int) $timeBaseParts[2];
        $streamDurationTicks = $this->integer($audio['duration_ts'] ?? null);
        if ($timeBaseNumerator <= 0 || $timeBaseDenominator <= 0
            || $streamDurationTicks === null || $streamDurationTicks <= 0) {
            $this->invalid();
        }

        $firstPts = null;
        $previousEnd = null;
        $skipSamples = 0;
        $discardPadding = 0;
        $packetCount = 0;
        foreach ((array) ($payload['packets'] ?? []) as $packet) {
            if ($this->integer($packet['stream_index'] ?? null) !== $streamIndex) {
                continue;
            }
            $pts = $this->integer($packet['pts'] ?? null);
            $duration = $this->integer($packet['duration'] ?? null);
            if ($pts === null || $duration === null || $duration <= 0
                || ($previousEnd !== null && $pts !== $previousEnd)
                || $pts > PHP_INT_MAX - $duration) {
                $this->invalid();
            }
            $firstPts ??= $pts;
            $previousEnd = $pts + $duration;
            $packetCount++;

            foreach ((array) ($packet['side_data_list'] ?? []) as $sideData) {
                if (($sideData['side_data_type'] ?? '') !== 'Skip Samples') {
                    continue;
                }
                $skip = $this->integer($sideData['skip_samples'] ?? 0);
                $discard = $this->integer($sideData['discard_padding'] ?? 0);
                if ($skip === null || $discard === null || $skip < 0 || $discard < 0
                    || (int) ($sideData['skip_reason'] ?? 0) !== 0
                    || (int) ($sideData['discard_reason'] ?? 0) !== 0
                    || $skipSamples > PHP_INT_MAX - $skip
                    || $discardPadding > PHP_INT_MAX - $discard) {
                    $this->invalid();
                }
                $skipSamples += $skip;
                $discardPadding += $discard;
            }
        }
        if ($packetCount === 0 || $firstPts === null || $previousEnd === null || $previousEnd <= $firstPts) {
            $this->invalid();
        }

        if ($skipSamples > PHP_INT_MAX - $discardPadding) {
            $this->invalid();
        }
        $packetTicks = $previousEnd - $firstPts;
        if ($packetTicks <= 0
            || $packetTicks > intdiv(PHP_INT_MAX, $timeBaseNumerator)
            || ($packetTicks * $timeBaseNumerator) > intdiv(PHP_INT_MAX, $sampleRate)
            || $streamDurationTicks > intdiv(PHP_INT_MAX, $timeBaseNumerator)
            || ($streamDurationTicks * $timeBaseNumerator) > intdiv(PHP_INT_MAX, $sampleRate)) {
            $this->invalid();
        }

        $packetSampleValue = ($packetTicks * $timeBaseNumerator * $sampleRate) / $timeBaseDenominator;
        $streamSampleValue = ($streamDurationTicks * $timeBaseNumerator * $sampleRate) / $timeBaseDenominator;
        // Container time bases can represent a sample boundary as a tiny
        // fraction above an integer. Ceiling keeps the duration check
        // fail-closed instead of rounding an over-limit stream down.
        $roundedPacketSamples = (int) round($packetSampleValue);
        $roundedStreamSamples = (int) round($streamSampleValue);
        $packetSamples = abs($packetSampleValue - $roundedPacketSamples) <= 0.001
            ? $roundedPacketSamples
            : (int) ceil($packetSampleValue);
        $streamSamples = abs($streamSampleValue - $roundedStreamSamples) <= 0.001
            ? $roundedStreamSamples
            : (int) ceil($streamSampleValue);
        if ($skipSamples > $packetSamples
            || $discardPadding > ($packetSamples - $skipSamples)) {
            $this->invalid();
        }

        $effectivePacketSamples = $packetSamples - $skipSamples - $discardPadding;
        $streamMatchesTimeline = abs($streamSamples - $effectivePacketSamples) <= 1
            || abs($streamSamples - $packetSamples) <= 1;
        $decoderPaddingAllowance = match (strtolower((string) ($audio['codec_name'] ?? ''))) {
            'aac' => 1024,
            'mp3' => 1152,
            default => 0,
        };
        if ($effectivePacketSamples <= 0
            || ! $streamMatchesTimeline
            || $decodedSamples < $effectivePacketSamples
            || ($decodedSamples - $effectivePacketSamples) > $decoderPaddingAllowance) {
            $this->invalid();
        }

        return $effectivePacketSamples;
    }

    private function integer(mixed $value): ?int
    {
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }

    private function resolveExecutable(string $configured): ?string
    {
        $configuredPath = realpath($configured);

        return $configuredPath !== false
            && is_file($configuredPath)
            && ('\\' === DIRECTORY_SEPARATOR || is_executable($configuredPath))
                ? $configuredPath
                : (new ExecutableFinder)->find($configured);
    }

    private function processRunner(): QuizBoundedProcessRunner
    {
        return $this->processRunner ??= new QuizBoundedProcessRunner;
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages([
            'file' => 'Pemeriksaan durasi audio tidak tersedia di server. Hubungi administrator.',
        ]);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['file' => 'File audio tidak valid atau durasinya tidak dapat diperiksa.']);
    }
}
