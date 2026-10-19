<?php

namespace Tests\Feature;

use App\Repositories\GoogleSheets\AttendanceRequestRepository;
use App\Support\Migration\WmsMigrationManifest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceRequestPersistenceContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createAttendanceRequestTable(true);
    }

    public function test_class_and_attendance_type_survive_repository_round_trip(): void
    {
        $repository = new AttendanceRequestRepository;

        $this->assertTrue($repository->create([
            'Request_ID' => 'REQ-0000001',
            'Attendance_ID' => 'ATT-STU-A-20261019-CLASS-CLS-A-CLASS_QR',
            'Student_ID' => 'STU-A',
            'Class_ID' => 'CLS-A',
            'Schedule_ID' => '',
            'Attendance_Type' => 'CLASS_QR',
            'Attendance_Date' => '2026-10-19',
            'Request_Type' => 'IZIN',
            'Reason' => 'Keperluan keluarga',
            'Evidence_URL' => 'storage/attendance-evidence/evidence.jpg',
            'Status' => 'PENDING',
            'Created_At' => '2026-10-19 08:00:00',
            'Updated_At' => '2026-10-19 08:00:00',
        ]));

        $persisted = $repository->findById('REQ-0000001');

        $this->assertSame('CLS-A', $persisted['Class_ID']);
        $this->assertSame('CLASS_QR', $persisted['Attendance_Type']);
    }

    public function test_routing_column_migration_is_additive_idempotent_and_reversible_in_test_database(): void
    {
        $this->createAttendanceRequestTable(false);
        $migration = require database_path('migrations/wms_mysql/2026_10_19_000001_add_attendance_request_routing_columns.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumns('MASTER_ATTENDANCE_REQUEST', ['Class_ID', 'Attendance_Type']));
        $this->assertTrue(Schema::hasIndex('MASTER_ATTENDANCE_REQUEST', 'attendance_request_class_status_idx'));
        $this->assertTrue(Schema::hasIndex('MASTER_ATTENDANCE_REQUEST', 'attendance_request_schedule_status_idx'));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('MASTER_ATTENDANCE_REQUEST', 'Class_ID'));
        $this->assertFalse(Schema::hasColumn('MASTER_ATTENDANCE_REQUEST', 'Attendance_Type'));
    }

    private function createAttendanceRequestTable(bool $withRoutingColumns): void
    {
        Schema::dropIfExists('MASTER_ATTENDANCE_REQUEST');
        Schema::create('MASTER_ATTENDANCE_REQUEST', function (Blueprint $table) use ($withRoutingColumns): void {
            foreach (WmsMigrationManifest::contracts()['MASTER_ATTENDANCE_REQUEST']['headers'] as $column) {
                $table->string($column)->nullable();
            }
            if ($withRoutingColumns) {
                $table->string('Class_ID')->nullable();
                $table->string('Attendance_Type')->nullable();
            }
        });
    }
}
