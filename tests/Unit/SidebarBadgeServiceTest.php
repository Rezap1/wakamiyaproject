<?php

namespace Tests\Unit;

use App\Repositories\MySql\SidebarBadgeRepository;
use App\Services\Core\NotificationService;
use App\Services\Core\SidebarBadgeService;
use App\Services\Quiz\QuizPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class SidebarBadgeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_student_payload_reuses_unread_source_and_is_memoized_per_request(): void
    {
        $user = new GenericUser(['id' => 'U-1', 'User_ID' => 'U-1', 'Role' => 'STUDENT']);
        $this->actingAs($user);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('unreadForCurrentUser')->once()->andReturn(collect([
            ['Notification_ID' => 'N-1', 'Link' => '/announcements/A-1'],
            ['Notification_ID' => 'N-2', 'Link' => 'https://wms.test/student/portal/announcements/A-2'],
            ['Notification_ID' => 'N-3', 'Link' => '/student/attendance/requests'],
        ]));
        $repository = Mockery::mock(SidebarBadgeRepository::class);
        $repository->shouldReceive('activeIncompleteRegularQuizzes')
            ->once()
            ->with('U-1', Mockery::type(CarbonImmutable::class))
            ->andReturn(4);
        $repository->shouldNotReceive('teacherAttendanceRequests');
        $repository->shouldNotReceive('paymentsWaitingVerification');
        $repository->shouldNotReceive('pendingApprovals');
        $periods = Mockery::mock(QuizPeriodService::class);
        $periods->shouldReceive('now')->once()->andReturn(CarbonImmutable::parse('2026-11-26 10:00:00', 'Asia/Jakarta'));

        $service = new SidebarBadgeService($notifications, $repository, $periods);
        $first = $service->forUser($user, 'STUDENT');
        $second = $service->forUser($user, 'STUDENT');

        $this->assertSame($first, $second);
        $this->assertSame(2, $first['announcement_activity']);
        $this->assertSame(3, $first['notifications']);
        $this->assertSame(4, $first['regular_quizzes']);
    }

    public function test_teacher_payload_only_queries_teacher_scoped_pending_work(): void
    {
        $user = new GenericUser(['id' => 'U-T', 'User_ID' => 'U-T', 'Role' => 'TEACHER']);
        $this->actingAs($user);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('unreadForCurrentUser')->once()->andReturn(collect());
        $repository = Mockery::mock(SidebarBadgeRepository::class);
        $repository->shouldReceive('teacherAttendanceRequests')->once()->with('U-T')->andReturn(2);
        $repository->shouldNotReceive('activeIncompleteRegularQuizzes');
        $repository->shouldNotReceive('paymentsWaitingVerification');
        $repository->shouldNotReceive('pendingApprovals');
        $periods = Mockery::mock(QuizPeriodService::class);
        $periods->shouldNotReceive('now');

        $counts = (new SidebarBadgeService($notifications, $repository, $periods))->forUser($user, 'TEACHER');

        $this->assertSame(0, $counts['announcement_activity']);
        $this->assertSame(2, $counts['attendance_requests']);
        $this->assertArrayNotHasKey('notifications', $counts);
    }

    public function test_master_skips_unread_notification_scan(): void
    {
        $user = new GenericUser(['id' => 'U-M', 'User_ID' => 'U-M', 'Role' => 'MASTER']);
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldNotReceive('unreadForCurrentUser');
        $repository = Mockery::mock(SidebarBadgeRepository::class);
        $repository->shouldNotReceive('pendingApprovals');
        $repository->shouldReceive('paymentsWaitingVerification')->once()->andReturn(1);
        $periods = Mockery::mock(QuizPeriodService::class);

        $counts = (new SidebarBadgeService($notifications, $repository, $periods))->forUser($user, 'MASTER');

        $this->assertSame(['payments' => 1], $counts);
    }
}
