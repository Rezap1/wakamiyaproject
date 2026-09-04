<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\NotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class StudentPermissionWorkflowIntegrityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_student_request_is_server_owned_pending_without_attendance_side_effect(): void
    {
        [$service, $requestRepo, $attendanceRepo] = $this->newService();

        $this->assertTrue($service->createRequest([
            'Attendance_ID' => 'ATT-FORGED',
            'Student_ID' => 'STU-A',
            'Class_ID' => 'CLS-FORGED',
            'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-09-04',
            'Request_Type' => 'IZIN',
            'Reason' => 'Keperluan keluarga',
            'Evidence_URL' => 'attendance/evidence.jpg',
        ], $this->studentUser('USR-STU-A')));

        $createdRequest = reset($requestRepo->rows);

        $this->assertSame('STU-A', $createdRequest['Student_ID']);
        $this->assertSame('CLS-A', $createdRequest['Class_ID']);
        $this->assertSame('PENDING', $createdRequest['Status']);
        $this->assertSame([], $attendanceRepo->rows);
        $this->assertSame(0, $attendanceRepo->createCount);
    }

    public function test_student_cannot_submit_request_for_another_student(): void
    {
        [$service, $requestRepo, $attendanceRepo] = $this->newService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak sesuai');

        try {
            $service->createRequest([
                'Attendance_ID' => 'ATT-FORGED',
                'Student_ID' => 'STU-B',
                'Class_ID' => 'CLS-B',
                'Schedule_ID' => '',
                'Attendance_Type' => 'CLASS_QR',
                'Attendance_Date' => '2026-09-04',
                'Request_Type' => 'IZIN',
                'Reason' => 'Forged',
                'Evidence_URL' => 'attendance/evidence.jpg',
            ], $this->studentUser('USR-STU-A'));
        } finally {
            $this->assertSame([], $requestRepo->rows);
            $this->assertSame([], $attendanceRepo->rows);
        }
    }

    public function test_teacher_only_sees_requests_inside_teaching_scope(): void
    {
        [$service] = $this->newService([
            $this->request(['Request_ID' => 'REQ-A', 'Student_ID' => 'STU-A', 'Class_ID' => 'CLS-A']),
            $this->request(['Request_ID' => 'REQ-B', 'Student_ID' => 'STU-B', 'Class_ID' => 'CLS-B']),
        ]);

        $visible = $service->getTeacherRequests($this->teacherUser('USR-TCH-A'));

        $this->assertSame(['REQ-A'], $visible->pluck('Request_ID')->all());
    }

    public function test_scoped_teacher_approves_sakit_and_notifies_student(): void
    {
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('NotifyUser')->once()->withArgs(function ($userId, $title, $message) {
            return $userId === 'USR-STU-A'
                && str_contains($title, 'DISETUJUI')
                && str_contains($message, 'Sakit');
        })->andReturn(true);

        [$service, $requestRepo, $attendanceRepo] = $this->newService([
            $this->request(['Request_Type' => 'SAKIT']),
        ], [], $notifications);

        $this->assertTrue($service->approveRequest('REQ-A', 'SAKIT', 'Valid', $this->teacherUser('USR-TCH-A')));

        $this->assertSame('APPROVED', $requestRepo->rows['REQ-A']['Status']);
        $this->assertSame('USR-TCH-A', $requestRepo->rows['REQ-A']['Reviewed_By']);
        $this->assertSame('SAKIT', $attendanceRepo->rows['ATT-A']['Status']);
    }

    public function test_scoped_teacher_approves_waiting_izin_consistently(): void
    {
        [$service, $requestRepo, $attendanceRepo] = $this->newService([
            $this->request(['Status' => 'WAITING', 'Request_Type' => 'IZIN']),
        ]);

        $this->assertTrue($service->approveRequest('REQ-A', 'IZIN', 'OK', $this->teacherUser('USR-TCH-A')));

        $this->assertSame('APPROVED', $requestRepo->rows['REQ-A']['Status']);
        $this->assertSame('IZIN', $attendanceRepo->rows['ATT-A']['Status']);
    }

    public function test_scoped_teacher_rejects_request_to_alpa_and_notifies_student(): void
    {
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('NotifyUser')->once()->withArgs(function ($userId, $title, $message) {
            return $userId === 'USR-STU-A'
                && str_contains($title, 'DITOLAK')
                && str_contains($message, 'Alpa');
        })->andReturn(true);

        [$service, $requestRepo, $attendanceRepo] = $this->newService([
            $this->request(['Request_Type' => 'IZIN']),
        ], [], $notifications);

        $this->assertTrue($service->rejectRequest('REQ-A', 'Bukti tidak valid', $this->teacherUser('USR-TCH-A')));

        $this->assertSame('REJECTED', $requestRepo->rows['REQ-A']['Status']);
        $this->assertSame('ALPA', $attendanceRepo->rows['ATT-A']['Status']);
    }

    public function test_teacher_outside_scope_is_denied_fail_closed(): void
    {
        [$service, $requestRepo, $attendanceRepo] = $this->newService([
            $this->request(),
        ]);

        $this->expectException(AuthorizationException::class);

        try {
            $service->approveRequest('REQ-A', 'IZIN', 'Nope', $this->teacherUser('USR-TCH-B'));
        } finally {
            $this->assertSame('PENDING', $requestRepo->rows['REQ-A']['Status']);
            $this->assertSame([], $requestRepo->updates);
            $this->assertSame([], $attendanceRepo->rows);
        }
    }

    public function test_student_actor_cannot_review_requests(): void
    {
        [$service] = $this->newService([
            $this->request(),
        ]);

        $this->expectException(AuthorizationException::class);

        $service->rejectRequest('REQ-A', 'Tidak valid', $this->studentUser('USR-STU-A'));
    }

    public function test_unknown_status_is_rejected_without_mutating_attendance_or_request(): void
    {
        [$service, $requestRepo, $attendanceRepo] = $this->newService([
            $this->request(['Status' => 'ESCALATED']),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Status pengajuan tidak valid');

        try {
            $service->approveRequest('REQ-A', 'IZIN', 'OK', $this->teacherUser('USR-TCH-A'));
        } finally {
            $this->assertSame('ESCALATED', $requestRepo->rows['REQ-A']['Status']);
            $this->assertSame([], $requestRepo->updates);
            $this->assertSame([], $attendanceRepo->rows);
        }
    }

    public function test_approved_same_status_is_idempotent(): void
    {
        [$service, $requestRepo, $attendanceRepo] = $this->newService([
            $this->request(['Status' => 'APPROVED', 'Request_Type' => 'SAKIT']),
        ], [
            'ATT-A' => $this->attendance(['Status' => 'SAKIT']),
        ]);

        $this->assertTrue($service->approveRequest('REQ-A', 'SAKIT', 'Duplicate click', $this->teacherUser('USR-TCH-A')));
        $this->assertSame([], $requestRepo->updates);
        $this->assertSame([], $attendanceRepo->updates);
        $this->assertSame(0, $attendanceRepo->createCount);
    }

    public function test_rejected_alpa_is_idempotent(): void
    {
        [$service, $requestRepo, $attendanceRepo] = $this->newService([
            $this->request(['Status' => 'REJECTED']),
        ], [
            'ATT-A' => $this->attendance(['Status' => 'ALPA']),
        ]);

        $this->assertTrue($service->rejectRequest('REQ-A', 'Duplicate click', $this->teacherUser('USR-TCH-A')));
        $this->assertSame([], $requestRepo->updates);
        $this->assertSame([], $attendanceRepo->updates);
        $this->assertSame(0, $attendanceRepo->createCount);
    }

    public function test_forged_request_class_does_not_expand_teacher_scope(): void
    {
        [$service] = $this->newService([
            $this->request(['Class_ID' => 'CLS-B']),
        ]);

        $this->expectException(AuthorizationException::class);

        $service->approveRequest('REQ-A', 'IZIN', 'Nope', $this->teacherUser('USR-TCH-B'));
    }

    private function newService(array $requests = [], array $attendances = [], ?NotificationService $notifications = null): array
    {
        $requestRepo = new H838AttendanceRequestMemoryRepository($requests);
        $attendanceRepo = new H838AttendanceMemoryRepository($attendances);
        $activityLog = Mockery::mock(ActivityLogService::class);
        $activityLog->shouldReceive('log')->zeroOrMoreTimes()->andReturn(true);

        $service = new AttendanceRequestService(
            $requestRepo,
            $attendanceRepo,
            $activityLog,
            new H838StudentMemoryRepository($this->students()),
            new H838ScheduleMemoryRepository($this->schedules()),
            $notifications,
            new H838TeacherMemoryRepository($this->teachers()),
            new H838ClassMemoryRepository($this->classes())
        );

        return [$service, $requestRepo, $attendanceRepo];
    }

    private function request(array $overrides = []): array
    {
        return array_merge([
            'Request_ID' => 'REQ-A',
            'Attendance_ID' => 'ATT-A',
            'Student_ID' => 'STU-A',
            'Class_ID' => 'CLS-A',
            'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-09-04',
            'Request_Type' => 'IZIN',
            'Reason' => 'Keperluan',
            'Evidence_URL' => 'attendance/evidence.jpg',
            'Status' => 'PENDING',
        ], $overrides);
    }

    private function attendance(array $overrides = []): array
    {
        return array_merge([
            'Attendance_ID' => 'ATT-A',
            'Student_ID' => 'STU-A',
            'Class_ID' => 'CLS-A',
            'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-09-04',
            'Status' => 'IZIN',
            'Is_Active' => 'TRUE',
        ], $overrides);
    }

    private function studentUser(string $userId): GenericUser
    {
        return new GenericUser(['id' => $userId, 'User_ID' => $userId, 'Role' => 'STUDENT']);
    }

    private function teacherUser(string $userId): GenericUser
    {
        return new GenericUser(['id' => $userId, 'User_ID' => $userId, 'Role' => 'TEACHER']);
    }

    private function students(): array
    {
        return [
            ['Student_ID' => 'STU-A', 'User_ID' => 'USR-STU-A', 'Full_Name' => 'Aiko Tanaka', 'Student_Number' => 'NIS-001', 'Class_ID' => 'CLS-A'],
            ['Student_ID' => 'STU-B', 'User_ID' => 'USR-STU-B', 'Full_Name' => 'Bima Putra', 'Student_Number' => 'NIS-002', 'Class_ID' => 'CLS-B'],
        ];
    }

    private function schedules(): array
    {
        return [
            ['Schedule_ID' => 'SCH-A', 'Class_ID' => 'CLS-A', 'Teacher_ID' => 'TCH-A', 'Subject_ID' => 'SUB-A'],
            ['Schedule_ID' => 'SCH-B', 'Class_ID' => 'CLS-B', 'Teacher_ID' => 'TCH-B', 'Subject_ID' => 'SUB-B'],
        ];
    }

    private function teachers(): array
    {
        return [
            ['Teacher_ID' => 'TCH-A', 'User_ID' => 'USR-TCH-A', 'Full_Name' => 'Sensei A'],
            ['Teacher_ID' => 'TCH-B', 'User_ID' => 'USR-TCH-B', 'Full_Name' => 'Sensei B'],
        ];
    }

    private function classes(): array
    {
        return [
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas Sakura'],
            ['Class_ID' => 'CLS-B', 'Class_Name' => 'Kelas Fuji'],
        ];
    }
}

final class H838AttendanceRequestMemoryRepository implements AttendanceRequestRepositoryInterface
{
    public array $rows = [];
    public array $updates = [];
    private int $counter = 1;

    public function __construct(array $rows = [])
    {
        foreach ($rows as $row) {
            $this->rows[$row['Request_ID']] = $row;
        }
    }

    public function fetchAll()
    {
        return collect(array_values($this->rows));
    }

    public function findById(string $id)
    {
        return $this->rows[$id] ?? null;
    }

    public function findByStudent(string $studentId)
    {
        return collect($this->rows)->filter(fn ($row) => ($row['Student_ID'] ?? '') === $studentId)->values();
    }

    public function create(array $data)
    {
        $this->rows[$data['Request_ID']] = $data;
        return true;
    }

    public function update($id, array $data)
    {
        if (!isset($this->rows[$id])) {
            return false;
        }

        $this->updates[] = [$id, $data];
        $this->rows[$id] = array_merge($this->rows[$id], $data);
        return true;
    }

    public function clearCache()
    {
        return true;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix . '-' . str_pad((string) $this->counter++, $padding, '0', STR_PAD_LEFT);
    }
}

final class H838AttendanceMemoryRepository implements AttendanceRepositoryInterface
{
    public array $rows = [];
    public array $updates = [];
    public int $createCount = 0;

    public function __construct(array $rows = [])
    {
        foreach ($rows as $key => $row) {
            $row['Attendance_ID'] = $row['Attendance_ID'] ?? (is_string($key) ? $key : 'ATT-' . $key);
            $this->rows[$row['Attendance_ID']] = $row;
        }
    }

    public function fetchAll()
    {
        return collect(array_values($this->rows));
    }

    public function findById(string $id)
    {
        return $this->rows[$id] ?? null;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix . '-' . str_pad((string) (count($this->rows) + 1), $padding, '0', STR_PAD_LEFT);
    }

    public function create(array $data)
    {
        $this->createCount++;
        $this->rows[$data['Attendance_ID']] = $data;
        return true;
    }

    public function update(string $id, array $data)
    {
        if (!isset($this->rows[$id])) {
            return false;
        }

        $this->updates[] = [$id, $data];
        $this->rows[$id] = array_merge($this->rows[$id], $data);
        return true;
    }

    public function softDelete(string $id)
    {
        unset($this->rows[$id]);
        return true;
    }

    public function clearCache()
    {
        return true;
    }
}

final class H838StudentMemoryRepository implements StudentRepositoryInterface
{
    public function __construct(private array $rows)
    {
    }

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Student_ID', $id);
    }

    public function findByStudentNumber(string $number)
    {
        return collect($this->rows)->firstWhere('Student_Number', $number);
    }

    public function findByNationalId(string $nationalId)
    {
        return null;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix . '-001';
    }

    public function create(array $data)
    {
        return true;
    }

    public function update(string $id, array $data)
    {
        return true;
    }

    public function softDelete(string $id)
    {
        return true;
    }

    public function clearCache()
    {
        return true;
    }
}

