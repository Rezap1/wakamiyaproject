<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\AlumniRepositoryInterface;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Providers\GoogleSheetsUserProvider;
use App\Services\Academic\PlacementService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\ProgramService;
use App\Services\Core\StudentService;
use App\Services\Core\UserService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class H860UserHardDeleteSemanticSplitTest extends TestCase
{
    private H860UserRepository $users;
    private H860StudentRepository $students;
    private H860NotificationRepository $notifications;
    private H860RoleRepository $roles;
    private $eventService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roles = new H860RoleRepository([
            ['Role_ID' => 'ROLE-ADMIN-H860', 'Role_Name' => 'ADMINISTRATOR', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-STUDENT-H860', 'Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-HR-H860', 'Role_Name' => 'HR', 'Is_Active' => 'TRUE'],
        ]);
        $this->users = new H860UserRepository([
            $this->userRow('USR-ADMIN-H860', 'ROLE-ADMIN-H860', 'administrator.h860@example.test'),
            $this->userRow('USR-STUDENT-H860', 'ROLE-STUDENT-H860', 'student.h860@example.test'),
            $this->userRow('USR-OTHER-H860', 'ROLE-STUDENT-H860', 'other.h860@example.test'),
        ]);
        $this->students = new H860StudentRepository([
            $this->studentRow('STD-STUDENT-H860', 'USR-STUDENT-H860', 'Student H860'),
            $this->studentRow('STD-OTHER-H860', 'USR-OTHER-H860', 'Other H860'),
        ]);
        $this->notifications = new H860NotificationRepository([
            ['Notification_ID' => 'NOTIF-001-H860', 'User_ID' => 'USR-STUDENT-H860'],
            ['Notification_ID' => 'NOTIF-002-H860', 'Recipient_User_ID' => 'USR-STUDENT-H860'],
            ['Notification_ID' => 'NOTIF-003-H860', 'Recipient_Email' => 'student.h860@example.test'],
            ['Notification_ID' => 'NOTIF-OTHER-H860', 'User_ID' => 'USR-OTHER-H860'],
        ]);

        $this->eventService = Mockery::mock(EnterpriseEventService::class);
        $this->eventService->shouldReceive('dispatch')->zeroOrMoreTimes();

        $this->app->instance(UserRepositoryInterface::class, $this->users);
        $this->app->instance(StudentRepositoryInterface::class, $this->students);
        $this->app->instance(NotificationRepositoryInterface::class, $this->notifications);
        $this->app->instance(RoleRepositoryInterface::class, $this->roles);
        $this->app->instance(EnterpriseEventService::class, $this->eventService);

        $this->bindStudentPageServices();
        $this->bindPreservedHistoryRepositories();
    }

    public function test_pengguna_hard_delete_removes_login_and_private_inbox_but_preserves_student_history(): void
    {
        $studentBefore = $this->students->rows;

        $this->actingAs($this->adminUser());
        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Hapus akun ini secara permanen?')
            ->assertSee('Data historis yang wajib dipertahankan tetap tersimpan');

        $this->delete(route('users.destroy', 'USR-STUDENT-H860'))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertNull($this->users->findById('USR-STUDENT-H860'));
        $this->assertNotNull($this->users->findById('USR-OTHER-H860'));
        $this->assertSame($studentBefore, $this->students->rows);
        $this->assertSame(0, $this->students->deleteCalls);
        $this->assertSame(
            ['NOTIF-001-H860', 'NOTIF-002-H860', 'NOTIF-003-H860'],
            $this->notifications->deletedIds
        );
        $this->assertSame([4, 3, 2], $this->notifications->rowCountsAtDelete);
        $this->assertNotNull($this->notifications->getById('NOTIF-OTHER-H860'));

        $provider = new GoogleSheetsUserProvider($this->app->make(UserService::class));
        $this->assertNull($provider->retrieveById('USR-STUDENT-H860'));
        $this->assertNull($provider->retrieveByCredentials([
            'email' => 'student.h860@example.test',
            'password' => 'Secret!H860',
        ]));

        $this->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('USR-STUDENT-H860')
            ->assertSee('USR-OTHER-H860');
        $this->get(route('students.index'))
            ->assertOk()
            ->assertSee('STD-STUDENT-H860')
            ->assertSee('Student H860');
        $this->assertGreaterThanOrEqual(2, $this->notifications->fetchCalls);
        $this->assertGreaterThanOrEqual(2, $this->students->fetchCalls);
    }

    public function test_delete_plan_rejects_malformed_student_relation_before_any_write(): void
    {
        $this->students->rows[0]['Student_ID'] = '';
        $this->actingAs($this->adminUser());

        $this->delete(route('users.destroy', 'USR-STUDENT-H860'))
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertNotNull($this->users->findById('USR-STUDENT-H860'));
        $this->assertSame(0, $this->users->deleteCalls);
        $this->assertSame([], $this->notifications->deletedIds);
    }

    public function test_self_delete_and_last_privileged_account_are_rejected(): void
    {
        $this->actingAs($this->adminUser());
        $this->delete(route('users.destroy', 'USR-ADMIN-H860'))
            ->assertRedirect()
            ->assertSessionHasErrors('error');
        $this->assertNotNull($this->users->findById('USR-ADMIN-H860'));

        $this->users->rows = [
            $this->userRow('USR-LAST-ADMIN-H860', 'ROLE-ADMIN-H860', 'last.admin.h860@example.test'),
            $this->userRow('USR-NON-PRIV-H860', 'ROLE-HR-H860', 'hr.h860@example.test'),
        ];
        $this->actingAs(new GenericUser([
            'id' => 'USR-SECURITY-OPERATOR-H860',
            'User_ID' => 'USR-SECURITY-OPERATOR-H860',
            'Role_ID' => 'ROLE-ADMIN-H860',
        ]));

        $this->delete(route('users.destroy', 'USR-LAST-ADMIN-H860'))
            ->assertRedirect()
            ->assertSessionHasErrors('error');
        $this->assertNotNull($this->users->findById('USR-LAST-ADMIN-H860'));
    }

    public function test_student_list_delete_remains_separate_and_never_deletes_user_account(): void
    {
        $this->allowStudentLifecycleDelete([], []);
        $this->actingAs($this->adminUser());

        $this->delete(route('students.destroy', 'STD-STUDENT-H860'))
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('success');

        $this->assertNull($this->students->findById('STD-STUDENT-H860'));
        $this->assertNotNull($this->users->findById('USR-STUDENT-H860'));
        $this->assertSame(0, $this->users->deleteCalls);
        $this->assertSame([], $this->notifications->deletedIds);
    }

    public function test_student_list_historical_guard_is_unchanged(): void
    {
        $this->allowStudentLifecycleDelete(
            [['Invoice_ID' => 'INV-H860', 'Student_ID' => 'STD-STUDENT-H860']],
            [['Score_ID' => 'SCORE-H860', 'Student_ID' => 'STD-STUDENT-H860']]
        );
        $this->actingAs($this->adminUser());

        $this->delete(route('students.destroy', 'STD-STUDENT-H860'))
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('error');

        $this->assertNotNull($this->students->findById('STD-STUDENT-H860'));
        $this->assertNotNull($this->users->findById('USR-STUDENT-H860'));
        $this->assertSame(0, $this->students->deleteCalls);
        $this->assertSame(0, $this->users->deleteCalls);
    }

    private function bindStudentPageServices(): void
    {
        $programService = Mockery::mock(ProgramService::class);
        $programService->shouldReceive('getAllPrograms')->zeroOrMoreTimes()->andReturn(collect([
            ['Program_ID' => 'PROG-H860', 'Program_Name' => 'Program H860', 'Program_Code' => 'P860', 'Is_Active' => 'TRUE'],
        ]));
        $batchService = Mockery::mock(BatchService::class);
        $batchService->shouldReceive('getAllBatches')->zeroOrMoreTimes()->andReturn(collect([
            ['Batch_ID' => 'BATCH-H860', 'Batch_Name' => 'Batch H860', 'Batch_Code' => 'B860', 'Is_Active' => 'TRUE'],
        ]));
        $classService = Mockery::mock(ClassService::class);
        $classService->shouldReceive('getAllClasses')->zeroOrMoreTimes()->andReturn(collect([
            ['Class_ID' => 'CLASS-H860', 'Class_Name' => 'Class H860', 'Class_Code' => 'C860', 'Is_Active' => 'TRUE'],
        ]));

        $this->app->instance(ProgramService::class, $programService);
        $this->app->instance(BatchService::class, $batchService);
        $this->app->instance(ClassService::class, $classService);
        $this->app->instance(PlacementService::class, Mockery::mock(PlacementService::class));
    }

    private function bindPreservedHistoryRepositories(): void
    {
        foreach ([
            \App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\PaymentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class,
            AlumniRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\DocumentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AssignmentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AssessmentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AuditLogRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ApprovalRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\WorkflowRepositoryInterface::class,
        ] as $interface) {
            $repository = Mockery::mock($interface);
            foreach (['fetchAll', 'getAll', 'delete', 'hardDelete', 'update', 'softDelete'] as $operation) {
                $repository->shouldNotReceive($operation);
            }
            $this->app->instance($interface, $repository);
        }
    }

    private function allowStudentLifecycleDelete(array $invoices, array $scores): void
    {
        $invoiceRepository = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($invoices));
        $scoreRepository = Mockery::mock(ScoreRepositoryInterface::class);
        $scoreRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($scores));
        $this->app->instance(InvoiceRepositoryInterface::class, $invoiceRepository);
        $this->app->instance(ScoreRepositoryInterface::class, $scoreRepository);

        $programRepository = Mockery::mock(ProgramRepositoryInterface::class);
        $batchRepository = Mockery::mock(BatchRepositoryInterface::class);
        $classRepository = Mockery::mock(ClassRepositoryInterface::class);
        $alumniRepository = Mockery::mock(AlumniRepositoryInterface::class);
        $this->app->instance(StudentService::class, new StudentService(
            $this->students,
            $programRepository,
            $batchRepository,
            $classRepository,
            $this->eventService,
            $alumniRepository
        ));
    }

    private function adminUser(): GenericUser
    {
        return new GenericUser([
            'id' => 'USR-ADMIN-H860',
            'User_ID' => 'USR-ADMIN-H860',
            'Role_ID' => 'ROLE-ADMIN-H860',
        ]);
    }

    private function userRow(string $id, string $roleId, string $email): array
    {
        return [
            'User_ID' => $id,
            'Username' => $email,
            'Password' => Hash::make('Secret!H860'),
            'Full_Name' => $id,
            'Email' => $email,
            'Role_ID' => $roleId,
            'Is_Active' => 'TRUE',
        ];
    }

    private function studentRow(string $studentId, string $userId, string $name): array
    {
        return [
            'Student_ID' => $studentId,
            'User_ID' => $userId,
            'Student_Number' => $studentId,
            'Full_Name' => $name,
            'Gender' => 'Laki-laki',
            'Phone_Number' => '0800000000',
            'Email' => strtolower($name).'@example.test',
            'Program_ID' => 'PROG-H860',
            'Batch_ID' => 'BATCH-H860',
            'Class_ID' => 'CLASS-H860',
            'Registration_Date' => '2026-01-01',
            'Enrollment_Status' => 'ACTIVE',
            'Graduation_Status' => 'Belum Lulus',
            'Is_Active' => 'TRUE',
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

class H860UserRepository implements UserRepositoryInterface
{
    public int $deleteCalls = 0;
    public int $updateCalls = 0;

    public function __construct(public array $rows = []) {}
    public function fetchAll() { return collect(array_values($this->rows)); }
    public function findById(string $id) { return $this->find('User_ID', $id); }
    public function findByEmail(string $email) { return $this->find('Email', $email); }
    public function findByUsername(string $username) { return $this->find('Username', $username); }
    public function create(array $data) { $this->rows[] = $data; return true; }
    public function generateNewId(string $prefix, int $padding = 6): string { return $prefix.str_pad((string) (count($this->rows) + 1), $padding, '0', STR_PAD_LEFT); }
    public function softDelete(string $id) { return $this->update($id, ['Is_Active' => 'FALSE']); }
    public function update(string $id, array $data) { $this->updateCalls++; return false; }
    public function delete(string $id) { return $this->hardDelete($id); }

    public function hardDelete(string $id)
    {
        $this->deleteCalls++;
        foreach ($this->rows as $index => $row) {
            if (strcasecmp((string) ($row['User_ID'] ?? ''), $id) === 0) {
                unset($this->rows[$index]);
                $this->rows = array_values($this->rows);
                return true;
            }
        }
        return false;
    }

    private function find(string $key, string $value): ?array
    {
        foreach ($this->rows as $row) {
            if (strcasecmp(trim((string) ($row[$key] ?? '')), trim($value)) === 0) {
                return $row;
            }
        }
        return null;
    }
}

class H860StudentRepository implements StudentRepositoryInterface
{
    public int $fetchCalls = 0;
    public int $deleteCalls = 0;
    public int $clearCacheCalls = 0;

    public function __construct(public array $rows = []) {}
    public function fetchAll() { $this->fetchCalls++; return collect(array_values($this->rows)); }
    public function findById(string $id) { return collect($this->rows)->first(fn ($row) => strcasecmp((string) ($row['Student_ID'] ?? ''), $id) === 0); }
    public function findByStudentNumber(string $number) { return collect($this->rows)->firstWhere('Student_Number', $number); }
    public function findByNationalId(string $nationalId) { return collect($this->rows)->firstWhere('National_ID', $nationalId); }
    public function generateNewId(string $prefix, int $padding = 6): string { return $prefix.str_pad((string) (count($this->rows) + 1), $padding, '0', STR_PAD_LEFT); }
    public function create(array $data) { $this->rows[] = $data; return true; }
    public function update(string $id, array $data) { return false; }
    public function softDelete(string $id) { return false; }
    public function clearCache() { $this->clearCacheCalls++; }

    public function delete(string $id)
    {
        $this->deleteCalls++;
        foreach ($this->rows as $index => $row) {
            if (strcasecmp((string) ($row['Student_ID'] ?? ''), $id) === 0) {
                unset($this->rows[$index]);
                $this->rows = array_values($this->rows);
                return true;
            }
        }
        return false;
    }
}

class H860NotificationRepository implements NotificationRepositoryInterface
{
    public int $fetchCalls = 0;
    public array $deletedIds = [];
    public array $rowCountsAtDelete = [];

    public function __construct(public array $rows = []) {}
    public function fetchAll() { $this->fetchCalls++; return collect(array_values($this->rows)); }
    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return collect($this->rows)->firstWhere('Notification_ID', $id); }
    public function create(array $data) { $this->rows[] = $data; return true; }
    public function update($id, array $data) { return false; }
    public function delete($id) { return $this->hardDelete($id); }
    public function clearCache() {}

    public function hardDelete($id)
    {
        $this->rowCountsAtDelete[] = count($this->rows);
        foreach ($this->rows as $index => $row) {
            if (strcasecmp((string) ($row['Notification_ID'] ?? ''), (string) $id) === 0) {
                $this->deletedIds[] = $id;
                unset($this->rows[$index]);
                $this->rows = array_values($this->rows);
                return true;
            }
        }
        return false;
    }
}

class H860RoleRepository implements RoleRepositoryInterface
{
    public function __construct(public array $rows = []) {}
    public function fetchAll() { return collect($this->rows); }
    public function findById(string $id) { return collect($this->rows)->firstWhere('Role_ID', $id); }
    public function create(array $data) { $this->rows[] = $data; return true; }
    public function update(string $id, array $data) { return false; }
}
