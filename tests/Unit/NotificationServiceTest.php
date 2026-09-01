<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use App\Models\User;
use App\Services\Core\NotificationService;
use App\Exceptions\FinancialIntegrityException;
use Mockery;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = new User();
        $user->User_ID = 'USR001';
        $user->Email = 'user@example.test';
        $user->Role = 'STUDENT';
        $user->resolved_student_id = '';
        $user->resolved_employee_id = '';
        $this->actingAs($user);

        $repo = Mockery::mock(NotificationRepositoryInterface::class);
        $repo->shouldReceive('getById')->with('N2')->andReturn([
            'Notification_ID' => 'N2',
            'User_ID' => 'USR999',
            'Title' => 'Private',
            'Status' => 'Pending',
            'Is_Read' => 'FALSE',
        ]);
        $repo->shouldNotReceive('update');
        $repo->shouldNotReceive('clearCache');

        $service = new NotificationService($repo);

        $this->assertFalse($service->MarkAsRead('N2'));
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = new User();
        $user->User_ID = 'USR001';
        $user->Email = 'user@example.test';
        $user->Role = 'STUDENT';
        $user->resolved_student_id = '';
        $user->resolved_employee_id = '';
        $this->actingAs($user);

        $repo = Mockery::mock(NotificationRepositoryInterface::class);
        $repo->shouldReceive('getById')->with('N1')->andReturn([
            'Notification_ID' => 'N1',
            'User_ID' => 'USR001',
            'Title' => 'Mine',
            'Status' => 'Pending',
            'Is_Read' => 'FALSE',
        ]);
        $repo->shouldReceive('update')->once()->with('N1', Mockery::on(function ($payload) {
            return ($payload['Is_Read'] ?? '') === 'TRUE'
                && ($payload['Status'] ?? '') === 'Read'
                && !empty($payload['Read_At']);
        }))->andReturn(true);
        $repo->shouldReceive('clearCache')->once();

        $service = new NotificationService($repo);

        $this->assertTrue($service->MarkAsRead('N1'));
    }

    public function test_guest_target_notification_is_not_visible_to_authenticated_user(): void
    {
        $user = new User();
        $user->User_ID = 'USR001';
        $user->Email = 'user@example.test';
        $user->Role = 'STUDENT';
        $user->resolved_student_id = '';
        $user->resolved_employee_id = '';
        $this->actingAs($user);

        $repo = Mockery::mock(NotificationRepositoryInterface::class);
        $service = new NotificationService($repo);

        $this->assertFalse($service->isForUser([
            'Notification_ID' => 'N-GUEST',
            'User_ID' => 'guest',
            'Title' => 'Guest only',
        ], $user));
    }

    public function test_all_target_notification_remains_visible_to_authenticated_user(): void
    {
        $user = new User();
        $user->User_ID = 'USR001';
        $user->Email = 'user@example.test';
        $user->Role = 'STUDENT';
        $user->resolved_student_id = '';
        $user->resolved_employee_id = '';
        $this->actingAs($user);

        $repo = Mockery::mock(NotificationRepositoryInterface::class);
        $service = new NotificationService($repo);

        $this->assertTrue($service->isForUser([
            'Notification_ID' => 'N-ALL',
            'User_ID' => 'all',
            'Title' => 'Broadcast',
        ], $user));
    }

    public function test_reminder_deduplication_fails_closed_without_identity_columns(): void
    {
        $repo = Mockery::mock(NotificationRepositoryInterface::class);
        $repo->shouldReceive('getAll')->once()->andReturn(collect([
            ['Notification_ID' => 'N-LEGACY', 'Title' => 'Legacy notification', 'Created_At' => '2026-09-01 09:00:00'],
        ]));
        $service = new NotificationService($repo);
        $this->expectException(FinancialIntegrityException::class);
        $service->hasReminder('INV-1', 7, '2026-09-01');
    }

    public function test_reminder_read_failure_is_not_masqueraded_as_no_reminder(): void
    {
        $repo = Mockery::mock(NotificationRepositoryInterface::class);
        $repo->shouldReceive('getAll')->once()->andThrow(new \RuntimeException('notification read outage'));
        $service = new NotificationService($repo);
        $this->expectException(\RuntimeException::class);
        $service->hasReminder('INV-1', 7, '2026-09-01');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
