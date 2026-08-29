<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Services\Core\EmployeeService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\StudentService;
use App\Services\Core\SystemSettingService;
use App\Services\Core\UserService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ProfileContactSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_student_create_uses_user_contact_when_profile_contact_is_empty(): void
    {
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $programRepo = Mockery::mock(ProgramRepositoryInterface::class);
        $batchRepo = Mockery::mock(BatchRepositoryInterface::class);
        $classRepo = Mockery::mock(ClassRepositoryInterface::class);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);
        $settings = Mockery::mock(SystemSettingService::class);

        $this->app->instance(StudentRepositoryInterface::class, $studentRepo);
        $this->app->instance(ProgramRepositoryInterface::class, $programRepo);
        $this->app->instance(BatchRepositoryInterface::class, $batchRepo);
        $this->app->instance(ClassRepositoryInterface::class, $classRepo);
        $this->app->instance(UserRepositoryInterface::class, $userRepo);
        $this->app->instance(SystemSettingService::class, $settings);

        $studentRepo->shouldReceive('findByStudentNumber')->once()->with('NIS001')->andReturn(null);
        $programRepo->shouldReceive('findById')->once()->with('PRG001')->andReturn(['Program_ID' => 'PRG001', 'Is_Active' => 'TRUE']);
        $batchRepo->shouldReceive('findById')->once()->with('BTC001')->andReturn(['Batch_ID' => 'BTC001', 'Is_Active' => 'TRUE']);
        $classRepo->shouldReceive('findById')->once()->with('CLS001')->andReturn(['Class_ID' => 'CLS001', 'Is_Active' => 'TRUE']);
        $studentRepo->shouldReceive('generateNewId')->once()->with('STD', 6)->andReturn('STD000001');
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect());
        $userRepo->shouldReceive('findById')->once()->with('USR-STUDENT')->andReturn([
            'User_ID' => 'USR-STUDENT',
            'Full_Name' => 'Siswa Contoh',
            'Email' => 'siswa@example.test',
            'Phone_Number' => '081234567890',
            'Role_ID' => 'ROL000008',
        ]);
        $studentRepo->shouldReceive('create')->once()->with(Mockery::on(function ($row) {
            return $row['Email'] === 'siswa@example.test'
                && $row['Phone_Number'] === '081234567890'
                && $row['Full_Name'] === 'Siswa Contoh';
        }))->andReturn(true);
        $event->shouldReceive('dispatch')->zeroOrMoreTimes();
        $settings->shouldReceive('getDefaultTuitionFee')->once()->andReturn(0);

        $service = new StudentService($studentRepo, $programRepo, $batchRepo, $classRepo, $event);
        $result = $service->createStudent([
            'Student_Number' => 'NIS001',
            'Registration_Date' => '2026-08-23',
            'User_ID' => 'USR-STUDENT',
            'Phone_Number' => '',
            'Email' => '',
            'Education' => 'SMA / SMK Sederajat',
            'Program_ID' => 'PRG001',
            'Batch_ID' => 'BTC001',
            'Class_ID' => 'CLS001',
            'Enrollment_Status' => 'Aktif Belajar',
        ]);

        $this->assertSame('siswa@example.test', $result['Email']);
        $this->assertSame('081234567890', $result['Phone_Number']);
    }

    public function test_employee_update_refreshes_contact_from_selected_user(): void
    {
        Cache::flush();

        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);

        $this->app->instance(EmployeeRepositoryInterface::class, $employeeRepo);
        $this->app->instance(UserRepositoryInterface::class, $userRepo);

        $existingEmployee = [
            'Employee_ID' => 'EMP000001',
            'User_ID' => 'USR-OLD',
            'Full_Name' => 'Nama Lama',
            'Email' => 'lama@example.test',
            'Phone_Number' => '0800000000',
        ];

        $employeeRepo->shouldReceive('findById')->once()->with('EMP000001')->andReturn($existingEmployee);
        $userRepo->shouldReceive('findById')->once()->with('USR-NEW')->andReturn([
            'User_ID' => 'USR-NEW',
            'Full_Name' => 'Nama Baru',
            'Email' => 'baru@example.test',
            'Phone_Number' => '0899999999',
        ]);
        $userRepo->shouldReceive('update')->once()->with('USR-NEW', Mockery::on(function ($row) {
            return $row['Employee_ID'] === 'EMP000001';
        }))->andReturn(true);
        $userRepo->shouldReceive('update')->once()->with('USR-OLD', Mockery::on(function ($row) {
            return $row['Employee_ID'] === '';
        }))->andReturn(true);
        $employeeRepo->shouldReceive('fetchAll')->once()->andReturn(collect([$existingEmployee]));
        $employeeRepo->shouldReceive('updateRow')->once()->with('EMP000001', Mockery::on(function ($row) {
            return $row['User_ID'] === 'USR-NEW'
                && $row['Full_Name'] === 'Nama Baru'
                && $row['Email'] === 'baru@example.test'
                && $row['Phone_Number'] === '0899999999';
        }))->andReturn(true);
        $employeeRepo->shouldReceive('clearCache')->once();
        $event->shouldReceive('dispatch')->zeroOrMoreTimes();

        $service = new EmployeeService($employeeRepo, $event);
        $this->assertTrue($service->updateEmployee('EMP000001', [
            'User_ID' => 'USR-NEW',
            'Department_ID' => 'DEP001',
            'Position_ID' => 'POS001',
            'Join_Date' => '2026-08-23',
            'Employment_Status' => 'Tetap (PKWTT)',
        ]));
    }

    public function test_employee_create_uses_user_contact_without_retyping_email_or_phone(): void
    {
        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);

        $this->app->instance(EmployeeRepositoryInterface::class, $employeeRepo);
        $this->app->instance(UserRepositoryInterface::class, $userRepo);

        $employeeRepo->shouldReceive('fetchAll')->twice()->andReturn(collect());
        $employeeRepo->shouldReceive('generateEmployeeNumber')->once()->with('EMP', date('Y'), 3)->andReturn('EMP-' . date('Y') . '-001');
        $userRepo->shouldReceive('findById')->once()->with('USR-EMPLOYEE')->andReturn([
            'User_ID' => 'USR-EMPLOYEE',
            'Full_Name' => 'Pegawai Contoh',
            'Email' => 'pegawai@example.test',
            'Phone_Number' => '0877777777',
        ]);
        $userRepo->shouldReceive('update')->once()->with('USR-EMPLOYEE', Mockery::on(function ($row) {
            return $row['Employee_ID'] === 'EMP000001';
        }))->andReturn(true);
        $employeeRepo->shouldReceive('create')->once()->with(Mockery::on(function ($row) {
            return $row['Full_Name'] === 'Pegawai Contoh'
                && $row['Email'] === 'pegawai@example.test'
                && $row['Phone_Number'] === '0877777777'
                && $row['Employee_Number'] === 'EMP-' . date('Y') . '-001';
        }))->andReturn(true);
        $employeeRepo->shouldReceive('clearCache')->once();
        $event->shouldReceive('dispatch')->zeroOrMoreTimes();

        $service = new EmployeeService($employeeRepo, $event);
        $result = $service->createEmployee([
            'User_ID' => 'USR-EMPLOYEE',
            'Department_ID' => 'DEP001',
            'Position_ID' => 'POS001',
            'Join_Date' => '2026-08-23',
            'Employment_Status' => 'Tetap (PKWTT)',
        ]);

        $this->assertSame('pegawai@example.test', $result['Email']);
        $this->assertSame('0877777777', $result['Phone_Number']);
    }
}
