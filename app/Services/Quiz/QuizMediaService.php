<?php

namespace App\Services\Quiz;

use App\Interfaces\GoogleSheets\QuizQuestionRepositoryInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuizMediaService
{
    public function __construct(
        private QuizAudioInspector $audioInspector,
        private QuizQuestionRepositoryInterface $questions,
        private ?QuizMediaIdempotencyService $idempotency = null,
        private ?QuizRecordingNormalizer $recordingNormalizer = null,
    ) {}

    /** @return array<string,mixed> */
    public function stage(UploadedFile $file, string $type, string $userId, string $source = 'upload', ?string $requestId = null): array
    {
        $type = strtolower($type);
        abort_unless(in_array($type, ['image', 'audio'], true), 422);
        $source = strtolower(trim($source));
        abort_unless(in_array($source, ['upload', 'recording'], true), 422);
        abort_if($source === 'recording' && $type !== 'audio', 422, 'Sumber rekaman hanya dapat digunakan untuk audio.');
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'Upload media gagal. Silakan pilih file kembali.']);
        }

        $clientExtension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        $size = (int) $file->getSize();
        $extension = $source === 'recording'
            ? $this->validatedRecordingInput($clientExtension, $mime, $size)
            : $this->validatedExtension($type, $clientExtension, $mime, $size);
        $userDirectory = $this->stagingDirectory($userId);
        $quotaLock = Cache::lock('quiz_media_staging_quota_'.hash('sha256', trim($userId)), 30);
        abort_unless($quotaLock->get(), 423, 'Upload lain sedang diproses. Silakan coba kembali.');
        try {
            $this->assertStagingQuota($userDirectory, $size);
            $path = $userDirectory.'/'.Str::uuid().'.'.$extension;
            $stored = $file->storeAs(dirname($path), basename($path), $this->diskName());
            if (! is_string($stored) || $stored !== $path || ! $this->disk()->exists($path)) {
                throw ValidationException::withMessages(['file' => 'Media gagal disimpan. Silakan coba kembali.']);
            }
        } finally {
            $quotaLock->release();
        }

        $originalPath = $path;
        $normalizedPath = null;
        try {
            $metadata = null;
            if ($source === 'recording') {
                $normalizedPath = $userDirectory.'/'.Str::uuid().'.m4a';
                // Production has a small PHP-FPM pool. A fail-fast global
                // lock prevents concurrent transcodes from occupying every
                // worker while each FFmpeg process is already thread-limited.
                $normalizationLock = Cache::lock(
                    'quiz_recording_normalization',
                    (int) ceil((float) config('quiz.media.recording_timeout_seconds', 30)) + 5,
                );
                abort_unless($normalizationLock->get(), 423, 'Rekaman lain sedang diproses. Silakan coba kembali.');
                try {
                    $metadata = ($this->recordingNormalizer ?? app(QuizRecordingNormalizer::class))->normalize(
                        $this->disk()->path($path),
                        $this->disk()->path($normalizedPath),
                        $extension,
                        $mime,
                    );
                } finally {
                    $normalizationLock->release();
                }
                $this->disk()->delete($path);
                $path = $normalizedPath;
                $extension = 'm4a';
                $mime = 'audio/mp4';
            }
            $absolutePath = $this->disk()->path($path);
            $actualSize = (int) $this->disk()->size($path);
            if (($source === 'upload' && $actualSize !== $size) || $actualSize <= 0) {
                throw ValidationException::withMessages(['file' => 'Ukuran media tidak dapat diverifikasi.']);
            }
            if ($source === 'recording') {
                $finalQuotaLock = Cache::lock('quiz_media_staging_quota_'.hash('sha256', trim($userId)), 30);
                abort_unless($finalQuotaLock->get(), 423, 'Upload lain sedang diproses. Silakan coba kembali.');
                try {
                    $this->assertStagingQuota($userDirectory, 0);
                } finally {
                    $finalQuotaLock->release();
                }
            }

            $metadata ??= $type === 'image'
                ? $this->inspectImage($absolutePath, $extension, $mime)
                : $this->audioInspector->inspect($absolutePath, $extension, $mime);
            $token = (string) Str::uuid();
            $ttl = now()->addHours((int) config('quiz.media.staging_ttl_hours', 24));
            $record = [
                'user_id' => trim($userId),
                'type' => $type,
                'path' => $path,
                'size' => $actualSize,
                'mime' => $metadata['mime'],
                'extension' => $metadata['extension'],
                'duration_ms' => $metadata['duration_ms'] ?? null,
                'checksum' => hash_file('sha256', $absolutePath),
                'expires_at' => $ttl->getTimestamp(),
            ];
            Cache::put($this->tokenKey($token), $record, $ttl);

            return [
                'token' => $token,
                'type' => $type,
                'size' => $actualSize,
                'mime' => $record['mime'],
                'duration_ms' => $record['duration_ms'],
            ];
        } catch (\Throwable $exception) {
            $this->disk()->delete($path);
            if ($path !== $originalPath) {
                $this->disk()->delete($originalPath);
            }
            if ($normalizedPath !== null && $normalizedPath !== $path) {
                $this->disk()->delete($normalizedPath);
            }
            Log::warning('Quiz media staging failed.', [
                'request_id' => $requestId,
                'stage' => $source === 'recording' ? 'recording_normalize_or_inspect' : 'upload_inspect',
                'media_type' => $type,
                'source' => $source,
                'extension' => $clientExtension,
                'detected_mime' => $mime,
                'file_size' => $size,
                'reason_code' => $this->failureReason($exception),
                'exception_class' => $exception::class,
            ]);
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function staged(string $token, string $userId, ?string $expectedType = null): array
    {
        $record = Cache::get($this->tokenKey($token));
        abort_unless(is_array($record), 404, 'Media staging tidak ditemukan atau sudah kedaluwarsa.');
        abort_unless(hash_equals((string) $record['user_id'], trim($userId)), 403);
        abort_if((int) ($record['expires_at'] ?? 0) <= now()->getTimestamp(), 410, 'Media staging sudah kedaluwarsa.');
        abort_if($expectedType !== null && ($record['type'] ?? '') !== $expectedType, 422);
        $this->assertQuizPath((string) ($record['path'] ?? ''), true);
        abort_unless($this->disk()->exists($record['path']), 404);

        return $record;
    }

    /**
     * @param  list<array<string,mixed>>  $questions
     * @param  iterable<array<string,mixed>>  $existingQuestions
     * @return array{questions:list<array<string,mixed>>,new_paths:list<string>,old_paths:list<string>,tokens:list<string>,token_uses:list<array{token_hash:string,question_id:string,media_type:string,final_path:string}>,locks:list<Lock>}
     */
    public function prepare(string $quizId, array $questions, iterable $existingQuestions, string $userId): array
    {
        $existing = collect($existingQuestions)->keyBy('Question_ID');
        $plan = ['questions' => [], 'new_paths' => [], 'old_paths' => [], 'tokens' => [], 'token_uses' => [], 'locks' => []];
        $retainedPaths = [];

        try {
            foreach (array_values($questions) as $index => $question) {
                $questionId = $this->questionId($quizId, $index);
                $old = (array) ($existing->get($questionId) ?? []);
                foreach (['image', 'audio'] as $type) {
                    $prefix = ucfirst($type);
                    $token = trim((string) ($question[$prefix.'_Token'] ?? ''));
                    $remove = filter_var($question['Remove_'.$prefix] ?? false, FILTER_VALIDATE_BOOL);
                    if ($token !== '') {
                        $this->idempotency?->assertTokenUnused($token);
                        $lock = Cache::lock('quiz_media_token_'.hash('sha256', $token), 120);
                        abort_unless($lock->get(), 423, 'Media sedang diproses oleh permintaan lain.');
                        $plan['locks'][] = $lock;
                        $record = $this->staged($token, $userId, $type);
                        $pathLock = Cache::lock('quiz_media_path_'.hash('sha256', (string) $record['path']), 120);
                        abort_unless($pathLock->get(), 423, 'Media sedang diproses oleh permintaan lain.');
                        $plan['locks'][] = $pathLock;
                        $absolutePath = $this->disk()->path($record['path']);
                        abort_unless(hash_equals((string) $record['checksum'], (string) hash_file('sha256', $absolutePath)), 409, 'Media staging berubah dan harus diunggah ulang.');
                        $finalPath = $this->finalDirectory($quizId, $questionId).'/'.Str::uuid().'.'.$record['extension'];
                        abort_unless($this->disk()->copy($record['path'], $finalPath), 500, 'Media gagal dipersiapkan.');
                        $plan['new_paths'][] = $finalPath;
                        $plan['tokens'][] = $token;
                        $plan['token_uses'][] = [
                            'token_hash' => $this->idempotency?->tokenHash($token) ?? hash('sha256', trim($token)),
                            'question_id' => $questionId,
                            'media_type' => $type,
                            'final_path' => $finalPath,
                        ];
                        $question[$prefix.'_Path'] = $finalPath;
                        $question[$prefix.'_Mime'] = $record['mime'];
                        $question[$prefix.'_Size_Bytes'] = (int) $record['size'];
                        if ($type === 'audio') {
                            $question['Audio_Duration_Milliseconds'] = (int) $record['duration_ms'];
                        }
                    } elseif ($remove) {
                        $question[$prefix.'_Path'] = null;
                        $question[$prefix.'_Mime'] = null;
                        $question[$prefix.'_Size_Bytes'] = null;
                        if ($type === 'audio') {
                            $question['Audio_Duration_Milliseconds'] = null;
                        }
                    } else {
                        foreach ([$prefix.'_Path', $prefix.'_Mime', $prefix.'_Size_Bytes'] as $field) {
                            $question[$field] = $old[$field] ?? null;
                        }
                        if ($type === 'audio') {
                            $question['Audio_Duration_Milliseconds'] = $old['Audio_Duration_Milliseconds'] ?? null;
                        }
                    }
                    if (! empty($question[$prefix.'_Path'])) {
                        $retainedPaths[] = $question[$prefix.'_Path'];
                    }
                }
                $plan['questions'][] = $question;
            }

            $total = collect($plan['questions'])->sum(fn (array $question): int => (int) ($question['Image_Size_Bytes'] ?? 0) + (int) ($question['Audio_Size_Bytes'] ?? 0)
            );
            if ($total > (int) config('quiz.media.aggregate_max_bytes', 150 * 1024 * 1024)) {
                throw ValidationException::withMessages(['questions' => 'Total media kuis maksimal 150 MB.']);
            }

            $plan['old_paths'] = $existing->flatMap(fn ($row) => [$row['Image_Path'] ?? null, $row['Audio_Path'] ?? null])
                ->filter()->diff($retainedPaths)->unique()->values()->all();

            return $plan;
        } catch (\Throwable $exception) {
            $this->rollback($plan);
            throw $exception;
        }
    }

    /** @param array{new_paths:list<string>,old_paths:list<string>,tokens:list<string>,locks:list<Lock>} $plan */
    public function commit(array $plan): void
    {
        try {
            foreach ($plan['tokens'] as $token) {
                try {
                    $record = Cache::pull($this->tokenKey($token));
                    if (is_array($record) && ! empty($record['path'])) {
                        $this->safeDelete((string) $record['path'], true);
                    }
                } catch (\Throwable $exception) {
                    Log::warning('Quiz staging media cleanup failed after commit.', [
                        'token_hash' => hash('sha256', (string) $token),
                        'exception' => $exception::class,
                    ]);
                }
            }
            $this->deleteUnreferenced($plan['old_paths']);
        } catch (\Throwable $exception) {
            Log::warning('Quiz replaced media cleanup failed after commit.', ['exception' => $exception::class]);
        } finally {
            $this->releaseLocks($plan['locks']);
        }
    }

    /** @param array{new_paths?:list<string>,locks?:list<Lock>} $plan */
    public function rollback(array $plan): void
    {
        try {
            foreach ($plan['new_paths'] ?? [] as $path) {
                try {
                    $this->safeDelete($path, false);
                } catch (\Throwable $exception) {
                    Log::warning('Quiz prepared media rollback cleanup failed.', [
                        'path_hash' => hash('sha256', (string) $path),
                        'exception' => $exception::class,
                    ]);
                }
            }
        } finally {
            $this->releaseLocks($plan['locks'] ?? []);
        }
    }

    /** @param iterable<string> $paths */
    public function deleteUnreferenced(iterable $paths): void
    {
        $paths = collect($paths)->filter()->unique()->values();
        if ($paths->isEmpty()) {
            return;
        }
        $referenced = collect($this->questions->fetchAllFresh())
            ->flatMap(fn ($row) => [$row['Image_Path'] ?? null, $row['Audio_Path'] ?? null])
            ->filter()
            ->mapWithKeys(fn ($path) => [(string) $path => true]);
        foreach ($paths as $path) {
            if ($referenced->has((string) $path)) {
                continue;
            }
            try {
                $this->safeDelete((string) $path, false);
            } catch (\Throwable $exception) {
                Log::warning('Quiz media cleanup failed.', ['path_hash' => hash('sha256', (string) $path), 'exception' => $exception::class]);
            }
        }
    }

    /** @return array{staging:int,final:int} */
    public function cleanupOrphans(iterable $referencedFinalPaths): array
    {
        $root = trim((string) config('quiz.media.root', 'quiz-media'), '/');
        $stagingPrefix = $root.'/staging/';
        $stagingCutoff = now()->subHours((int) config('quiz.media.staging_ttl_hours', 24))->getTimestamp();
        $finalCutoff = now()->subHours((int) config('quiz.media.final_orphan_grace_hours', 48))->getTimestamp();
        $referenced = collect($referencedFinalPaths)->filter()->mapWithKeys(fn ($path) => [(string) $path => true]);
        $summary = ['staging' => 0, 'final' => 0];

        foreach ($this->disk()->allFiles($root) as $path) {
            $staging = str_starts_with($path, $stagingPrefix);
            if ((! $staging && $referenced->has($path)) || (int) $this->disk()->lastModified($path) > ($staging ? $stagingCutoff : $finalCutoff)) {
                continue;
            }
            $lock = Cache::lock('quiz_media_path_'.hash('sha256', $path), 30);
            if (! $lock->get()) {
                continue;
            }
            try {
                if (! $staging && $referenced->has($path)) {
                    continue;
                }
                $this->safeDelete($path, $staging);
                $summary[$staging ? 'staging' : 'final']++;
            } catch (\Throwable $exception) {
                Log::warning('Quiz orphan media cleanup failed.', ['path_hash' => hash('sha256', $path), 'exception' => $exception::class]);
            } finally {
                $lock->release();
            }
        }

        return $summary;
    }

    public function absolutePath(array $media): string
    {
        $path = (string) ($media['path'] ?? '');
        $this->assertQuizPath($path, str_contains($path, '/staging/'));
        abort_unless($this->disk()->exists($path), 404, 'Media tidak ditemukan.');

        return $this->disk()->path($path);
    }

    private function validatedExtension(string $type, string $extension, string $mime, int $size): string
    {
        $map = $type === 'image'
            ? ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']
            : [
                'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mp3', 'audio/x-mpeg'],
                'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/mp4'],
            ];
        $max = (int) config("quiz.media.{$type}_max_bytes");
        if ($size <= 0 || $size > $max) {
            $message = $type === 'image' ? 'Ukuran file gambar maksimal 7 MB.' : 'Ukuran file audio maksimal 5 MB.';
            throw ValidationException::withMessages(['file' => $message]);
        }
        $allowedMime = $map[$extension] ?? null;
        if ($allowedMime === null || ! in_array($mime, (array) $allowedMime, true)) {
            $message = $type === 'image'
                ? 'Format gambar yang didukung adalah JPG, JPEG, PNG, dan WebP.'
                : 'Format audio yang didukung adalah MP3 dan M4A.';
            throw ValidationException::withMessages(['file' => $message]);
        }

        return $extension;
    }

    private function validatedRecordingInput(string $extension, string $mime, int $size): string
    {
        if ($size <= 0 || $size > (int) config('quiz.media.audio_max_bytes', 5 * 1024 * 1024)) {
            throw ValidationException::withMessages(['file' => 'Ukuran rekaman suara maksimal 5 MB.']);
        }
        $allowed = [
            'webm' => ['audio/webm', 'video/webm', 'application/webm'],
            'ogg' => ['audio/ogg', 'application/ogg'],
            'oga' => ['audio/ogg', 'application/ogg'],
            'mp4' => ['audio/mp4', 'video/mp4', 'application/mp4'],
            'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/mp4'],
        ];
        if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension], true)) {
            throw ValidationException::withMessages(['file' => 'Format rekaman browser tidak didukung. Silakan gunakan pilihan file MP3 atau M4A.']);
        }

        return $extension;
    }

    private function failureReason(\Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'durasi') => 'duration_limit',
            str_contains($message, 'ukuran') => 'size_limit',
            str_contains($message, 'format') || str_contains($message, 'codec') => 'format_rejected',
            str_contains($message, 'sementara tidak tersedia') || str_contains($message, 'tidak tersedia di server') => 'processor_unavailable',
            str_contains($message, 'terlalu lama') => 'processor_timeout',
            default => 'media_invalid',
        };
    }

    /** @return array{mime:string,extension:string} */
    private function inspectImage(string $path, string $extension, string $mime): array
    {
        $info = @getimagesize($path);
        if (! is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            throw ValidationException::withMessages(['file' => 'File gambar tidak valid.']);
        }
        if ($info[0] > (int) config('quiz.media.image_max_width', 4096) || $info[1] > (int) config('quiz.media.image_max_height', 4096)) {
            throw ValidationException::withMessages(['file' => 'Dimensi gambar maksimal 4096 x 4096 piksel.']);
        }
        if (strtolower((string) ($info['mime'] ?? '')) !== $mime) {
            throw ValidationException::withMessages(['file' => 'Isi file gambar tidak sesuai dengan formatnya.']);
        }

        $this->assertImageStructure($path, $extension);
        $this->assertImageDecodeBudget((int) $info[0], (int) $info[1], (int) filesize($path));

        $previousHandler = set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });
        $image = null;
        try {
            $image = match ($extension) {
                'jpg', 'jpeg' => imagecreatefromjpeg($path),
                'png' => imagecreatefrompng($path),
                'webp' => imagecreatefromwebp($path),
                default => false,
            };
            if (! $image instanceof \GdImage) {
                throw new \RuntimeException('Image decoder rejected the file.');
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'File gambar rusak atau tidak dapat didekode.']);
        } finally {
            if ($image instanceof \GdImage) {
                imagedestroy($image);
            }
            restore_error_handler();
        }

        return compact('mime', 'extension');
    }

    private function assertImageStructure(string $path, string $extension): void
    {
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw ValidationException::withMessages(['file' => 'File gambar tidak dapat dibaca.']);
        }

        $valid = match ($extension) {
            'jpg', 'jpeg' => str_starts_with($contents, "\xFF\xD8") && strrpos($contents, "\xFF\xD9") !== false,
            'png' => str_starts_with($contents, "\x89PNG\r\n\x1A\n") && str_contains($contents, "IEND\xAE\x42\x60\x82"),
            'webp' => strlen($contents) >= 12
                && substr($contents, 0, 4) === 'RIFF'
                && substr($contents, 8, 4) === 'WEBP'
                && (unpack('Vsize', substr($contents, 4, 4))['size'] ?? PHP_INT_MAX) + 8 <= strlen($contents),
            default => false,
        };

        unset($contents);
        if (! $valid) {
            throw ValidationException::withMessages(['file' => 'File gambar rusak atau terpotong.']);
        }
    }

    private function assertImageDecodeBudget(int $width, int $height, int $fileSize): void
    {
        $limit = $this->bytesFromIni((string) ini_get('memory_limit'));
        if ($limit === null) {
            return;
        }

        $pixelBytes = $width * $height * 4;
        $required = (int) ceil($pixelBytes * 1.25) + $fileSize + (16 * 1024 * 1024);
        $available = max(0, $limit - memory_get_usage(true));
        if ($required > $available) {
            throw ValidationException::withMessages([
                'file' => 'Gambar terlalu besar untuk diperiksa dengan aman pada kapasitas server saat ini.',
            ]);
        }
    }

    private function bytesFromIni(string $value): ?int
    {
        $value = strtolower(trim($value));
        if ($value === '' || $value === '-1') {
            return null;
        }
        if (! preg_match('/^(\d+)([kmgt]?)$/', $value, $matches)) {
            return 0;
        }

        $bytes = (int) $matches[1];
        $power = array_search($matches[2], ['', 'k', 'm', 'g', 't'], true);
        for ($i = 0; $i < $power; $i++) {
            if ($bytes > intdiv(PHP_INT_MAX, 1024)) {
                return PHP_INT_MAX;
            }
            $bytes *= 1024;
        }

        return $bytes;
    }

    private function assertStagingQuota(string $directory, int $incoming): void
    {
        $used = collect($this->disk()->files($directory))->sum(fn (string $path): int => (int) $this->disk()->size($path));
        if ($used + $incoming > (int) config('quiz.media.staging_quota_bytes', 200 * 1024 * 1024)) {
            throw ValidationException::withMessages(['file' => 'Kuota upload sementara Anda telah penuh. Hapus media yang tidak digunakan atau tunggu cleanup.']);
        }
    }

    private function safeDelete(string $path, bool $staging): void
    {
        $this->assertQuizPath($path, $staging);
        $this->disk()->delete($path);
    }

    private function assertQuizPath(string $path, bool $staging): void
    {
        $path = str_replace('\\', '/', trim($path));
        $root = trim((string) config('quiz.media.root', 'quiz-media'), '/');
        $prefix = $root.($staging ? '/staging/' : '/');
        $invalidNamespace = ! $staging && str_starts_with($path, $root.'/staging/');
        abort_if($path === '' || ! str_starts_with($path, $prefix) || $invalidNamespace || str_contains($path, '../') || str_contains($path, "\0"), 400, 'Path media tidak valid.');
    }

    private function stagingDirectory(string $userId): string
    {
        return trim((string) config('quiz.media.root', 'quiz-media'), '/').'/staging/'.hash('sha256', trim($userId));
    }

    private function finalDirectory(string $quizId, string $questionId): string
    {
        return trim((string) config('quiz.media.root', 'quiz-media'), '/').'/'.rawurlencode($quizId).'/'.rawurlencode($questionId);
    }

    private function questionId(string $quizId, int $index): string
    {
        return 'QQN'.strtoupper(substr(hash('sha256', $quizId.'|'.$index), 0, 20));
    }

    private function tokenKey(string $token): string
    {
        abort_unless(Str::isUuid($token), 404);

        return 'quiz_media_stage_'.hash('sha256', $token);
    }

    /** @param iterable<Lock> $locks */
    private function releaseLocks(iterable $locks): void
    {
        foreach ($locks as $lock) {
            try {
                $lock->release();
            } catch (\Throwable) {
            }
        }
    }

    private function diskName(): string
    {
        return (string) config('quiz.media.disk', 'local');
    }

    private function disk()
    {
        return Storage::disk($this->diskName());
    }
}
