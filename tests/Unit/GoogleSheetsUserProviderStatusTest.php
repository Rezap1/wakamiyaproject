<?php

namespace Tests\Unit;

use App\Providers\GoogleSheetsUserProvider;
use App\Services\Core\UserService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class GoogleSheetsUserProviderStatusTest extends TestCase
{
    public function test_inactive_user_is_rejected_for_login_and_session_restore(): void
    {
        $record = [
            'User_ID' => 'USR-INACTIVE',
            'Email' => 'inactive@example.test',
            'Password' => Hash::make('Secret!123'),
            'Is_Active' => 'FALSE',
        ];
        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('getUserByEmail')->once()->andReturn($record);
        $service->shouldReceive('getUserById')->once()->andReturn($record);
        $provider = new GoogleSheetsUserProvider($service);

        $this->assertNull($provider->retrieveByCredentials([
            'email' => $record['Email'],
            'password' => 'Secret!123',
        ]));
        $this->assertNull($provider->retrieveById($record['User_ID']));
        $this->assertFalse($provider->validateCredentials(new GenericUser([
            'id' => $record['User_ID'],
            'password' => $record['Password'],
            'Is_Active' => 'FALSE',
        ]), ['password' => 'Secret!123']));
    }

    public function test_active_user_with_valid_password_is_accepted(): void
    {
        $record = [
            'User_ID' => 'USR-ACTIVE',
            'Email' => 'active@example.test',
            'Password' => Hash::make('Secret!123'),
            'Is_Active' => 'TRUE',
        ];
        $service = Mockery::mock(UserService::class);
        $service->shouldReceive('getUserByEmail')->once()->andReturn($record);
        $provider = new GoogleSheetsUserProvider($service);

        $user = $provider->retrieveByCredentials([
            'email' => $record['Email'],
            'password' => 'Secret!123',
        ]);

        $this->assertNotNull($user);
        $this->assertSame('USR-ACTIVE', $user->getAuthIdentifier());
        $this->assertTrue($provider->validateCredentials($user, ['password' => 'Secret!123']));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
