<?php

namespace Tests\Feature\GoogleSheets;

use Tests\TestCase;
use App\Services\Core\UserService;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Mockery;

class UserServiceTest extends TestCase
{
    protected $userService;
    protected $userRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepositoryMock = Mockery::mock(UserRepositoryInterface::class);
        $this->app->instance(UserRepositoryInterface::class, $this->userRepositoryMock);
        
        $this->userService = $this->app->make(UserService::class);
    }

    public function test_can_create_user_with_generated_id()
    {
        $this->userRepositoryMock->shouldReceive('generateNewId')
            ->once()
            ->with('USR', 6)
            ->andReturn('USR000001');

        $this->userRepositoryMock->shouldReceive('create')
            ->once()
            ->andReturn(true);

        $data = [
            'Username' => 'testuser',
            'Full_Name' => 'Test User',
            'Email' => 'test@wakamiya.co.id',
            'Password' => 'Secret!123',
            'Role_ID' => 'ROL000002',
        ];

        $result = $this->userService->createUser($data);

        $this->assertEquals('USR000001', $result['User_ID']);
        $this->assertEquals('testuser', $result['Username']);
        $this->assertTrue(Hash::check('Secret!123', $result['Password']));
        $this->assertEquals('TRUE', $result['Is_Active']);
    }

    public function test_soft_delete_calls_repository_soft_delete()
    {
        $userId = 'USR000001';
        $this->userRepositoryMock->shouldReceive('softDelete')
            ->once()
            ->with($userId)
            ->andReturn(true);

        $result = $this->userService->deleteUser($userId);
        
        $this->assertTrue($result);
    }

    public function test_update_user_formats_data_correctly()
    {
        $userId = 'USR000001';
        $updateData = [
            'Full_Name' => 'Updated Name',
            'Is_Active' => 'FALSE'
        ];

        $this->userRepositoryMock->shouldReceive('update')
            ->once()
            ->with($userId, Mockery::on(function ($data) {
                return $data['Full_Name'] === 'Updated Name' 
                    && $data['Is_Active'] === 'FALSE' 
                    && isset($data['Updated_At']);
            }))
            ->andReturn(true);

        $result = $this->userService->updateUser($userId, $updateData);
        $this->assertTrue($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
