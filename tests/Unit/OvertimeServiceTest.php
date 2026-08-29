<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\OvertimeRepositoryInterface;
use App\Services\Core\EmployeeService;
use App\Services\Core\EnterpriseEventService;
use App\Services\HR\OvertimeService;
use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class OvertimeServiceTest extends TestCase
{
    public function test_overtime_duration_and_pay_are_calculated_from_start_to_end_time(): void
    {
        Cache::flush();
        config(['finance.overtime_rate_per_hour' => 25000]);

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $employeeRepo->shouldReceive('fetchAll')->once()->andReturn([
            [
                'Employee_ID' => 'EMP-OT',
                'User_ID' => 'USR-OT',
                'Full_Name' => 'Pegawai Lembur',
                'Is_Active' => 'TRUE',
            ],
        ]);

        $employeeService = Mockery::mock(EmployeeService::class);
        $employeeService->shouldReceive('isEmployeeActive')
            ->with('EMP-OT')
            ->once()
            ->andReturnTrue();

        $enterpriseEvent = Mockery::mock(EnterpriseEventService::class);
        $enterpriseEvent->shouldReceive('dispatch')->once();

        $overtimeRepo = Mockery::mock(OvertimeRepositoryInterface::class);
        $overtimeRepo->shouldReceive('getAll')->once()->andReturn(collect());
        $overtimeRepo->shouldReceive('create')->once()->with(Mockery::on(
            fn (array $record) => $record['Duration_Hours'] === 2.5
                && $record['Hourly_Rate'] === 25000.0
                && $record['Overtime_Pay'] === 62500.0
                && !empty($record['Updated_At'])
        ))->andReturnTrue();

        $service = new OvertimeService($overtimeRepo, $employeeRepo, $employeeService, $enterpriseEvent);

        $this->actingAs(new GenericUser([
            'id' => 'USR-OT',
            'User_ID' => 'USR-OT',
            'Role' => 'EMPLOYEE',
        ]));

        Carbon::setTestNow('2026-08-23 17:00:00');
        $record = $service->createOvertimeRequest([
            'Date' => '2026-08-23',
            'Start_Time' => '18:00',
            'End_Time' => '20:30',
            'Reason' => 'Pekerjaan tambahan',
        ]);

        $this->assertSame(2.5, $record['Duration_Hours']);
        $this->assertSame(62500.0, $record['Overtime_Pay']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
}
