<?php

namespace Tests\Feature;

use App\Services\Core\RoleService;
use App\Services\Dashboard\AdminDashboardService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    public function test_unknown_role_cannot_fall_through_to_admin_dashboard(): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')
            ->with('ROLE-UNKNOWN')
            ->twice()
            ->andReturn([
                'Role_ID' => 'ROLE-UNKNOWN',
                'Role_Name' => 'Support',
                'Is_Active' => 'TRUE',
            ]);
        $this->app->instance(RoleService::class, $roleService);

        $adminDashboard = Mockery::mock(AdminDashboardService::class);
        $adminDashboard->shouldNotReceive('getDashboardData');
        $this->app->instance(AdminDashboardService::class, $adminDashboard);

        $this->actingAs(new GenericUser([
            'id' => 'USR-UNKNOWN',
            'User_ID' => 'USR-UNKNOWN',
            'Role_ID' => 'ROLE-UNKNOWN',
        ]));

        $this->get('/dashboard')->assertForbidden();
    }

    public function test_known_non_admin_role_redirects_to_its_dashboard(): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')
            ->with('ROLE-TEACHER')
            ->twice()
            ->andReturn([
                'Role_ID' => 'ROLE-TEACHER',
                'Role_Name' => 'TEACHER',
                'Is_Active' => 'TRUE',
            ]);
        $this->app->instance(RoleService::class, $roleService);

        $adminDashboard = Mockery::mock(AdminDashboardService::class);
        $adminDashboard->shouldNotReceive('getDashboardData');
        $this->app->instance(AdminDashboardService::class, $adminDashboard);

        $this->actingAs(new GenericUser([
            'id' => 'USR-TEACHER',
            'User_ID' => 'USR-TEACHER',
            'Role_ID' => 'ROLE-TEACHER',
        ]));

        $this->get('/dashboard')->assertRedirect(route('dashboard.teacher'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
