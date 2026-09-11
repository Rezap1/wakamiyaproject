<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Providers\GoogleSheetsUserProvider;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\UserService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class H859StudentAccountDeletionIntegrityTest extends TestCase
{
    private H859UserRepository $users;
    private H859StudentRepository $students;
    private H859RoleRepository $roles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roles = new H859RoleRepository([
            ['Role_ID' => 'ROLE-ADMIN-H859', 'Role_Name' => 'ADMINISTRATOR', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-STUDENT-H859', 'Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE'],
            ['Role_ID' => 'ROLE-HR-H859', 'Role_Name' => 'HR', 'Is_Active' => 'TRUE'],
        ]);
        $this->users = new H859UserRepository([
            [
                'User_ID' => 'USR-ADMIN-H859',
                'Username' => 'administrator.h859',
                'Password' => Hash::make('AdminSecret!859'),
                'Full_Name' => 'Administrator H859',
                'Email' => 'administrator.h859@example.test',
                'Role_ID' => 'ROLE-ADMIN-H859',
                'Is_Active' => 'TRUE',
            ],
            [
                'User_ID' => 'USR-STUDENT-H859',
                'Username' => 'student.h859',
                'Password' => Hash::make('StudentSecret!859'),
                'Full_Name' => 'Student H859',
                'Email' => 'student.h859@example.test',
                'Role_ID' => 'ROLE-STUDENT-H859',
                'Is_Active' => 'TRUE',
            ],
            [
                'User_ID' => 'USR-STUDENT-OTHER-H859',
                'Username' => 'student.other.h859',
                'Password' => Hash::make('OtherSecret!859'),
                'Full_Name' => 'Other Student H859',
                'Email' => 'student.other.h859@example.test',
                'Role_ID' => 'ROLE-STUDENT-H859',
                'Is_Active' => 'TRUE',
            ],
        ]);
        $this->students = new H859StudentRepository([
            [
                'Student_ID' => 'STD-STUDENT-H859',
                'User_ID' => 'USR-STUDENT-H859',
                'Full_Name' => 'Student H859',
                'Enrollment_Status' => 'ACTIVE',
                'Graduation_Status' => 'Belum Lulus',
                'Is_Active' => 'TRUE',
            ],
            [
                'Student_ID' => 'STD-STUDENT-OTHER-H859',
                'User_ID' => 'USR-STUDENT-OTHER-H859',
                'Full_Name' => 'Other Student H859',
                'Enrollment_Status' => 'ACTIVE',
                'Graduation_Status' => 'Belum Lulus',
                'Is_Active' => 'TRUE',
            ],
        ]);

        $this->app->instance(UserRepositoryInterface::class, $this->users);
        $this->app->instance(StudentRepositoryInterface::class, $this->students);
        $this->app->instance(RoleRepositoryInterface::class, $this->roles);
        $this->app->instance(EnterpriseEventService::class, Mockery::mock(EnterpriseEventService::class));
        $this->bindProtectedHistoryRepositories();
    }

    public function test_student_account_delete_is_verified_deactivation_and_preserves_every_history_boundary(): void
    {
        $historical = [
            'scores' => [['Score_ID' => 'SCORE-H859', 'Student_ID' => 'STD-STUDENT-H859']],
            'ujian_bab' => [['Score_ID' => 'UJIAN-BAB-H859', 'Student_ID' => 'STD-STUDENT-H859', 'Assessment_Category' => 'UJIAN_BAB']],
            'attendance' => [['Attendance_ID' => 'ATTENDANCE-H859', 'Student_ID' => 'STD-STUDENT-H859']],
            'invoices' => [['Invoice_ID' => 'INVOICE-H859', 'Student_ID' => 'STD-STUDENT-H859']],
            'payments' => [['Payment_ID' => 'PAYMENT-H859', 'Student_ID' => 'STD-STUDENT-H859']],
            'ledger' => [['Transaction_ID' => 'TRANSACTION-H859', 'Reference_ID' => 'PAYMENT-H859']],
            'alumni' => [['Alumni_ID' => 'ALUMNI-H859', 'Student_ID' => 'STD-STUDENT-H859', 'Is_Active' => 'TRUE']],
            'documents' => [['Document_ID' => 'DOCUMENT-H859', 'Reference_ID' => 'STD-STUDENT-H859']],
        ];
        $historicalBefore = $historical;
        $studentBefore = $this->students->findById('STD-STUDENT-H859');
        $otherStudentBefore = $this->students->findById('STD-STUDENT-OTHER-H859');

        $this->actingAs($this->adminUser());
        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('USR-STUDENT-H859');

        $this->delete(route('users.destroy', 'USR-STUDENT-H859'))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('USR-STUDENT-H859')
            ->assertSee('Nonaktif');

        $persistedUser = $this->users->findById('USR-STUDENT-H859');
        $this->assertNotNull($persistedUser);
        $this->assertSame('FALSE', $persistedUser['Is_Active']);
        $this->assertSame($studentBefore, $this->students->findById('STD-STUDENT-H859'));
        $this->assertSame($otherStudentBefore, $this->students->findById('STD-STUDENT-OTHER-H859'));
        $this->assertSame('USR-STUDENT-H859', $this->students->findById('STD-STUDENT-H859')['User_ID']);
        $this->assertSame($historicalBefore, $historical);
        $this->assertSame(0, $this->users->deleteCalls);
        $this->assertSame(0, $this->students->deleteCalls);
        $this->assertGreaterThanOrEqual(2, $this->students->fetchCalls, 'MASTER_STUDENT must be read again after the account update.');
        $this->assertGreaterThanOrEqual(1, $this->students->clearCacheCalls);

        $provider = new GoogleSheetsUserProvider($this->app->make(UserService::class));
        $this->assertNull($provider->retrieveByCredentials([
            'email' => 'student.h859@example.test',
            'password' => 'StudentSecret!859',
        ]));
        $this->assertNull($provider->retrieveById('USR-STUDENT-H859'));
    }

    public function test_student_role_without_profile_is_deactivated_without_fake_student_identity(): void
    {
        $this->users->rows[] = [
            'User_ID' => 'USR-UNMAPPED-H859',
            'Username' => 'unmapped.h859',
            'Password' => Hash::make('UnmappedSecret!859'),
            'Full_Name' => 'Unmapped Student H859',
            'Email' => 'unmapped.h859@example.test',
            'Role_ID' => 'ROLE-STUDENT-H859',
            'Is_Active' => 'TRUE',
        ];

        $this->actingAs($this->adminUser());
        $this->assertTrue($this->app->make(UserService::class)->deleteUser('USR-UNMAPPED-H859'));

        $this->assertSame('FALSE', $this->users->findById('USR-UNMAPPED-H859')['Is_Active']);
        $this->assertNull($this->students->findById('USR-UNMAPPED-H859'));
        $this->assertSame(0, $this->users->deleteCalls);
        $this->assertSame(0, $this->students->deleteCalls);
    }

    public function test_non_administrator_cannot_delete_student_account(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-HR-H859',
            'User_ID' => 'USR-HR-H859',
            'Role_ID' => 'ROLE-HR-H859',
        ]));

        $this->delete(route('users.destroy', 'USR-STUDENT-H859'))->assertForbidden();

        $this->assertSame('TRUE', $this->users->findById('USR-STUDENT-H859')['Is_Active']);
        $this->assertSame(0, $this->users->updateCalls);
        $this->assertSame(0, $this->users->deleteCalls);
    }

    public function test_forged_user_id_cannot_mutate_another_account(): void
    {
        $before = $this->users->rows;
        $this->actingAs($this->adminUser());

        $this->delete(route('users.destroy', 'USR-FORGED-H859'))
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertSame($before, $this->users->rows);
        $this->assertSame(0, $this->users->updateCalls);
        $this->assertSame(0, $this->users->deleteCalls);
    }

    public function test_forged_student_id_is_not_accepted_as_a_user_id(): void
    {
        $before = $this->users->rows;
        $this->actingAs($this->adminUser());

        $this->delete(route('users.destroy', 'STD-STUDENT-OTHER-H859'))
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertSame($before, $this->users->rows);
        $this->assertSame('TRUE', $this->users->findById('USR-STUDENT-OTHER-H859')['Is_Active']);
        $this->assertSame(0, $this->users->updateCalls);
        $this->assertSame(0, $this->users->deleteCalls);
    }

    public function test_repository_identity_mismatch_fails_closed_before_any_write(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $repository->shouldReceive('findById')->once()->with('USR-REQUESTED-H859')->andReturn([
            'User_ID' => 'USR-DIFFERENT-H859',
            'Role_ID' => 'ROLE-STUDENT-H859',
        ]);
        $repository->shouldReceive('update')->never();
        $repository->shouldReceive('delete')->never();
        $service = new UserService($repository, Mockery::mock(EnterpriseEventService::class));
        $this->actingAs($this->adminUser());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Identitas akun pengguna tidak konsisten');
        $service->deleteUser('USR-REQUESTED-H859');
    }

    private function adminUser(): GenericUser
    {
        return new GenericUser([
            'id' => 'USR-ADMIN-H859',
            'User_ID' => 'USR-ADMIN-H859',
            'Role_ID' => 'ROLE-ADMIN-H859',
        ]);
    }

    private function bindProtectedHistoryRepositories(): void
    {
        foreach ([
            \App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\PaymentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AlumniRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\DocumentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface::class,
        ] as $interface) {
            $repository = Mockery::mock($interface);
            foreach (['fetchAll', 'getAll', 'delete', 'hardDelete', 'update', 'softDelete'] as $operation) {
                $repository->shouldNotReceive($operation);
            }
            $this->app->instance($interface, $repository);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

class H859UserRepository implements UserRepositoryInterface
{
    public int $updateCalls = 0;
    public int $deleteCalls = 0;

    public function __construct(public array $rows = []) {}

    public function fetchAll() { return collect(array_values($this->rows)); }
    public function findById(string $id) { return $this->find('User_ID', $id); }
    public function findByEmail(string $email) { return $this->find('Email', $email); }
    public function findByUsername(string $username) { return $this->find('Username', $username); }
    public function create(array $data) { $this->rows[] = $data; return true; }
    public function generateNewId(string $prefix, int $padding = 6): string { return $prefix.str_pad((string) (count($this->rows) + 1), $padding, '0', STR_PAD_LEFT); }

    public function update(string $id, array $data)
    {
        $this->updateCalls++;
        foreach ($this->rows as &$row) {
            if (strcasecmp((string) ($row['User_ID'] ?? ''), $id) === 0) {
                $row = array_merge($row, $data);
                return true;
            }
        }
        return false;
    }

    public function delete(string $id)
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

    public function softDelete(string $id) { return $this->update($id, ['Is_Active' => 'FALSE']); }

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

class H859StudentRepository implements StudentRepositoryInterface
{
    public int $fetchCalls = 0;
    public int $clearCacheCalls = 0;
    public int $deleteCalls = 0;

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
    public function delete(string $id) { $this->deleteCalls++; return false; }
}

class H859RoleRepository implements RoleRepositoryInterface
{
    public function __construct(public array $rows = []) {}

    public function fetchAll() { return collect($this->rows); }
    public function findById(string $id) { return collect($this->rows)->firstWhere('Role_ID', $id); }
    public function create(array $data) { $this->rows[] = $data; return true; }
    public function update(string $id, array $data) { return false; }
}