final class H838ScheduleMemoryRepository implements ScheduleRepositoryInterface
{
    public function __construct(private array $rows)
    {
    }

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Schedule_ID', $id);
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix . '-001';
    }

    public function create(array $data)
    {
        return true;
    }

    public function update(string $id, array $data)
    {
        return true;
    }

    public function softDelete(string $id)
    {
        return true;
    }

    public function clearCache()
    {
        return true;
    }
}

final class H838TeacherMemoryRepository implements TeacherRepositoryInterface
{
    public function __construct(private array $rows)
    {
    }

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Teacher_ID', $id);
    }

    public function findByEmployeeId(string $employeeId)
    {
        return null;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix . '-001';
    }

    public function generateTeacherCode(string $prefix, string $year, int $padding = 3): string
    {
        return $prefix . $year . '001';
    }

    public function create(array $data)
    {
        return true;
    }

    public function update(string $id, array $data)
    {
        return true;
    }

    public function softDelete(string $id)
    {
        return true;
    }
}

final class H838ClassMemoryRepository implements ClassRepositoryInterface
{
    public function __construct(private array $rows)
    {
    }

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Class_ID', $id);
    }

    public function findByCode(string $code)
    {
        return collect($this->rows)->firstWhere('Class_Code', $code);
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix . '-001';
    }

    public function create(array $data)
    {
        return true;
    }

    public function update(string $id, array $data)
    {
        return true;
    }

    public function softDelete(string $id)
    {
        return true;
    }

    public function clearCache()
    {
        return true;
    }
}
