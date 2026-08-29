<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Services\Academic\AttendanceService;
use App\Services\Core\EnterpriseEventService;
use Mockery;
use Tests\TestCase;

class AttendanceDataFlowTest extends TestCase
{
    public function test_manual_attendance_create_normalizes_status_and_sets_check_in_time(): void
    {
        $repository = Mockery::mock(AttendanceRepositoryInterface::class);
        $repository->shouldReceive('fetchAll')->once()->andReturn(collect());
        $repository->shouldReceive('generateNewId')->once()->with('ATT', 6)->andReturn('ATT000001');
        $repository->shouldReceive('create')->once()->with(Mockery::on(function ($payload) {
            return ($payload['Attendance_ID'] ?? '') === 'ATT000001'
                && ($payload['Status'] ?? '') === 'PRESENT'
                && !empty($payload['Check_In_Time'])
                && ($payload['Is_Active'] ?? '') === 'TRUE';
        }))->andReturnUsing(fn ($payload) => $payload);
        $repository->shouldReceive('clearCache')->once();

        $service = new AttendanceService($repository, Mockery::mock(EnterpriseEventService::class));
        $result = $service->markAttendance([
            'Student_ID' => 'STD001',
            'Class_ID' => 'CLS001',
            'Schedule_ID' => 'CLS001',
            'Attendance_Date' => '2026-08-23',
            'Status' => 'Hadir',
        ]);

        $this->assertSame('PRESENT', $result['Status']);
    }

    public function test_manual_attendance_updates_existing_student_day_instead_of_creating_duplicate(): void
    {
        $repository = Mockery::mock(AttendanceRepositoryInterface::class);
        $repository->shouldReceive('fetchAll')->once()->andReturn(collect([
            [
                'Attendance_ID' => 'ATT000010',
                'Student_ID' => 'STD001',
                'Class_ID' => 'CLS001',
                'Schedule_ID' => 'CLS001',
                'Attendance_Date' => '2026-08-23',
                'Status' => 'PRESENT',
                'Is_Active' => 'TRUE',
            ],
        ]));
        $repository->shouldReceive('update')->once()->with('ATT000010', Mockery::on(function ($payload) {
            return ($payload['Status'] ?? '') === 'ABSENT'
                && !empty($payload['Updated_At']);
        }))->andReturn(true);
        $repository->shouldNotReceive('create');
        $repository->shouldReceive('clearCache')->once();

        $service = new AttendanceService($repository, Mockery::mock(EnterpriseEventService::class));

        $this->assertTrue($service->markAttendance([
            'Student_ID' => 'STD001',
            'Class_ID' => 'CLS001',
            'Schedule_ID' => 'CLS001',
            'Attendance_Date' => '2026-08-23',
            'Status' => 'Alpha',
        ]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
