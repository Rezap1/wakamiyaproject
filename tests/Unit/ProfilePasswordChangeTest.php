<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\UserService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ProfilePasswordChangeTest extends TestCase
{
    public function test_change_password_verifies_authoritative_hash_and_persists_new_hash(): void
    {
        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('findById')->once()->with('USR-A')->andReturn([
            'User_ID' => 'USR-A', 'Password' => Hash::make('OldPassword!1'),
        ]);
        $repo->shouldReceive('update')->once()->with('USR-A', Mockery::on(function (array $data) {
            return isset($data['Password'])
                && Hash::check('NewPassword!2', $data['Password'])
                && $data['Password'] !== 'NewPassword!2'
                && isset($data['Last_Password_Change']);
        }))->andReturn(true);

        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->once()->with(
            'USER', 'UPDATE', 'USER', 'USR-A', 'USR-A', ['ADMINISTRATOR'], [], ['Password_Changed' => true]
        )->andReturn(true);

        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));
        $service = new UserService($repo, $events);

        $this->assertTrue($service->changePassword('USR-A', 'OldPassword!1', 'NewPassword!2'));
    }

    public function test_change_password_rejects_wrong_current_password_without_writing(): void
    {
        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('findById')->once()->andReturn([
            'User_ID' => 'USR-A', 'Password' => Hash::make('OldPassword!1'),
        ]);
        $repo->shouldNotReceive('update');
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldNotReceive('dispatch');

        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));
        $service = new UserService($repo, $events);

        $this->assertFalse($service->changePassword('USR-A', 'wrong', 'NewPassword!2'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
