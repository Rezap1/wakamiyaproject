<?php

namespace App\Services\Core;

use App\Repositories\MySql\SidebarBadgeRepository;
use App\Services\Quiz\QuizPeriodService;
use Illuminate\Support\Collection;

class SidebarBadgeService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly SidebarBadgeRepository $badges,
        private readonly QuizPeriodService $quizPeriods
    ) {}

    /** @return array<string,int> */
    public function forUser($user, string $role): array
    {
        if (! $user) {
            return [];
        }

        $role = strtoupper(trim($role));
        $userId = trim((string) ($user->User_ID ?? $user->id ?? ''));
        $requestKey = 'wms.sidebar_badges.'.hash('sha256', $userId.'|'.$role);

        if (function_exists('request') && request()->attributes->has($requestKey)) {
            return (array) request()->attributes->get($requestKey);
        }

        $counts = [];
        $unread = collect();

        if ($role !== 'MASTER') {
            $unread = $this->notifications->unreadForCurrentUser();
            $counts['announcement_activity'] = $this->announcementActivityCount($unread);
        }
        if (in_array($role, ['MARKETING', 'STUDENT', 'EMPLOYEE'], true)) {
            $counts['notifications'] = $unread->count();
        }
        if ($role === 'ADMINISTRATOR') {
            $counts['approvals'] = $this->badges->pendingApprovals($role);
        }
        if (in_array($role, ['FINANCE', 'MASTER'], true)) {
            $counts['payments'] = $this->badges->paymentsWaitingVerification();
        }
        if ($role === 'TEACHER' && $userId !== '') {
            $counts['attendance_requests'] = $this->badges->teacherAttendanceRequests($userId);
        }
        if ($role === 'STUDENT' && $userId !== '') {
            $counts['regular_quizzes'] = $this->badges->activeIncompleteRegularQuizzes(
                $userId,
                $this->quizPeriods->now()
            );
        }

        $counts = collect($counts)
            ->map(fn ($count) => max(0, (int) $count))
            ->all();

        if (function_exists('request')) {
            request()->attributes->set($requestKey, $counts);
        }

        return $counts;
    }

    private function announcementActivityCount(Collection $unread): int
    {
        return $unread->filter(function ($notification): bool {
            $link = trim((string) ($notification['Link'] ?? $notification['Action_URL'] ?? ''));
            if ($link === '') {
                return false;
            }

            $path = strtolower((string) (parse_url($link, PHP_URL_PATH) ?: $link));

            return preg_match('#^/(announcements|student/portal/announcements)(/|$)#', $path) === 1;
        })->count();
    }
}
