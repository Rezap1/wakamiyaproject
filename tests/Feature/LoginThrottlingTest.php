<?php

namespace Tests\Feature;

use App\Services\Core\EnterpriseEventService;
use App\Services\Core\RoleService;
use App\Services\Core\UserService;
use App\Support\LoginRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class LoginThrottlingTest extends TestCase
{
    public function test_login_route_uses_named_login_limiter(): void
    {
        $route = Route::getRoutes()->getByAction('App\\Http\\Controllers\\Core\\AuthController@login');

        $this->assertNotNull($route);
        $this->assertContains('throttle:login', $route->gatherMiddleware());
    }

    public function test_valid_credentials_still_authenticate_and_redirect(): void
    {
        $this->bindAuthenticationDependencies(true);

        $response = $this->postLogin('valid-user@example.test', '198.51.100.10', 'Secret!123');

        $response->assertRedirect(route('dashboard.student'));
        $this->assertTrue(Auth::check());
    }

    public function test_failed_credentials_are_counted_and_sixth_attempt_is_limited(): void
    {
        $this->bindAuthenticationDependencies(false);

        for ($attempt = 1; $attempt <= LoginRateLimiter::IDENTIFIER_MAX_ATTEMPTS; $attempt++) {
            $this->postLogin('brute-force@example.test', '198.51.100.11', 'wrong-password')
                ->assertStatus(302)
                ->assertSessionHasErrors(['login']);
        }

        $this->postLogin('brute-force@example.test', '198.51.100.11', 'wrong-password')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertSessionHasErrors(['login' => 'Terlalu banyak percobaan login. Silakan coba lagi nanti.']);
    }

    public function test_ip_bucket_limits_identifier_rotation(): void
    {
        $this->bindAuthenticationDependencies(false);

        for ($attempt = 1; $attempt <= LoginRateLimiter::IP_MAX_ATTEMPTS; $attempt++) {
            $this->postLogin('rotating-user-'.$attempt.'@example.test', '198.51.100.17', 'wrong-password')
                ->assertStatus(302);
        }

        $this->postLogin('rotating-user-final@example.test', '198.51.100.17', 'wrong-password')
            ->assertStatus(429);
    }

    public function test_identifier_and_ip_buckets_are_isolated(): void
    {
        $this->bindAuthenticationDependencies(false);

        for ($attempt = 1; $attempt <= LoginRateLimiter::IDENTIFIER_MAX_ATTEMPTS; $attempt++) {
            $this->postLogin('first-user@example.test', '198.51.100.12', 'wrong-password');
        }

        $this->postLogin('first-user@example.test', '198.51.100.12', 'wrong-password')
            ->assertStatus(429);

        // A different identifier on the same IP has a separate pair bucket.
        $this->postLogin('second-user@example.test', '198.51.100.12', 'wrong-password')
            ->assertStatus(302);

        // The same identifier from a different IP also has a separate pair bucket.
        $this->postLogin('first-user@example.test', '198.51.100.13', 'wrong-password')
            ->assertStatus(302);
    }

    public function test_successful_login_clears_identifier_failure_bucket(): void
    {
        $this->bindAuthenticationDependencies(true);

        for ($attempt = 1; $attempt < LoginRateLimiter::IDENTIFIER_MAX_ATTEMPTS; $attempt++) {
            $this->postLogin('reset-user@example.test', '198.51.100.14', 'wrong-password')
                ->assertStatus(302);
        }

        $this->postLogin('reset-user@example.test', '198.51.100.14', 'Secret!123')
            ->assertRedirect(route('dashboard.student'));

        Auth::logout();

        // The pair bucket was reset, so five new failures are accepted.
        for ($attempt = 1; $attempt <= LoginRateLimiter::IDENTIFIER_MAX_ATTEMPTS; $attempt++) {
            $this->postLogin('reset-user@example.test', '198.51.100.14', 'wrong-password')
                ->assertStatus(302);
        }
    }

    public function test_limiter_decays_after_its_decay_period(): void
    {
        $request = Request::create('/login', 'POST', ['login' => 'decay@example.test'], [], [], [
            'REMOTE_ADDR' => '198.51.100.15',
        ]);
        $storageKey = md5('login'.LoginRateLimiter::identifierKey($request));

        RateLimiter::hit($storageKey, 1);
        $this->assertTrue(RateLimiter::tooManyAttempts($storageKey, 1));

        sleep(2);

        $this->assertFalse(RateLimiter::tooManyAttempts($storageKey, 1));
    }

    public function test_failure_response_is_generic_for_unknown_identifier(): void
    {
        $this->bindAuthenticationDependencies(false);

        $response = $this->postLogin('unknown-user@example.test', '198.51.100.16', 'wrong-password');

        $response->assertStatus(302)
            ->assertSessionHasErrors(['login' => __('auth.failed')]);
        $this->assertStringNotContainsString('unknown-user@example.test', (string) $response->getContent());
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    private function postLogin(string $login, string $ip, string $password)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login'), [
                'login' => $login,
                'password' => $password,
            ]);
    }

    private function bindAuthenticationDependencies(bool $validCredentials): void
    {
        $user = [
            'User_ID' => 'USR-LOGIN-TEST',
            'Username' => 'login-test',
            'Full_Name' => 'Login Test',
            'Email' => 'valid-user@example.test',
            'Password' => Hash::make('Secret!123'),
            'Role_ID' => 'ROLE-STUDENT',
            'Is_Active' => 'TRUE',
        ];

        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('getUserByEmail')->andReturnUsing(function ($login) use ($user, $validCredentials) {
            return $validCredentials && $login === 'valid-user@example.test' || $validCredentials && $login === 'reset-user@example.test'
                ? $user
                : null;
        });
        $userService->shouldReceive('getUserByUsername')->andReturn(null);
        $this->app->instance(UserService::class, $userService);

        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->zeroOrMoreTimes();
        $this->app->instance(EnterpriseEventService::class, $events);

        if ($validCredentials) {
            $roles = Mockery::mock(RoleService::class);
            $roles->shouldReceive('getRoleById')->andReturn([
                'Role_ID' => 'ROLE-STUDENT',
                'Role_Name' => 'STUDENT',
                'Is_Active' => 'TRUE',
            ]);
            $this->app->instance(RoleService::class, $roles);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
