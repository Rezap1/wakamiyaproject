<?php

namespace Tests\Feature;

use App\Services\Core\RoleService;
use App\Support\ActorIdentity;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class MasterAccountAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Define some dummy protected routes for testing capability explicitly
        Route::get('/_test/admin-only', function () { return 'admin-ok'; })->middleware(['web', 'auth', 'role:ADMINISTRATOR']);
        Route::get('/_test/hr-only', function () { return 'hr-ok'; })->middleware(['web', 'auth', 'role:HR']);
        Route::get('/_test/academic-only', function () { return 'academic-ok'; })->middleware(['web', 'auth', 'role:ACADEMIC']);
        Route::get('/_test/finance-only', function () { return 'finance-ok'; })->middleware(['web', 'auth', 'role:FINANCE']);
        Route::get('/_test/marketing-only', function () { return 'marketing-ok'; })->middleware(['web', 'auth', 'role:MARKETING']);
        Route::get('/_test/teacher-only', function () { return 'teacher-ok'; })->middleware(['web', 'auth', 'role:TEACHER']);
        Route::get('/_test/student-only', function () { return 'student-ok'; })->middleware(['web', 'auth', 'role:STUDENT']);
        Route::get('/_test/director-only', function () { return 'director-ok'; })->middleware(['web', 'auth', 'role:DIRECTOR']);
        Route::get('/_test/teacher-admin', function () { return 'teacher-admin-ok'; })->middleware(['web', 'auth', 'role:ADMINISTRATOR,TEACHER']);
    }

    private function actingAsRole(string $roleName, bool $isActive = true)
    {
        $roleId = 'ROLE-' . strtoupper($roleName);
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')
            ->with($roleId)
            ->andReturn([
                'Role_ID' => $roleId,
                'Role_Name' => $roleName,
                'Is_Active' => $isActive ? 'TRUE' : 'FALSE',
            ]);
        $roleService->shouldReceive('getAllRoles')->andReturn([]);
        $this->app->instance(RoleService::class, $roleService);

        $this->actingAs(new GenericUser([
            'id' => 'USR-' . strtoupper($roleName),
            'User_ID' => 'USR-' . strtoupper($roleName),
            'Role_ID' => $roleId,
            'remember_token' => '',
        ]));
    }

    public function test_master_account_routes_to_administrator_dashboard()
    {
        $this->actingAsRole('MASTER');
        // DashboardController renders view directly for administrator, it doesn't redirect
        $this->get('/dashboard')->assertOk();
    }

    public function test_inactive_master_account_is_blocked()
    {
        $this->actingAsRole('MASTER', false);
        // RoleMiddleware invalidates and redirects to login with errors
        $this->get('/_test/admin-only')->assertRedirect(route('login'));
    }

    public function test_master_has_allowed_capabilities()
    {
        $this->actingAsRole('MASTER');
        
        // Use real routes to verify capability access
        $this->get('/_test/admin-only')->assertOk();
        $this->get('/_test/hr-only')->assertOk();
        $this->get('/_test/academic-only')->assertOk();
        $this->get('/_test/finance-only')->assertOk();
        $this->get('/_test/marketing-only')->assertOk();
        
        // Also verify real route dashboard access
        $this->get(route('dashboard.administrator'))->assertOk();
        $this->get(route('dashboard.hr'))->assertOk();
        $this->get(route('dashboard.academic'))->assertOk();
        $this->get(route('dashboard.finance'))->assertOk();
        $this->get(route('dashboard.marketing'))->assertOk();
    }

    public function test_master_is_explicitly_denied_teacher_student_director()
    {
        $this->actingAsRole('MASTER');
        
        $this->get('/_test/teacher-only')->assertForbidden();
        $this->get('/_test/student-only')->assertForbidden();
        $this->get('/_test/director-only')->assertForbidden();
        
        // Verify real route dashboard access explicitly denied
        $this->get(route('dashboard.teacher'))->assertForbidden();
        $this->get(route('dashboard.student'))->assertForbidden();
        $this->get(route('dashboard.director'))->assertForbidden();
    }
    
    public function test_master_allowed_if_route_supports_multiple_roles_including_allowed_ones()
    {
        $this->actingAsRole('MASTER');
        
        // Route allows ADMINISTRATOR or TEACHER. Master has ADMINISTRATOR, so it should be allowed.
        $this->get('/_test/teacher-admin')->assertOk();
    }

    public function test_existing_roles_are_unchanged()
    {
        $this->actingAsRole('TEACHER');
        $this->get('/_test/teacher-only')->assertOk();
        $this->get('/_test/admin-only')->assertForbidden();
        
        $this->actingAsRole('ADMINISTRATOR');
        $this->get('/_test/admin-only')->assertOk();
        $this->get('/_test/teacher-only')->assertForbidden();
    }

    public function test_master_capability_does_not_mutate_actor_identity_resolution()
    {
        $this->actingAsRole('MASTER');
        
        // ActorIdentity should still resolve strictly to USR-MASTER
        $this->assertEquals('USR-MASTER', ActorIdentity::required());
        $this->assertEquals('USR-MASTER', ActorIdentity::resolve());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
