<?php

namespace Tests\Unit;

use App\Http\Controllers\Core\NotificationController;
use App\Services\Core\NotificationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class NotificationRedirectSafetyTest extends TestCase
{
    public function test_notification_read_redirect_route_is_post_only(): void
    {
        $route = Route::getRoutes()->getByName('notifications.read');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    public function test_notification_show_does_not_mutate_read_state(): void
    {
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('visibleToCurrentUser')->once()->with('NOTIF-UNREAD')->andReturn([
            'Notification_ID' => 'NOTIF-UNREAD',
            'Is_Read' => 'FALSE',
            'Title' => 'Belum Dibaca',
        ]);
        $notificationService->shouldNotReceive('MarkAsRead');

        $view = (new NotificationController($notificationService))->show('NOTIF-UNREAD');

        $this->assertSame('notifications.show', $view->name());
    }

    public function test_notification_external_action_url_does_not_redirect_offsite(): void
    {
        Config::set('app.url', 'https://wms.example.test');

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('visibleToCurrentUser')->once()->with('NOTIF-EXT')->andReturn([
            'Notification_ID' => 'NOTIF-EXT',
            'Is_Read' => 'TRUE',
            'Action_URL' => 'https://evil.example/phish',
        ]);

        $response = (new NotificationController($notificationService))->readAndRedirect('NOTIF-EXT');

        $this->assertSame(route('notifications.show', 'NOTIF-EXT'), $response->getTargetUrl());
    }

    public function test_notification_internal_action_url_can_redirect(): void
    {
        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('visibleToCurrentUser')->once()->with('NOTIF-INT')->andReturn([
            'Notification_ID' => 'NOTIF-INT',
            'Is_Read' => 'TRUE',
            'Action_URL' => '/dashboard',
        ]);

        $response = (new NotificationController($notificationService))->readAndRedirect('NOTIF-INT');

        $this->assertSame(url('/dashboard'), $response->getTargetUrl());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
