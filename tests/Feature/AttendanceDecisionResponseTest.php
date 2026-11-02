<?php
namespace Tests\Feature;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\RoleService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;
class AttendanceDecisionResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id'=>'USR-T','User_ID'=>'USR-T','Role_ID'=>'TEACHER','Role'=>'TEACHER']));
        $role=Mockery::mock(RoleService::class);
        $role->shouldReceive('getRoleById')->andReturn(['Role_Name'=>'TEACHER','Is_Active'=>'TRUE']);
        $this->app->instance(RoleService::class,$role);
    }
    public function test_approval_system_error_has_traceable_report_without_exposing_details(): void
    {
        config(['app.debug'=>false]);
        $service=Mockery::mock(AttendanceRequestService::class);
        $service->shouldReceive('approveRequest')->once()->andThrow(new \Error('Private infrastructure details'));
        $this->app->instance(AttendanceRequestService::class,$service);
        $response=$this->from('/teacher/workspace/attendance-requests/REQ-A')->post(route('teacher.workspace.attendance-requests.approve','REQ-A'),['Approve_As'=>'IZIN']);
        $response->assertRedirect('/teacher/workspace/attendance-requests/REQ-A')->assertSessionHas('error');
        $this->assertStringContainsString('Kode laporan: WMS-',session('error'));
        $this->assertStringNotContainsString('Private infrastructure',session('error'));
    }
    public function test_invalid_decision_payload_is_validation_error_without_service_call(): void
    {
        $service=Mockery::mock(AttendanceRequestService::class);
        $service->shouldNotReceive('approveRequest');
        $service->shouldNotReceive('rejectRequest');
        $this->app->instance(AttendanceRequestService::class,$service);
        $this->postJson(route('teacher.workspace.attendance-requests.approve','REQ-A'),['Approve_As'=>'REJECTED'])->assertUnprocessable()->assertJsonValidationErrors('Approve_As');
        $this->postJson(route('teacher.workspace.attendance-requests.reject','REQ-A'),[])->assertUnprocessable()->assertJsonValidationErrors('Academic_Notes');
    }
    public function test_teacher_outside_scope_gets_forbidden_instead_of_generic_system_error(): void
    {
        $service=Mockery::mock(AttendanceRequestService::class);
        $service->shouldReceive('approveRequest')->once()->andThrow(new \Illuminate\Auth\Access\AuthorizationException('Outside class scope'));
        $this->app->instance(AttendanceRequestService::class,$service);
        $this->post(route('teacher.workspace.attendance-requests.approve','REQ-A'),['Approve_As'=>'IZIN'])->assertForbidden();
    }
}
