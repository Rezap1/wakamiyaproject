<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Quiz\QuizScopeService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class H877QuizScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_teacher_scope_uses_only_three_bulk_reads_and_excludes_inactive_or_foreign_classes(): void
    {
        $teachers = Mockery::mock(TeacherRepositoryInterface::class);
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $schedules = Mockery::mock(ScheduleRepositoryInterface::class);
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $teachers->shouldReceive('fetchAll')->once()->andReturn(collect([['Teacher_ID' => 'T1', 'User_ID' => 'U1', 'Is_Active' => 'TRUE']]));
        $students->shouldNotReceive('fetchAll');
        $schedules->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Teacher_ID' => 'T1', 'Class_ID' => 'C1', 'Is_Active' => 'TRUE'],
            ['Teacher_ID' => 'T1', 'Class_ID' => 'C2', 'Is_Active' => 'FALSE'],
            ['Teacher_ID' => 'T2', 'Class_ID' => 'C3', 'Is_Active' => 'TRUE'],
        ]));
        $classes->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Class_ID' => 'C1', 'Class_Name' => 'Sakura', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'C2', 'Class_Name' => 'Inactive', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'C3', 'Class_Name' => 'Foreign', 'Is_Active' => 'TRUE'],
        ]));
        $scope = new QuizScopeService($teachers, $students, $schedules, $classes);
        $teacher = $scope->teacherForUser(new GenericUser(['User_ID' => 'U1']));
        $this->assertSame('T1', $teacher['Teacher_ID']);
        $this->assertSame(['C1'], $scope->classesForTeacher('T1')->pluck('Class_ID')->all());
    }

    public function test_missing_teacher_student_and_forged_class_fail_closed(): void
    {
        $teachers = Mockery::mock(TeacherRepositoryInterface::class);
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $schedules = Mockery::mock(ScheduleRepositoryInterface::class);
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $teachers->shouldReceive('fetchAll')->andReturn(collect());
        $students->shouldReceive('fetchAll')->andReturn(collect([['Student_ID' => 'S1', 'User_ID' => 'US', 'Class_ID' => '', 'Is_Active' => 'TRUE']]));
        $schedules->shouldReceive('fetchAll')->andReturn(collect());
        $classes->shouldReceive('fetchAll')->andReturn(collect());
        $scope = new QuizScopeService($teachers, $students, $schedules, $classes);

        foreach ([fn () => $scope->teacherForUser(new GenericUser(['User_ID' => 'UT'])), fn () => $scope->studentForUser(new GenericUser(['User_ID' => 'US'])), fn () => $scope->assertTeacherClass('T1', 'FORGED')] as $operation) {
            try {
                $operation();
                $this->fail('Scope resolution must fail closed.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }
}
