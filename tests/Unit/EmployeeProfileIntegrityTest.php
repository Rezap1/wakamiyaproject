<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Http\Middleware\ProtectMassAssignment;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Services\Core\EmployeeService;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EmployeeProfileIntegrityTest extends TestCase
{
    private string $mapFile;
    private ?string $mapBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));
        Storage::fake('public');
        $this->mapFile = storage_path('app/employee_photos.json');
        if (is_file($this->mapFile)) {
            $this->mapBackup = file_get_contents($this->mapFile);
            @unlink($this->mapFile);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->mapFile);
        if ($this->mapBackup !== null) {
            file_put_contents($this->mapFile, $this->mapBackup);
        }
        Mockery::close();
        parent::tearDown();
    }

    public function test_photo_is_committed_only_after_employee_persistence(): void
    {
        $repo = Mockery::mock(EmployeeRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);
        $repo->shouldReceive('fetchAll')->once()->andReturn(collect());
        $repo->shouldReceive('generateEmployeeNumber')->once()->andReturn('EMP-2026-001');
        $repo->shouldReceive('create')->once()->with(Mockery::on(fn ($row) => ($row['Employee_ID'] ?? '') === 'EMP000001'))->andReturn(true);
        $repo->shouldReceive('clearCache')->once();
        $event->shouldReceive('dispatch')->zeroOrMoreTimes();

        $service = new EmployeeService($repo, $event);
        $result = $service->createEmployee([
            'Department_ID' => 'DEP001',
            'Position_ID' => 'POS001',
            'Join_Date' => '2026-09-01',
            'Employment_Status' => 'ACTIVE',
            'Profile_Photo' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $this->assertSame('storage/profiles/' . basename((string) $result['Profile_Photo']), $result['Profile_Photo']);
        $this->assertMatchesRegularExpression('/^storage\/profiles\/employee_EMP000001_[a-f0-9-]+\.png$/', $result['Profile_Photo']);
        $this->assertSame($result['Profile_Photo'], json_decode((string) file_get_contents($this->mapFile), true)['EMP000001']);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $result['Profile_Photo']));
    }

    public function test_failed_employee_write_keeps_previous_photo_metadata_and_cleans_staged_file(): void
    {
        Storage::disk('public')->put('profiles/employee_EMP000001_old.jpg', 'old');
        file_put_contents($this->mapFile, json_encode(['EMP000001' => 'storage/profiles/employee_EMP000001_old.jpg']));

        $repo = Mockery::mock(EmployeeRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);
        $repo->shouldReceive('findById')->once()->with('EMP000001')->andReturn([
            'Employee_ID' => 'EMP000001',
            'User_ID' => '',
            'Full_Name' => 'Pegawai',
            'Phone_Number' => '081234',
        ]);
        $repo->shouldReceive('updateRow')->twice()->andReturn(false, true);
        $repo->shouldNotReceive('clearCache');
        $event->shouldNotReceive('dispatch');

        $service = new EmployeeService($repo, $event);
        $this->expectException(\Throwable::class);
        try {
            $service->updateEmployee('EMP000001', [
                'Department_ID' => 'DEP001',
                'Position_ID' => 'POS001',
                'Join_Date' => '2026-09-01',
                'Employment_Status' => 'ACTIVE',
                'Profile_Photo' => UploadedFile::fake()->image('new.jpg'),
            ]);
        } finally {
            $map = json_decode((string) file_get_contents($this->mapFile), true);
            $this->assertSame('storage/profiles/employee_EMP000001_old.jpg', $map['EMP000001']);
            Storage::disk('public')->assertExists('profiles/employee_EMP000001_old.jpg');
            $this->assertCount(1, Storage::disk('public')->files('profiles'));
        }
    }

    public function test_empty_user_phone_never_erases_existing_employee_phone(): void
    {
        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);
        $this->app->instance(UserRepositoryInterface::class, $userRepo);

        $existing = ['Employee_ID' => 'EMP000001', 'User_ID' => 'USR-1', 'Full_Name' => 'Pegawai', 'Email' => 'p@example.test', 'Phone_Number' => '081234'];
        $employeeRepo->shouldReceive('findById')->once()->with('EMP000001')->andReturn($existing);
        $employeeRepo->shouldReceive('fetchAll')->once()->andReturn(collect([$existing]));
        $userRepo->shouldReceive('findById')->once()->with('USR-1')->andReturn(['User_ID' => 'USR-1', 'Full_Name' => 'Pegawai', 'Email' => 'p@example.test', 'Phone_Number' => '']);
        $userRepo->shouldReceive('update')->once()->with('USR-1', Mockery::on(fn ($row) => ($row['Employee_ID'] ?? '') === 'EMP000001'))->andReturn(true);
        $employeeRepo->shouldReceive('updateRow')->once()->with('EMP000001', Mockery::on(fn ($row) => ($row['Phone_Number'] ?? '') === '081234'
            && !array_key_exists('Address', $row)
            && !array_key_exists('Birth_Date', $row)
            && !array_key_exists('Notes', $row)))->andReturn(true);
        $employeeRepo->shouldReceive('clearCache')->once();
        $event->shouldReceive('dispatch')->zeroOrMoreTimes();

        $service = new EmployeeService($employeeRepo, $event);
        $this->assertTrue($service->updateEmployee('EMP000001', [
            'User_ID' => 'USR-1',
            'Department_ID' => 'DEP001',
            'Position_ID' => 'POS001',
            'Join_Date' => '2026-09-01',
            'Employment_Status' => 'ACTIVE',
        ]));
    }

    public function test_explicit_phone_update_is_coordinated_to_user_and_employee(): void
    {
        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);
        $this->app->instance(UserRepositoryInterface::class, $userRepo);

        $existing = ['Employee_ID' => 'EMP000001', 'User_ID' => 'USR-1', 'Full_Name' => 'Pegawai', 'Email' => 'p@example.test', 'Phone_Number' => '081234'];
        $employeeRepo->shouldReceive('findById')->once()->with('EMP000001')->andReturn($existing);
        $employeeRepo->shouldReceive('fetchAll')->once()->andReturn(collect([$existing]));
        $userRepo->shouldReceive('findById')->once()->with('USR-1')->andReturn(['User_ID' => 'USR-1', 'Full_Name' => 'Pegawai', 'Email' => 'p@example.test', 'Phone_Number' => '081234']);
        $userRepo->shouldReceive('update')->once()->with('USR-1', Mockery::on(fn ($row) => ($row['Phone_Number'] ?? '') === '089999'))->andReturn(true);
        $employeeRepo->shouldReceive('updateRow')->once()->with('EMP000001', Mockery::on(fn ($row) => ($row['Phone_Number'] ?? '') === '089999'))->andReturn(true);
        $employeeRepo->shouldReceive('clearCache')->once();
        $event->shouldReceive('dispatch')->zeroOrMoreTimes();

        $service = new EmployeeService($employeeRepo, $event);
        $this->assertTrue($service->updateEmployee('EMP000001', [
            'User_ID' => 'USR-1',
            'Phone_Number' => '089999',
            'Department_ID' => 'DEP001',
            'Position_ID' => 'POS001',
            'Join_Date' => '2026-09-01',
            'Employment_Status' => 'ACTIVE',
        ]));
    }

    public function test_forged_employee_id_is_not_sent_to_update_repository(): void
    {
        $employeeRepo = Mockery::mock(EmployeeRepositoryInterface::class);
        $event = Mockery::mock(EnterpriseEventService::class);
        $existing = ['Employee_ID' => 'EMP000001', 'User_ID' => '', 'Phone_Number' => '081234'];
        $employeeRepo->shouldReceive('findById')->once()->with('EMP000001')->andReturn($existing);
        $employeeRepo->shouldReceive('updateRow')->once()->with('EMP000001', Mockery::on(fn ($row) => !array_key_exists('Employee_ID', $row)))->andReturn(true);
        $employeeRepo->shouldReceive('clearCache')->once();
        $event->shouldReceive('dispatch')->zeroOrMoreTimes();

        $service = new EmployeeService($employeeRepo, $event);
        $this->assertTrue($service->updateEmployee('EMP000001', [
            'Employee_ID' => 'EMP999999',
            'Department_ID' => 'DEP001',
            'Position_ID' => 'POS001',
            'Join_Date' => '2026-09-01',
            'Employment_Status' => 'ACTIVE',
        ]));
    }

    public function test_employee_request_contract_uses_verified_schema_and_server_owned_primary_key(): void
    {
        $storeRules = (new StoreEmployeeRequest())->rules();
        $updateRules = (new UpdateEmployeeRequest())->rules();

        foreach ([$storeRules, $updateRules] as $rules) {
            $this->assertArrayHasKey('Phone_Number', $rules);
            $this->assertArrayHasKey('Profile_Photo', $rules);
            $this->assertArrayHasKey('Birth_Place', $rules);
            $this->assertArrayHasKey('Birth_Date', $rules);
            $this->assertArrayHasKey('Gender', $rules);
            $this->assertArrayHasKey('Department_ID', $rules);
            $this->assertArrayHasKey('Position_ID', $rules);
            $this->assertArrayNotHasKey('Employee_ID', $rules);
        }
        $this->assertStringContainsString('webp', (string) $updateRules['Profile_Photo']);
        $this->assertStringContainsString('max:5120', (string) $updateRules['Profile_Photo']);
    }

    public function test_mass_assignment_guard_keeps_valid_employee_fields_but_removes_server_owned_audit_fields(): void
    {
        $request = Request::create('/employees/EMP000001', 'PUT', [
            'Employment_Status' => 'ACTIVE',
            'Phone_Number' => '081234',
            'User_ID' => 'USR-1',
            'Gender' => 'Laki-laki',
            'Department_ID' => 'DEP001',
            'Position_ID' => 'POS001',
            'Updated_By' => 'FORGED',
        ]);
        $route = (new Route('PUT', '/employees/{id}', fn () => response('ok')))->name('employees.update');
        $request->setRouteResolver(fn () => $route);

        $captured = [];
        (new ProtectMassAssignment())->handle($request, function (Request $next) use (&$captured) {
            $captured = $next->all();
            return response('ok');
        });

        foreach (['Employment_Status', 'Phone_Number', 'User_ID', 'Gender', 'Department_ID', 'Position_ID'] as $field) {
            $this->assertArrayHasKey($field, $captured);
        }
        $this->assertArrayNotHasKey('Updated_By', $captured);
    }

    public function test_employee_surfaces_use_localized_upload_and_phone_fallbacks(): void
    {
        $create = file_get_contents(resource_path('views/employees/create.blade.php'));
        $edit = file_get_contents(resource_path('views/employees/edit.blade.php'));
        $index = file_get_contents(resource_path('views/employees/index.blade.php'));
        $show = file_get_contents(resource_path('views/employees/show.blade.php'));
        $profile = file_get_contents(resource_path('views/profile/index.blade.php'));

        foreach ([$create, $edit] as $form) {
            $this->assertStringContainsString('Pilih File', $form);
            $this->assertStringContainsString('Belum ada file dipilih', $form);
            $this->assertStringContainsString('name="Phone_Number"', $form);
        }
        foreach ([$index, $show, $profile] as $surface) {
            $this->assertStringContainsString('Belum diisi', $surface);
        }
    }
}
