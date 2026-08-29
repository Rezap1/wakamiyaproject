<?php

namespace Tests\Unit;

use App\Http\Controllers\Academic\AssignmentController;
use App\Repositories\GoogleSheets\ClassRepository;
use App\Repositories\GoogleSheets\TeacherRepository;
use App\Services\Academic\ScheduleService;
use App\Services\Core\AssignmentService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeacherAssignmentScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_teacher_cannot_edit_assignment_owned_by_another_teacher_in_same_class(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A', 'Role' => 'TEACHER']));

        $teacherRepo = Mockery::mock(TeacherRepository::class);
        $teacherRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Teacher_ID' => 'TCH-A', 'User_ID' => 'USR-A'],
        ]));
        $this->app->instance(TeacherRepository::class, $teacherRepo);

        $scheduleService = Mockery::mock(ScheduleService::class);
        $scheduleService->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Schedule_ID' => 'SCH-A', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-1'],
        ]));
        $this->app->instance(ScheduleService::class, $scheduleService);

        $classRepo = Mockery::mock(ClassRepository::class);
        $classRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Class_ID' => 'CLS-1', 'Homeroom_Teacher_ID' => ''],
        ]));
        $this->app->instance(ClassRepository::class, $classRepo);

        $assignmentService = Mockery::mock(AssignmentService::class);
        $assignmentService->shouldReceive('getById')->once()->with('ASN-B')->andReturn([
            'Assignment_ID' => 'ASN-B',
            'Teacher_ID' => 'TCH-B',
            'Class_ID' => 'CLS-1',
        ]);

        $controller = new AssignmentController($assignmentService);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Anda tidak memiliki hak akses untuk tugas ini');

        $controller->edit('ASN-B', $classRepo, $teacherRepo);
    }
}
