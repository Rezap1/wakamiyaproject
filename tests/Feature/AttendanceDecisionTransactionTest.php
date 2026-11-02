<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Repositories\GoogleSheets\AttendanceRepository;
use App\Repositories\GoogleSheets\AttendanceRequestRepository;
use App\Repositories\GoogleSheets\StudentRepository;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\ActivityLogService;
use App\Support\Migration\WmsMigrationManifest;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AttendanceDecisionTransactionTest extends TestCase
{
    private AttendanceRequestRepository $requests;
    private StudentRepository $students;
    private bool $mysqlTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection(config('wms_runtime.connection'));
        if ($db->getDriverName() === 'sqlite' && $db->getDatabaseName() === ':memory:') {
            foreach (['MASTER_ATTENDANCE_REQUEST', 'MASTER_ATTENDANCE', 'MASTER_STUDENT'] as $name) {
                Schema::create($name, function (Blueprint $table) use ($name): void {
                    $contract = WmsMigrationManifest::contracts()[$name];
                    foreach (array_unique([...$contract['headers'], ...config('wms_runtime.runtime_columns.'.$name, [])]) as $column) {
                        $table->string($column)->nullable();
                    }
                });
            }
        } else {
            $this->assertContains($db->getDriverName(), ['mysql', 'mariadb']);
            $this->assertSame('wms_mysql_test', $db->getDatabaseName(), 'Refuse to write outside the isolated test database.');
            $this->assertSame('wms_mysql_test', $db->selectOne('SELECT DATABASE() AS db')->db);
            foreach (['MASTER_ATTENDANCE_REQUEST', 'MASTER_ATTENDANCE', 'MASTER_STUDENT'] as $name) {
                foreach (config('wms_runtime.runtime_columns.'.$name, []) as $column) {
                    $this->assertTrue($db->getSchemaBuilder()->hasColumn($name, $column), "BLOCKED: {$name}.{$column} missing in isolated schema");
                }
            }
            $db->beginTransaction();
            $this->mysqlTransaction = true;
        }
        $this->requests = new AttendanceRequestRepository;
        $this->students = new StudentRepository;
        $this->students->create(['Student_ID' => 'STU-AUDIT-TX', 'User_ID' => 'USR-AUDIT-STUDENT', 'Class_ID' => 'CLS-AUDIT']);
        $this->requests->create([
            'Request_ID' => 'REQ-AUDIT-TX', 'Student_ID' => 'STU-AUDIT-TX',
            'Class_ID' => 'CLS-AUDIT', 'Attendance_Type' => 'CLASS_QR',
            'Attendance_ID' => 'ATT-AUDIT-TX', 'Attendance_Date' => '2026-11-02',
            'Request_Type' => 'IZIN', 'Status' => 'PENDING',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->mysqlTransaction) {
            DB::connection(config('wms_runtime.connection'))->rollBack();
        }
        parent::tearDown();
    }

    public function test_approval_persists_both_rows_and_conflicting_rejection_changes_neither(): void
    {
        $attendance = new AttendanceRepository;
        $service = $this->service($attendance);
        $service->approveRequest('REQ-AUDIT-TX', 'IZIN', 'Approved', $this->teacher());
        $this->assertSame('APPROVED', $this->requests->findByIdFresh('REQ-AUDIT-TX')['Status']);
        $this->assertSame('IZIN', $attendance->findByIdFresh('ATT-AUDIT-TX')['Status']);
        try {
            $service->rejectRequest('REQ-AUDIT-TX', 'Conflicting decision', $this->teacher());
            $this->fail('Conflicting decision must fail.');
        } catch (\Exception $failure) {
            $this->assertStringContainsString('sudah disetujui', $failure->getMessage());
        }
        $this->assertCount(1, $attendance->fetchAll()->where('Student_ID', 'STU-AUDIT-TX'));
        $this->assertSame('APPROVED', $this->requests->findByIdFresh('REQ-AUDIT-TX')['Status']);
    }

    public function test_attendance_write_failure_rolls_back_decision_and_attendance_insert(): void
    {
        $attendance = new class extends AttendanceRepository {
            public function create(array $data)
            {
                parent::create($data);
                throw new \RuntimeException('Injected failure after attendance insert');
            }
        };
        try {
            $this->service($attendance)->approveRequest('REQ-AUDIT-TX', 'IZIN', 'Approved', $this->teacher());
            $this->fail('Injected failure must escape.');
        } catch (\RuntimeException $failure) {
            $this->assertSame('Injected failure after attendance insert', $failure->getMessage());
        }
        $this->assertSame('PENDING', $this->requests->findByIdFresh('REQ-AUDIT-TX')['Status']);
        $this->assertNull($attendance->findByIdFresh('ATT-AUDIT-TX'));
    }

    public function test_rejection_syncs_alpa_and_retry_preserves_audited_correction(): void
    {
        $attendance = new AttendanceRepository;
        $service = $this->service($attendance);
        $service->rejectRequest('REQ-AUDIT-TX', 'Evidence invalid', $this->teacher());
        $this->assertSame('REJECTED', $this->requests->findByIdFresh('REQ-AUDIT-TX')['Status']);
        $this->assertSame('ALPA', $attendance->findByIdFresh('ATT-AUDIT-TX')['Status']);
        $attendance->update('ATT-AUDIT-TX', ['Status' => 'PRESENT', 'Notes' => 'Official correction', 'Updated_By' => 'MASTER']);
        $service->rejectRequest('REQ-AUDIT-TX', 'Retry', $this->teacher());
        $this->assertSame('PRESENT', $attendance->findByIdFresh('ATT-AUDIT-TX')['Status']);
        $this->assertSame('Evidence invalid', $this->requests->findByIdFresh('REQ-AUDIT-TX')['Academic_Notes']);
    }

    private function teacher(): GenericUser
    {
        return new GenericUser(['User_ID' => 'USR-AUDIT-TEACHER', 'Role' => 'TEACHER']);
    }

    private function service(AttendanceRepository $attendance): AttendanceRequestService
    {
        $log = Mockery::mock(ActivityLogService::class);
        $log->shouldReceive('log')->andReturn(true);
        $schedules = Mockery::mock(ScheduleRepositoryInterface::class);
        $schedules->shouldReceive('fetchAll')->andReturn(collect([
            ['Schedule_ID' => 'SCH-AUDIT', 'Class_ID' => 'CLS-AUDIT', 'Teacher_ID' => 'TCH-AUDIT'],
        ]));
        $teachers = Mockery::mock(TeacherRepositoryInterface::class);
        $teachers->shouldReceive('fetchAll')->andReturn(collect([
            ['Teacher_ID' => 'TCH-AUDIT', 'User_ID' => 'USR-AUDIT-TEACHER'],
        ]));
        $classes = Mockery::mock(ClassRepositoryInterface::class);
        $classes->shouldReceive('fetchAll')->andReturn(collect([['Class_ID' => 'CLS-AUDIT']]));

        return new AttendanceRequestService($this->requests, $attendance, $log, $this->students, $schedules, null, $teachers, $classes);
    }
}
