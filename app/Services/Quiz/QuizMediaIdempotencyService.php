<?php

namespace App\Services\Quiz;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuizMediaIdempotencyService
{
    public function replayResult(
        string $key,
        string $userId,
        string $operation,
        ?string $targetQuizId,
        string $payloadHash,
    ): ?string {
        $row = $this->connection()->table('QUIZ_MEDIA_OPERATION')
            ->where('Idempotency_Key', $key)
            ->first();
        if ($row === null) {
            return null;
        }

        $stored = (array) $row;
        $matches = hash_equals((string) ($stored['User_ID'] ?? ''), $userId)
            && hash_equals((string) ($stored['Operation_Type'] ?? ''), $operation)
            && hash_equals((string) ($stored['Target_Quiz_ID'] ?? ''), (string) $targetQuizId)
            && hash_equals((string) ($stored['Payload_Hash'] ?? ''), $payloadHash);
        if (! $matches) {
            throw new HttpException(409, 'Kunci idempotensi kuis sudah digunakan untuk operasi atau payload yang berbeda.');
        }

        return (string) $stored['Result_Quiz_ID'];
    }

    public function assertTokenUnused(string $token): void
    {
        if ($this->connection()->table('QUIZ_MEDIA_TOKEN_USE')
            ->where('Token_Hash', $this->tokenHash($token))
            ->exists()) {
            throw new HttpException(409, 'Token media sudah digunakan oleh operasi kuis yang berhasil.');
        }
    }

    /** @param list<array{token_hash:string,question_id:string,media_type:string,final_path:string}> $tokenUses */
    public function recordCommitted(
        string $key,
        string $userId,
        string $operation,
        ?string $targetQuizId,
        string $payloadHash,
        string $resultQuizId,
        array $tokenUses,
        string $committedAt,
    ): void {
        try {
            $this->connection()->transaction(function () use ($key, $userId, $operation, $targetQuizId, $payloadHash, $resultQuizId, $tokenUses, $committedAt): void {
                $this->connection()->table('QUIZ_MEDIA_OPERATION')->insert([
                    'Idempotency_Key' => $key,
                    'User_ID' => $userId,
                    'Operation_Type' => $operation,
                    'Target_Quiz_ID' => $targetQuizId,
                    'Payload_Hash' => $payloadHash,
                    'Result_Quiz_ID' => $resultQuizId,
                    'Committed_At' => $committedAt,
                ]);

                foreach ($tokenUses as $use) {
                    $this->connection()->table('QUIZ_MEDIA_TOKEN_USE')->insert([
                        'Token_Hash' => $use['token_hash'],
                        'Idempotency_Key' => $key,
                        'User_ID' => $userId,
                        'Quiz_ID' => $resultQuizId,
                        'Question_ID' => $use['question_id'],
                        'Media_Type' => $use['media_type'],
                        'Final_Path' => $use['final_path'],
                        'Committed_At' => $committedAt,
                    ]);
                }
            }, 5);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new HttpException(409, 'Token media atau kunci idempotensi telah digunakan oleh operasi lain.', $exception);
            }

            throw $exception;
        }
    }

    public function tokenHash(string $token): string
    {
        return hash('sha256', trim($token));
    }

    private function connection()
    {
        return DB::connection((string) config('wms_runtime.connection'));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($state, ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}
