<?php

namespace App\Services\PracticeQuiz;

use App\Services\Quiz\QuizMediaService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PracticeQuizMediaService
{
    public function __construct(private QuizMediaService $validatedMedia) {}

    public function stage(UploadedFile $file, string $type, string $userId, string $source, ?string $requestId = null): array
    {
        $regular = $this->validatedMedia->stage($file, $type, $userId, $source, $requestId);
        $token = (string) Str::uuid();
        $expires = now()->addHours((int) config('quiz.media.staging_ttl_hours', 24));
        Cache::put($this->tokenKey($token), [
            'regular_token' => $regular['token'],
            'user_id' => trim($userId),
            'type' => $regular['type'],
            'expires_at' => $expires->getTimestamp(),
        ], $expires);

        unset($regular['token']);

        return ['token' => $token] + $regular;
    }

    public function staged(string $token, string $userId, ?string $expectedType = null): array
    {
        $alias = Cache::get($this->tokenKey($token));
        abort_unless(is_array($alias), 404, 'Media latihan tidak ditemukan atau sudah kedaluwarsa.');
        abort_unless(hash_equals((string) $alias['user_id'], trim($userId)), 403);
        abort_if((int) $alias['expires_at'] <= now()->getTimestamp(), 410, 'Media staging sudah kedaluwarsa.');
        abort_if($expectedType !== null && $alias['type'] !== $expectedType, 422);

        return $this->validatedMedia->staged((string) $alias['regular_token'], $userId, $expectedType) + [
            'practice_token' => $token,
            'regular_token' => $alias['regular_token'],
        ];
    }

    public function absolutePath(array $media): string
    {
        if (isset($media['regular_token'])) {
            return $this->validatedMedia->absolutePath($media);
        }
        $path = str_replace('\\', '/', trim((string) ($media['path'] ?? '')));
        $root = trim((string) config('practice_quiz.media_root', 'practice-quiz-media'), '/').'/';
        abort_if($path === '' || ! str_starts_with($path, $root) || str_contains($path, '../') || str_contains($path, "\0"), 400);
        abort_unless($this->disk()->exists($path), 404, 'Media tidak ditemukan.');

        return $this->disk()->path($path);
    }

    /** @param list<array<string,mixed>> $questions */
    public function prepare(string $quizId, string $revisionId, array $questions, iterable $existing, string $userId): array
    {
        $old = collect($existing)->values();
        $plan = ['questions' => [], 'new_paths' => [], 'tokens' => [], 'locks' => []];
        try {
            foreach ($questions as $index => $question) {
                $previous = (array) ($old->get($index) ?? []);
                foreach (['image', 'audio'] as $type) {
                    $prefix = ucfirst($type);
                    $token = trim((string) ($question[$prefix.'_Token'] ?? ''));
                    if ($token !== '') {
                        $lock = Cache::lock('practice_quiz_media_token_'.hash('sha256', $token), 120);
                        abort_unless($lock->get(), 423, 'Media latihan sedang diproses.');
                        $plan['locks'][] = $lock;
                        $record = $this->staged($token, $userId, $type);
                        abort_unless(hash_equals((string) $record['checksum'], (string) hash_file('sha256', $this->validatedMedia->absolutePath($record))), 409);
                        $path = $this->finalDirectory($quizId, $revisionId).'/'.Str::uuid().'.'.$record['extension'];
                        abort_unless($this->disk()->copy($record['path'], $path), 500, 'Media latihan gagal dipersiapkan.');
                        $plan['new_paths'][] = $path;
                        $plan['tokens'][] = ['practice' => $token, 'regular' => $record['regular_token'], 'path' => $record['path']];
                        $question[$prefix.'_Path'] = $path;
                        $question[$prefix.'_Mime'] = $record['mime'];
                        $question[$prefix.'_Size_Bytes'] = $record['size'];
                        if ($type === 'audio') {
                            $question['Audio_Duration_Milliseconds'] = $record['duration_ms'];
                        }
                    } elseif (filter_var($question['Remove_'.$prefix] ?? false, FILTER_VALIDATE_BOOL)) {
                        $question[$prefix.'_Path'] = $question[$prefix.'_Mime'] = $question[$prefix.'_Size_Bytes'] = null;
                        if ($type === 'audio') {
                            $question['Audio_Duration_Milliseconds'] = null;
                        }
                    } else {
                        foreach ([$prefix.'_Path', $prefix.'_Mime', $prefix.'_Size_Bytes'] as $field) {
                            $question[$field] = $previous[$field] ?? null;
                        }
                        if ($type === 'audio') {
                            $question['Audio_Duration_Milliseconds'] = $previous['Audio_Duration_Milliseconds'] ?? null;
                        }
                    }
                }
                $plan['questions'][] = $question;
            }
            $total = collect($plan['questions'])->sum(fn (array $q): int => (int) ($q['Image_Size_Bytes'] ?? 0) + (int) ($q['Audio_Size_Bytes'] ?? 0));
            if ($total > (int) config('quiz.media.aggregate_max_bytes', 157286400)) {
                throw ValidationException::withMessages(['questions' => 'Total media kuis maksimal 150 MB.']);
            }

            return $plan;
        } catch (\Throwable $exception) {
            $this->rollback($plan);
            throw $exception;
        }
    }

    public function commit(array $plan): void
    {
        try {
            foreach ($plan['tokens'] as $token) {
                Cache::forget($this->tokenKey($token['practice']));
                Cache::forget('quiz_media_stage_'.hash('sha256', $token['regular']));
                $this->disk()->delete($token['path']);
            }
        } finally {
            $this->release($plan['locks'] ?? []);
        }
    }

    public function rollback(array $plan): void
    {
        foreach ($plan['new_paths'] ?? [] as $path) {
            $this->disk()->delete($path);
        }
        $this->release($plan['locks'] ?? []);
    }

    private function tokenKey(string $token): string
    {
        abort_unless(Str::isUuid($token), 404);

        return 'practice_quiz_media_stage_'.hash('sha256', $token);
    }

    private function finalDirectory(string $quizId, string $revisionId): string
    {
        return trim((string) config('practice_quiz.media_root', 'practice-quiz-media'), '/').'/'.rawurlencode($quizId).'/'.rawurlencode($revisionId);
    }

    private function disk()
    {
        return Storage::disk((string) config('quiz.media.disk', 'local'));
    }

    /** @param iterable<Lock> $locks */
    private function release(iterable $locks): void
    {
        foreach ($locks as $lock) {
            try {
                $lock->release();
            } catch (\Throwable) {
            }
        }
    }
}
