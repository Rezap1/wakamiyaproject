<?php

namespace Tests\Unit;

use App\Http\Requests\UpdateAssessmentRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Interfaces\GoogleSheets\AcademicYearRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Core\ProgramService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class UpdateRequestIntegrityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_module_update_allows_unchanged_code_and_name_for_current_route_id(): void
    {
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldReceive('findByCode')->with('USR')->once()->andReturn(['Module_ID' => 'MOD-001']);
        $repository->shouldReceive('findByName')->with('Users')->once()->andReturn(['Module_ID' => 'MOD-001']);
        $this->app->instance(ModuleRepositoryInterface::class, $repository);

        $request = $this->makeUpdateRequest(UpdateModuleRequest::class, [
            'Module_Code' => 'USR',
            'Module_Name' => 'Users',
            'Module_Group' => 'Core',
            'Module_Order' => '1',
            'Is_Active' => 'TRUE',
        ], 'MOD-001');

        $this->assertValid($request);
    }

    public function test_module_update_rejects_code_used_by_another_module(): void
    {
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldReceive('findByCode')->with('USR')->once()->andReturn(['Module_ID' => 'MOD-OTHER']);
        $repository->shouldReceive('findByName')->with('Users')->once()->andReturn(null);
        $this->app->instance(ModuleRepositoryInterface::class, $repository);

        $request = $this->makeUpdateRequest(UpdateModuleRequest::class, [
            'Module_Code' => 'USR',
            'Module_Name' => 'Users',
            'Module_Group' => 'Core',
            'Module_Order' => '1',
            'Is_Active' => 'TRUE',
        ], 'MOD-001');

        $this->assertInvalid($request, 'Module_Code');
    }

    public function test_schedule_update_requires_valid_references_and_time_order(): void
    {
        $this->bindScheduleReferences(subject: null);

        $request = $this->makeUpdateRequest(UpdateScheduleRequest::class, [
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-MISSING',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '10:00',
            'End_Time' => '09:00',
            'Room' => 'A1',
        ], 'SCH-001');

        $validator = $this->validator($request);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('Subject_ID', $validator->errors()->toArray());
        $this->assertArrayHasKey('End_Time', $validator->errors()->toArray());
    }

    public function test_schedule_update_accepts_valid_edit_without_status_field(): void
    {
        $this->bindScheduleReferences();

        $request = $this->makeUpdateRequest(UpdateScheduleRequest::class, [
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
            'Room' => 'A1',
        ], 'SCH-001');

        $validated = $this->assertValid($request);

        $this->assertArrayNotHasKey('Is_Active', $validated);
    }

    public function test_attendance_update_excludes_readonly_target_identity_fields(): void
    {
        $request = $this->makeUpdateRequest(UpdateAttendanceRequest::class, [
            'Student_ID' => 'STU-CHANGED',
            'Employee_ID' => 'EMP-CHANGED',
            'Attendance_Date' => '2026-09-04',
            'Status' => 'PRESENT',
            'Check_In_Time' => '08:00',
            'Check_Out_Time' => '16:00',
        ], 'ATT-001');

        $validated = $this->assertValid($request);

        $this->assertArrayNotHasKey('Student_ID', $validated);
        $this->assertArrayNotHasKey('Employee_ID', $validated);
    }

    public function test_assessment_update_excludes_readonly_assessment_code(): void
    {
        $request = $this->makeUpdateRequest(UpdateAssessmentRequest::class, [
            'Assessment_Code' => 'ASM-CHANGED',
            'Assessment_Name' => 'Midterm',
            'Category' => 'Mid Test',
            'Status' => 'Published',
            'Description' => 'Updated description',
        ], 'ASM-001');

        $validated = $this->assertValid($request);

        $this->assertArrayNotHasKey('Assessment_Code', $validated);
    }

    public function test_subject_update_excludes_subject_id_and_validates_program_reference(): void
    {
        $programService = Mockery::mock(ProgramService::class);
        $programService->shouldReceive('getProgramById')->with('PRG-001')->once()->andReturn([
            'Program_ID' => 'PRG-001',
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(ProgramService::class, $programService);

        $request = $this->makeUpdateRequest(UpdateSubjectRequest::class, [
            'Subject_ID' => 'SUB-CHANGED',
            'Subject_Code' => 'MAT',
            'Subject_Name' => 'Mathematics',
            'Program_ID' => 'PRG-001',
            'Credit' => '3',
            'Duration' => '90',
            'Is_Active' => 'TRUE',
        ], 'SUB-001');

        $validated = $this->assertValid($request);

        $this->assertArrayNotHasKey('Subject_ID', $validated);
    }

    /**
     * @param class-string<FormRequest> $requestClass
     */
    private function makeUpdateRequest(string $requestClass, array $payload, string $id): FormRequest
    {
        $request = $requestClass::create('/test/' . $id, 'PUT', $payload);
        $request->setContainer($this->app);

        $route = new Route(['PUT'], '/test/{id}', []);
        $route->bind($request);
        $route->setParameter('id', $id);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function assertValid(FormRequest $request): array
    {
        $validator = $this->validator($request);

        $this->assertTrue($validator->passes(), var_export($validator->errors()->toArray(), true));

        return $validator->validated();
    }

    private function assertInvalid(FormRequest $request, string $field): void
    {
        $validator = $this->validator($request);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    private function validator(FormRequest $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), $request->rules());
    }

    private function bindScheduleReferences(
        ?array $class = ['Class_ID' => 'CLS-001', 'Is_Active' => 'TRUE'],
        ?array $subject = ['Subject_ID' => 'SUB-001', 'Is_Active' => 'TRUE'],
        ?array $teacher = ['Teacher_ID' => 'TCH-001', 'Is_Active' => 'TRUE'],
        ?array $academicYear = ['Academic_Year_ID' => 'AY-001', 'Is_Active' => 'TRUE'],
    ): void {
        $classRepo = Mockery::mock(ClassRepositoryInterface::class);
        $classRepo->shouldReceive('findById')->andReturn($class);
        $this->app->instance(ClassRepositoryInterface::class, $classRepo);

        $subjectRepo = Mockery::mock(SubjectRepositoryInterface::class);
        $subjectRepo->shouldReceive('findById')->andReturn($subject);
        $this->app->instance(SubjectRepositoryInterface::class, $subjectRepo);

        $teacherRepo = Mockery::mock(TeacherRepositoryInterface::class);
        $teacherRepo->shouldReceive('findById')->andReturn($teacher);
        $this->app->instance(TeacherRepositoryInterface::class, $teacherRepo);

        $academicYearRepo = Mockery::mock(AcademicYearRepositoryInterface::class);
        $academicYearRepo->shouldReceive('findById')->andReturn($academicYear);
        $this->app->instance(AcademicYearRepositoryInterface::class, $academicYearRepo);
    }
}
