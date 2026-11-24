<?php

namespace App\Services\Quiz;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class QuizRecordingNormalizer
{
    public function __construct(
        private QuizAudioInspector $audioInspector,
        private QuizBoundedProcessRunner $processRunner,
    ) {}

    /** @return array{mime:string,extension:string,duration_ms:int,codec:string} */
    public function normalize(string $inputPath, string $outputPath, string $extension, string $mime): array
    {
        $input = realpath($inputPath);
        if ($input === false || ! is_file($input)) {
            $this->invalid();
        }

        $extension = strtolower(trim($extension));
        $mime = strtolower(trim($mime));
        $sources = [
            'webm' => ['mimes' => ['audio/webm', 'video/webm', 'application/webm'], 'codecs' => ['opus']],
            'ogg' => ['mimes' => ['audio/ogg', 'application/ogg'], 'codecs' => ['opus']],
            'oga' => ['mimes' => ['audio/ogg', 'application/ogg'], 'codecs' => ['opus']],
            'mp4' => ['mimes' => ['audio/mp4', 'video/mp4', 'application/mp4'], 'codecs' => ['aac']],
            'm4a' => ['mimes' => ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/mp4'], 'codecs' => ['aac']],
        ];
        if (! isset($sources[$extension]) || ! in_array($mime, $sources[$extension]['mimes'], true)) {
            throw ValidationException::withMessages(['file' => 'Format rekaman browser tidak didukung. Silakan gunakan pilihan file MP3 atau M4A.']);
        }

        $probe = $this->probe($input);
        $streams = collect($probe['streams'] ?? []);
        $audio = $streams->where('codec_type', 'audio')->values();
        if ($audio->count() !== 1 || $streams->contains(fn ($stream) => ($stream['codec_type'] ?? '') !== 'audio')) {
            $this->invalid();
        }
        $codec = strtolower((string) data_get($audio->first(), 'codec_name', ''));
        if (! in_array($codec, $sources[$extension]['codecs'], true)) {
            $this->invalid();
        }
        $duration = filter_var(data_get($probe, 'format.duration'), FILTER_VALIDATE_FLOAT);
        if ($duration === false || $duration <= 0) {
            $this->invalid();
        }
        if ($duration > ((int) config('quiz.media.audio_max_duration_ms', 90000) / 1000) + 0.05) {
            throw ValidationException::withMessages(['file' => 'Durasi audio maksimal 1 menit 30 detik.']);
        }

        $ffmpeg = $this->executable((string) config('quiz.media.ffmpeg_path', 'ffmpeg'));
        if ($ffmpeg === null) {
            $this->unavailable();
        }
        $process = new Process([
            $ffmpeg,
            '-nostdin', '-v', 'error', '-xerror',
            '-threads', '1', '-filter_threads', '1',
            '-protocol_whitelist', 'file,pipe',
            '-i', $input,
            '-map', '0:a:0', '-vn', '-sn', '-dn',
            '-c:a', 'aac', '-b:a', '96k',
            '-movflags', '+faststart', '-f', 'ipod',
            $outputPath,
        ]);
        try {
            $result = $this->processRunner->run(
                $process,
                (float) config('quiz.media.recording_timeout_seconds', 30),
                (int) config('quiz.media.ffmpeg_max_output_bytes', 64 * 1024),
            );
        } catch (ProcessTimedOutException) {
            throw new HttpException(504, 'Pemrosesan rekaman terlalu lama. Silakan rekam ulang.');
        } catch (QuizProcessOutputLimitException) {
            $this->invalid();
        } catch (\Throwable) {
            $this->unavailable();
        }
        if ($result['exit_code'] !== 0 || ! is_file($outputPath) || filesize($outputPath) <= 0) {
            $this->invalid();
        }
        if (filesize($outputPath) > (int) config('quiz.media.audio_max_bytes', 5 * 1024 * 1024)) {
            throw ValidationException::withMessages(['file' => 'Ukuran audio hasil rekaman maksimal 5 MB.']);
        }

        return $this->audioInspector->inspect($outputPath, 'm4a', 'audio/mp4');
    }

    /** @return array<string,mixed> */
    private function probe(string $input): array
    {
        $ffprobe = $this->executable((string) config('quiz.media.ffprobe_path', 'ffprobe'));
        if ($ffprobe === null) {
            $this->unavailable();
        }
        $process = new Process([
            $ffprobe, '-v', 'error',
            '-protocol_whitelist', 'file,pipe',
            '-show_entries', 'stream=codec_type,codec_name:format=format_name,duration',
            '-of', 'json', $input,
        ]);
        try {
            $result = $this->processRunner->run(
                $process,
                (float) config('quiz.media.ffprobe_timeout_seconds', 15),
                (int) config('quiz.media.ffprobe_max_output_bytes', 8 * 1024 * 1024),
            );
        } catch (ProcessTimedOutException|QuizProcessOutputLimitException) {
            $this->invalid();
        } catch (\Throwable) {
            $this->unavailable();
        }
        $payload = json_decode($result['stdout'], true);
        if ($result['exit_code'] !== 0 || ! is_array($payload)) {
            $this->invalid();
        }

        return $payload;
    }

    private function executable(string $configured): ?string
    {
        $real = realpath($configured);

        return $real !== false && is_file($real) && ('\\' === DIRECTORY_SEPARATOR || is_executable($real))
            ? $real
            : (new ExecutableFinder)->find($configured);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['file' => 'Rekaman suara rusak atau tidak dapat diproses. Silakan rekam ulang.']);
    }

    private function unavailable(): never
    {
        throw new HttpException(503, 'Layanan pemrosesan audio sementara tidak tersedia.');
    }
}
