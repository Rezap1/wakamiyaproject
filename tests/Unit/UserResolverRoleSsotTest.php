<?php

namespace Tests\Unit;

use App\Helpers\UserResolverHelper;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class UserResolverRoleSsotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('all_roles_lookup_map');
    }

    public function test_role_name_comes_from_ssot_instead_of_hardcoded_id_map(): void
    {
        $repo = Mockery::mock(RoleRepositoryInterface::class);
        $repo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Role_ID' => 'ROL000008', 'Role_Name' => 'ACADEMIC', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(RoleRepositoryInterface::class, $repo);

        $this->assertSame('ACADEMIC', UserResolverHelper::getRoleName('ROL000008'));
    }

    public function test_unknown_or_inactive_role_fails_closed(): void
    {
        $repo = Mockery::mock(RoleRepositoryInterface::class);
        $repo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Role_ID' => 'ROL-INACTIVE', 'Role_Name' => 'STUDENT', 'Is_Active' => 'FALSE'],
        ]));
        $this->app->instance(RoleRepositoryInterface::class, $repo);

        $this->assertSame('', UserResolverHelper::getRoleName('ROL-INACTIVE'));
        $this->assertSame('', UserResolverHelper::getRoleName('STUDENT'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
