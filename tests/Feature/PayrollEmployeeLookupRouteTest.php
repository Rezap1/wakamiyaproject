<?php

namespace Tests\Feature;

use App\Services\Core\DepartmentService;
use App\Services\Core\EmployeeService;
use App\Services\Core\PositionService;
use App\Services\Core\RoleService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class PayrollEmployeeLookupRouteTest extends TestCase
{
    public function test_finance_can_use_employee_lookup_required_by_payroll_create_form(): void
    {
        $this->mockRole('ROLE-FINANCE', 'FINANCE');

        $employeeService = Mockery::mock(EmployeeService::class);
        $employeeService->shouldReceive('getEmployeeById')
            ->once()
            ->with('EMP-001')
            ->andReturn([
                'Employee_ID' => 'EMP-001',
                'Employee_Number' => 'WK-001',
                'Full_Name' => 'Finance Staff',
                'Email' => 'finance@example.test',
                'Phone_Number' => '08123456789',
                'Employment_Status' => 'Active',
                'Department_ID' => 'DEP-FIN',
                'Position_ID' => 'POS-FIN',
            ]);
        $this->app->instance(EmployeeService::class, $employeeService);

        $departmentService = Mockery::mock(DepartmentService::class);
        $departmentService->shouldReceive('getDepartmentById')
            ->once()
            ->with('DEP-FIN')
            ->andReturn(['Department_Name' => 'Finance']);
        $this->app->instance(DepartmentService::class, $departmentService);

        $positionService = Mockery::mock(PositionService::class);
        $positionService->shouldReceive('getPositionById')
            ->once()
            ->with('POS-FIN')
            ->andReturn(['Position_Name' => 'Finance Staff']);
        $this->app->instance(PositionService::class, $positionService);

        $this->actingAs(new GenericUser([
            'id' => 'USR-FIN',
            'User_ID' => 'USR-FIN',
            'Role_ID' => 'ROLE-FINANCE',
        ]));

        $this->getJson('/api/employees/EMP-001')
            ->assertOk()
            ->assertJsonPath('Employee_ID', 'EMP-001')
            ->assertJsonPath('Department_Name', 'Finance')
            ->assertJsonPath('Position_Name', 'Finance Staff');
    }

    public function test_student_cannot_use_employee_lookup_endpoint(): void
    {
        $this->mockRole('ROLE-STUDENT', 'STUDENT');

        $employeeService = Mockery::mock(EmployeeService::class);
        $employeeService->shouldNotReceive('getEmployeeById');
        $this->app->instance(EmployeeService::class, $employeeService);

        $this->actingAs(new GenericUser([
            'id' => 'USR-STUDENT',
            'User_ID' => 'USR-STUDENT',
            'Role_ID' => 'ROLE-STUDENT',
        ]));

        $this->getJson('/api/employees/EMP-001')->assertForbidden();
    }

    private function mockRole(string $roleId, string $roleName): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')
            ->with($roleId)
            ->andReturn([
                'Role_ID' => $roleId,
                'Role_Name' => $roleName,
                'Is_Active' => 'TRUE',
            ]);
        $this->app->instance(RoleService::class, $roleService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
