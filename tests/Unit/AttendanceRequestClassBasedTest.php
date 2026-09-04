<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Http\Controllers\Student\AttendanceRequestController;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\ActivityLogService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AttendanceRequestClassBasedTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_class_request_is_created_without_schedule_and_with_explicit_type(): void
    {
        $requestRepo = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $requestRepo->shouldReceive('findByStudent')->once()->with('STU-A')->andReturn(collect());
        $requestRepo->shouldReceive('generateNewId')->once()->andReturn('REQ-1');
        $requestRepo->shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            return $data['Student_ID'] === 'STU-A'
                && $data['Class_ID'] === 'CLS-A'
                && $data['Schedule_ID'] === ''
                && $data['Attendance_Type'] === 'CLASS_QR';
        }))->andReturn(true);
        $requestRepo->shouldReceive('clearCache')->once();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);

        $service = $this->service($requestRepo, $attendanceRepo);
        $result = $service->createRequest([
            'Attendance_ID' => 'ATT-STU-A-20260831-CLASS-CLS-A-CLASS_QR',
            'Student_ID' => 'STU-A',
            'Class_ID' => 'CLS-A',
            'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-08-31',
            'Request_Type' => 'IZIN',
            'Reason' => 'Sakit',
            'Evidence_URL' => 'storage/evidence.png',
        ], new GenericUser(['User_ID' => 'USR-A']));
        $this->assertTrue($result);
    }

    public function test_future_request_is_persisted_as_pending_request_without_attendance_side_effect(): void
    {
        $requestRepo = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $requestRepo->shouldReceive('findByStudent')->once()->with('STU-A')->andReturn(collect());
        $requestRepo->shouldReceive('generateNewId')->once()->andReturn('REQ-FUTURE');
        $requestRepo->shouldReceive('create')->once()->andReturn(true);
        $requestRepo->shouldReceive('clearCache')->once();

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);

        $service = $this->service($requestRepo, $attendanceRepo);
        $this->assertTrue($service->createRequest([
            'Attendance_ID' => 'ATT-STU-A-20990102-CLASS-CLS-A-CLASS_QR',
            'Student_ID' => 'STU-A', 'Class_ID' => 'CLS-A', 'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR', 'Attendance_Date' => '2099-01-02',
            'Request_Type' => 'IZIN', 'Reason' => 'Keperluan', 'Evidence_URL' => 'storage/evidence.png',
        ], new GenericUser(['User_ID' => 'USR-A'])));
    }

    public function test_student_controller_ignores_client_identity_fields_for_class_request(): void
    {
        Storage::fake('local');
        $requestService = Mockery::mock(AttendanceRequestService::class);
        $requestService->shouldReceive('createRequest')->once()->with(Mockery::on(function ($data) {
            return $data['Student_ID'] === 'STU-A'
                && $data['Class_ID'] === 'CLS-A'
                && $data['Schedule_ID'] === ''
                && $data['Attendance_Type'] === 'CLASS_QR'
                && !isset($data['Teacher_ID']);
        }), Mockery::type(GenericUser::class))->andReturn(true);
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'User_ID' => 'USR-A', 'Class_ID' => 'CLS-A'],
        ]));
        $scheduleRepo = Mockery::mock(ScheduleRepositoryInterface::class);

        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A', 'Role' => 'STUDENT']));
        $httpRequest = Request::create('/student/attendance/requests', 'POST', [
            'Attendance_Date' => '2026-08-31',
            'Schedule_ID' => '',
            'Student_ID' => 'STU-B',
            'Class_ID' => 'CLS-B',
            'Attendance_Type' => 'SCHEDULE',
            'Request_Type' => 'IZIN',
            'Reason' => 'Keperluan',
        ]);
        $httpRequest->files->set('Evidence', UploadedFile::fake()->image('evidence.png'));

        $controller = new AttendanceRequestController($requestService, $studentRepo, $scheduleRepo);
        $response = $controller->store($httpRequest);

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_student_controller_expands_end_date_into_one_request_per_date(): void
    {
        Storage::fake('local');
        $requestService = Mockery::mock(AttendanceRequestService::class);
        $requestService->shouldReceive('createRequest')->times(3)->withArgs(function ($data) {
            return in_array($data['Attendance_Date'], ['2099-01-01', '2099-01-02', '2099-01-03'], true)
                && $data['Student_ID'] === 'STU-A';
        })->andReturn(true);
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'User_ID' => 'USR-A', 'Class_ID' => 'CLS-A'],
        ]));
        $scheduleRepo = Mockery::mock(ScheduleRepositoryInterface::class);

        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A', 'Role' => 'STUDENT']));
        $httpRequest = Request::create('/student/attendance/requests', 'POST', [
            'Attendance_Date' => '2099-01-01', 'End_Date' => '2099-01-03',
            'Schedule_ID' => '', 'Request_Type' => 'IZIN', 'Reason' => 'Keperluan',
        ]);
        $httpRequest->files->set('Evidence', UploadedFile::fake()->image('evidence.png'));

        $controller = new AttendanceRequestController($requestService, $studentRepo, $scheduleRepo);
        $response = $controller->store($httpRequest);

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_class_approval_updates_existing_class_qr_attendance_without_schedule(): void
    {
        $attendance = [
            'Attendance_ID' => 'ATT-CLASS', 'Student_ID' => 'STU-A', 'Class_ID' => 'CLS-A',
            'Schedule_ID' => '', 'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-08-31', 'Status' => 'PRESENT',
        ];
        $request = $this->request(['Attendance_ID' => 'ATT-CLASS', 'Attendance_Type' => 'CLASS_QR', 'Class_ID' => 'CLS-A']);
        $requestRepo = $this->approvalRequestRepo($request);
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('findById')->once()->with('ATT-CLASS')->andReturn($attendance);
        $attendanceRepo->shouldReceive('update')->once()->with('ATT-CLASS', Mockery::on(fn ($data) => $data['Status'] === 'IZIN'))->andReturn(true);
        $attendanceRepo->shouldReceive('clearCache')->once();

        $this->assertTrue($this->service($requestRepo, $attendanceRepo)->approveRequest('REQ-1', 'IZIN', '', $this->teacherUser()));
    }

    public function test_class_approval_creates_class_identity_when_attendance_is_missing(): void
    {
        $request = $this->request(['Attendance_ID' => 'ATT-CLASS-NEW', 'Attendance_Type' => 'CLASS_QR', 'Class_ID' => 'CLS-A']);
        $requestRepo = $this->approvalRequestRepo($request);
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('findById')->once()->andReturn(null);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn(collect());
        $attendanceRepo->shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            return $data['Student_ID'] === 'STU-A'
                && $data['Class_ID'] === 'CLS-A'
                && $data['Schedule_ID'] === ''
                && $data['Attendance_Type'] === 'CLASS_QR'
                && $data['Status'] === 'SAKIT';
        }))->andReturn(true);
        $attendanceRepo->shouldReceive('clearCache')->once();

        $this->assertTrue($this->service($requestRepo, $attendanceRepo)->approveRequest('REQ-1', 'SAKIT', '', $this->teacherUser()));
    }

    public function test_schedule_approval_requires_schedule_in_student_class(): void
    {
        $request = $this->request([
            'Attendance_ID' => 'ATT-SCHEDULE', 'Attendance_Type' => 'SCHEDULE',
            'Class_ID' => 'CLS-A', 'Schedule_ID' => 'SCH-B',
        ]);
        $requestRepo = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $requestRepo->shouldReceive('findById')->once()->andReturn($request);
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $studentRepo = $this->studentRepo();
        $scheduleRepo = Mockery::mock(ScheduleRepositoryInterface::class);
        $scheduleRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Schedule_ID' => 'SCH-A', 'Class_ID' => 'CLS-A', 'Teacher_ID' => 'TCH-AC'],
            ['Schedule_ID' => 'SCH-B', 'Class_ID' => 'CLS-B', 'Teacher_ID' => 'TCH-AC'],
        ]));

        $this->expectException(\Exception::class);
        $this->service($requestRepo, $attendanceRepo, $studentRepo, $scheduleRepo)
            ->approveRequest('REQ-1', 'IZIN', '', $this->teacherUser());
    }

    public function test_schedule_approval_creates_schedule_identity_for_valid_schedule(): void
    {
        $request = $this->request([
            'Attendance_ID' => 'ATT-SCHEDULE', 'Attendance_Type' => 'SCHEDULE',
            'Class_ID' => 'CLS-A', 'Schedule_ID' => 'SCH-A',
        ]);
        $requestRepo = $this->approvalRequestRepo($request);
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('findById')->once()->andReturn(null);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn(collect());
        $attendanceRepo->shouldReceive('create')->once()->with(Mockery::on(fn ($data) =>
            $data['Attendance_Type'] === 'SCHEDULE'
            && $data['Schedule_ID'] === 'SCH-A'
            && $data['Class_ID'] === 'CLS-A'
        ))->andReturn(true);
        $attendanceRepo->shouldReceive('clearCache')->once();
        $scheduleRepo = Mockery::mock(ScheduleRepositoryInterface::class);
        $scheduleRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Schedule_ID' => 'SCH-A', 'Class_ID' => 'CLS-A', 'Teacher_ID' => 'TCH-AC'],
        ]));

        $this->assertTrue($this->service($requestRepo, $attendanceRepo, $this->studentRepo(), $scheduleRepo)
            ->approveRequest('REQ-1', 'IZIN', '', $this->teacherUser()));
    }

    public function test_attendance_id_of_another_student_is_rejected(): void
    {
        $request = $this->request(['Attendance_ID' => 'ATT-OTHER', 'Attendance_Type' => 'CLASS_QR', 'Class_ID' => 'CLS-A']);
        $requestRepo = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $requestRepo->shouldReceive('findById')->once()->andReturn($request);
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('findById')->once()->andReturn([
            'Attendance_ID' => 'ATT-OTHER', 'Student_ID' => 'STU-B', 'Class_ID' => 'CLS-B',
            'Schedule_ID' => '', 'Attendance_Type' => 'CLASS_QR', 'Attendance_Date' => '2026-08-31',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Attendance_ID bukan milik student');
        $this->service($requestRepo, $attendanceRepo)
            ->approveRequest('REQ-1', 'IZIN', '', $this->teacherUser());
    }

    public function test_class_approval_does_not_create_duplicate_when_identity_exists(): void
    {
        $request = $this->request(['Attendance_ID' => 'ATT-REQUEST', 'Attendance_Type' => 'CLASS_QR', 'Class_ID' => 'CLS-A']);
        $requestRepo = $this->approvalRequestRepo($request);
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('findById')->once()->andReturn(null);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn(collect([[
            'Attendance_ID' => 'ATT-EXISTING', 'Student_ID' => 'STU-A', 'Class_ID' => 'CLS-A',
            'Schedule_ID' => '', 'Attendance_Type' => 'CLASS_QR', 'Attendance_Date' => '2026-08-31',
            'Is_Active' => 'TRUE',
        ]]));
        $attendanceRepo->shouldReceive('update')->once()->with('ATT-EXISTING', Mockery::type('array'))->andReturn(true);
        $attendanceRepo->shouldReceive('clearCache')->once();

        $this->assertTrue($this->service($requestRepo, $attendanceRepo)->approveRequest('REQ-1', 'IZIN', '', $this->teacherUser()));
    }

    public function test_legacy_request_with_schedule_id_is_validated_as_schedule_not_class(): void
    {
        $request = $this->request(['Attendance_ID' => 'ATT-LEGACY', 'Attendance_Type' => '', 'Class_ID' => 'CLS-A', 'Schedule_ID' => 'CLS-A']);
        $requestRepo = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $requestRepo->shouldReceive('findById')->once()->andReturn($request);
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $scheduleRepo = Mockery::mock(ScheduleRepositoryInterface::class);
        $scheduleRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect());

        $this->expectException(\Exception::class);
        $this->service($requestRepo, $attendanceRepo, $this->studentRepo(), $scheduleRepo)
            ->approveRequest('REQ-1', 'IZIN', '', $this->teacherUser());
    }

    private function service($requestRepo, $attendanceRepo = null, $studentRepo = null, $scheduleRepo = null): AttendanceRequestService
    {
        return new AttendanceRequestService(
            $requestRepo,
            $attendanceRepo ?: Mockery::mock(AttendanceRepositoryInterface::class),
            Mockery::mock(ActivityLogService::class)->shouldReceive('log')->andReturn(true)->getMock(),
            $studentRepo ?: $this->studentRepo(),
            $scheduleRepo ?: $this->scheduleRepo(),
            null,
            $this->teacherRepo(),
            $this->classRepo()
        );
    }

    private function teacherUser(): GenericUser
    {
        return new GenericUser(['User_ID' => 'USR-AC', 'Role' => 'TEACHER']);
    }

    private function studentRepo()
    {
        return Mockery::mock(StudentRepositoryInterface::class)
            ->shouldReceive('fetchAll')->andReturn(collect([
                ['Student_ID' => 'STU-A', 'User_ID' => 'USR-A', 'Class_ID' => 'CLS-A'],
            ]))->getMock();
    }

    private function approvalRequestRepo(array $request)
    {
        $repo = Mockery::mock(AttendanceRequestRepositoryInterface::class);
        $repo->shouldReceive('findById')->once()->andReturn($request);
        $repo->shouldReceive('update')->once()->andReturn(true);
        $repo->shouldReceive('clearCache')->once();
        return $repo;
    }

    private function scheduleRepo()
    {
        return Mockery::mock(ScheduleRepositoryInterface::class)
            ->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
                ['Schedule_ID' => 'SCH-A', 'Class_ID' => 'CLS-A', 'Teacher_ID' => 'TCH-AC'],
            ]))->getMock();
    }

    private function teacherRepo()
    {
        return Mockery::mock(TeacherRepositoryInterface::class)
            ->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
                ['Teacher_ID' => 'TCH-AC', 'User_ID' => 'USR-AC'],
            ]))->getMock();
    }

    private function classRepo()
    {
        return Mockery::mock(ClassRepositoryInterface::class)
            ->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
                ['Class_ID' => 'CLS-A', 'Class_Name' => 'Class A'],
            ]))->getMock();
    }

    private function request(array $overrides = []): array
    {
        return array_merge([
            'Request_ID' => 'REQ-1', 'Student_ID' => 'STU-A', 'Class_ID' => 'CLS-A',
            'Schedule_ID' => '', 'Attendance_Type' => 'CLASS_QR',
            'Attendance_ID' => 'ATT-1', 'Attendance_Date' => '2026-08-31',
            'Status' => 'PENDING',
        ], $overrides);
    }
}
