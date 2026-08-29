<?php

namespace Tests\Unit;

use App\Services\Core\EmailDeliveryService;
use App\Services\Core\SystemSettingService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class EmailDeliveryFailureMessageTest extends TestCase
{
    public function test_mail_transport_details_are_not_returned_to_callers(): void
    {
        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('getEmailDeliveryConfig')->once()->andReturn([
            'provider' => 'smtp',
            'from_address' => 'mailer@example.test',
            'from_name' => 'WMS',
            'reply_to' => 'mailer@example.test',
            'is_healthy' => true,
        ]);
        $settings->shouldReceive('get')->once()->with('EMAIL_CREDENTIAL_DATA', null)->andReturn(null);
        $settings->shouldReceive('get')->once()->with('SET_EMAIL_CREDENTIAL_DATA', null)->andReturn(null);

        Mail::shouldReceive('alwaysFrom')->once()->andReturnNull();
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('smtp password at C:\\secret.json'));

        $result = (new EmailDeliveryService($settings))->sendTestEmail('recipient@example.test');

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('secret.json', $result['message']);
        $this->assertSame(
            'Gagal mengirim email percobaan. Silakan periksa konfigurasi email atau hubungi administrator.',
            $result['message']
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
