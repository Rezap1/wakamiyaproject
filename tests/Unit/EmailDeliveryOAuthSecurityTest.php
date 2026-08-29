<?php

namespace Tests\Unit;

use App\Http\Controllers\Core\EmailDeliveryController;
use App\Services\Core\EmailDeliveryService;
use App\Services\Core\SystemSettingService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Mockery;
use Tests\TestCase;

class EmailDeliveryOAuthSecurityTest extends TestCase
{
    public function test_oauth_connection_fails_closed_when_credentials_are_missing(): void
    {
        Config::set('services.google.oauth_client_id', null);
        Config::set('services.google.oauth_client_secret', null);

        $response = $this->controller()->connectProvider(Request::create('/settings/email/connect/google'), 'google');

        $this->assertSame(route('settings.index', ['tab' => 'Email_Delivery']), $response->getTargetUrl());
        $this->assertFalse(Session::has('oauth_email_state'));
    }

    public function test_oauth_connection_redirects_to_real_provider_with_session_state(): void
    {
        $this->configureGoogleOauth();

        $response = $this->controller()->connectProvider(Request::create('/settings/email/connect/google'), 'google');
        $query = [];
        parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $response->getTargetUrl());
        $this->assertSame('google-client', $query['client_id']);
        $this->assertSame(Session::get('oauth_email_state'), $query['state']);
        $this->assertArrayNotHasKey('code', $query);
    }

    public function test_oauth_callback_rejects_invalid_state_without_contacting_provider(): void
    {
        $this->configureGoogleOauth();
        Session::put('oauth_email_state', 'expected-state');
        Session::put('oauth_email_provider', 'google');
        Http::preventStrayRequests();

        $response = $this->controller()->oauthCallback(
            Request::create('/settings/email/callback/google', 'GET', ['state' => 'forged-state', 'code' => 'forged-code']),
            'google'
        );

        $this->assertSame(route('settings.index', ['tab' => 'Email_Delivery']), $response->getTargetUrl());
        $this->assertFalse(Session::has('oauth_pending_preview'));
        Http::assertNothingSent();
    }

    public function test_oauth_callback_uses_verified_provider_identity(): void
    {
        $this->configureGoogleOauth();
        Config::set('mail.allowed_domain', 'example.test');
        Session::put('oauth_email_state', 'valid-state');
        Session::put('oauth_email_provider', 'google');
        $this->actingAs(new GenericUser([
            'id' => 'USR-ADMIN',
            'User_ID' => 'USR-ADMIN',
            'email' => 'admin@example.test',
        ]));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'provider-access-token',
                'refresh_token' => 'provider-refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'email' => 'mailer@example.test',
                'email_verified' => true,
            ]),
        ]);

        $response = $this->controller(['company' => ['email' => 'company@example.test']])->oauthCallback(
            Request::create('/settings/email/callback/google', 'GET', [
                'state' => 'valid-state',
                'code' => 'provider-authorization-code',
                'selected_email' => 'attacker@example.test',
            ]),
            'google'
        );

        $pending = Session::get('oauth_pending_preview');
        $this->assertSame(route('settings.index', ['tab' => 'Email_Delivery', 'preview' => '1']), $response->getTargetUrl());
        $this->assertSame('mailer@example.test', $pending['account']);
        $this->assertSame('provider-access-token', $pending['access_token']);
        $this->assertNotSame('attacker@example.test', $pending['account']);
    }

    private function configureGoogleOauth(): void
    {
        Config::set('services.google.oauth_client_id', 'google-client');
        Config::set('services.google.oauth_client_secret', 'google-secret');
        Config::set('services.google.oauth_redirect_uri', 'https://wms.example.test/settings/email/callback/google');
    }

    private function controller(array $companyProfile = []): EmailDeliveryController
    {
        $settings = Mockery::mock(SystemSettingService::class);
        if ($companyProfile) {
            $settings->shouldReceive('getCompanyProfile')->once()->andReturn($companyProfile);
        }

        return new EmailDeliveryController($settings, Mockery::mock(EmailDeliveryService::class));
    }

    protected function tearDown(): void
    {
        Session::forget(['oauth_email_state', 'oauth_email_provider', 'oauth_pending_preview']);
        Mockery::close();
        parent::tearDown();
    }
}
