<?php

namespace App\Policies;

use App\Models\Practice\PracticeQuiz;

class PracticeQuizPolicy
{
    public function viewAny(object $user, string $teacherId): bool
    {
        return $this->hasActiveTeacherIdentity($user, $teacherId);
    }

    public function create(object $user, string $teacherId): bool
    {
        return $this->hasActiveTeacherIdentity($user, $teacherId);
    }

    public function view(object $user, PracticeQuiz $quiz, string $teacherId): bool
    {
        return $this->owns($user, $quiz, $teacherId);
    }

    public function update(object $user, PracticeQuiz $quiz, string $teacherId): bool
    {
        return $this->owns($user, $quiz, $teacherId);
    }

    public function delete(object $user, PracticeQuiz $quiz, string $teacherId): bool
    {
        return $this->owns($user, $quiz, $teacherId);
    }

    private function owns(object $user, PracticeQuiz $quiz, string $teacherId): bool
    {
        return $this->hasActiveTeacherIdentity($user, $teacherId)
            && hash_equals(trim((string) $quiz->Teacher_ID), trim($teacherId));
    }

    private function hasActiveTeacherIdentity(object $user, string $teacherId): bool
    {
        return trim((string) ($user->User_ID ?? $user->getAuthIdentifier() ?? '')) !== ''
            && trim($teacherId) !== '';
    }
}
