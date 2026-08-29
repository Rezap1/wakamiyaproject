<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use App\Models\User;
use App\Services\Core\NotificationService;
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
