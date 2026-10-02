<?php

namespace Tests\Feature;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Providers\MySqlUserProvider;
use App\Repositories\GoogleSheets\AttendanceRepository;
use App\Repositories\GoogleSheets\PaymentRepository;
use App\Repositories\GoogleSheets\QuizAttemptRepository;
use App\Repositories\GoogleSheets\QuizResultRepository;
use App\Repositories\GoogleSheets\SystemSettingRepository;
use App\Repositories\GoogleSheets\TransactionRepository;
use App\Repositories\GoogleSheets\UserRepository;
use App\Repositories\MySql\BaseMySqlRepository;
use App\Services\Core\RoleService;
use App\Services\Core\UserService;
use App\Services\Finance\PaymentService;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class M2MySqlRuntimePersistenceTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection((string) config('wms_runtime.connection'));
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            || $connection->getDatabaseName() !== 'wms_mysql_test') {
            $this->markTestSkipped('M2 integration tests require the isolated wms_mysql_test database.');
        }

        $connection->beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::connection((string) config('wms_runtime.connection'))->rollBack();
        }

        parent::tearDown();
    }

    public function test_runtime_crud_and_auth_use_mysql_without_google_configuration(): void
    {
        config()->set('services.google.spreadsheet_id', null);
        $repository = app(UserRepository::class);
        $password = Hash::make('m2-password');
        $repository->create([
            'User_ID' => 'USR-M2-AUTH',
            'Username' => 'm2-user',
            'Password' => $password,
            'Full_Name' => 'M2 Runtime User',
            'Email' => 'm2@example.test',
            'Role_ID' => 'ROLE-M2',
            'Is_Active' => 'TRUE',
        ]);

        $this->assertSame('USR-M2-AUTH', $repository->findByEmail('M2@EXAMPLE.TEST')['User_ID']);
        $repository->update('USR-M2-AUTH', ['Full_Name' => 'Updated Runtime User']);
        $this->assertSame('Updated Runtime User', $repository->findById('USR-M2-AUTH')['Full_Name']);

        $provider = new MySqlUserProvider(app(UserService::class));
        $authenticated = $provider->retrieveByCredentials(['login' => 'm2-user', 'password' => 'm2-password']);
        $this->assertNotNull($authenticated);
        $this->assertTrue($provider->validateCredentials($authenticated, ['password' => 'm2-password']));
        $this->assertSame('wms_mysql', config('auth.providers.users.driver'));

        $this->assertTrue($repository->hardDelete('USR-M2-AUTH'));
        $this->assertNull($repository->findById('USR-M2-AUTH'));
    }

    public function test_all_runtime_repositories_read_mysql_with_google_unavailable(): void
    {
        config()->set('services.google.spreadsheet_id', null);
        putenv('GOOGLE_APPLICATION_CREDENTIALS');

        $classes = collect(File::files(app_path('Repositories/GoogleSheets')))
            ->map(fn ($file): string => 'App\\Repositories\\GoogleSheets\\'.pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->filter(fn (string $class): bool => class_exists($class) && ! (new \ReflectionClass($class))->isAbstract())
            ->values();

        $this->assertCount(47, $classes);
        foreach ($classes as $class) {
            $repository = app($class);
            $this->assertInstanceOf(BaseMySqlRepository::class, $repository, $class);
            if (method_exists($repository, 'fetchAll')) {
                $this->assertIsIterable($repository->fetchAll(), $class);
            } elseif (method_exists($repository, 'getAll')) {
                $this->assertIsIterable($repository->getAll(), $class);
            }
        }
    }

    public function test_runtime_id_sequence_is_durable_and_unique(): void
    {
        $repository = app(UserRepository::class);
        $first = $repository->generateNewId('M2USR', 6);
        $second = $repository->generateNewId('M2USR', 6);

        $this->assertNotSame($first, $second);
        $this->assertMatchesRegularExpression('/^M2USR\d{6}$/', $first);
        $this->assertSame(1, (int) DB::table('WMS_ID_SEQUENCE')
            ->where('Sequence_Key', 'master_user:user_id:m2usr')->count());
    }

    public function test_all_fixed_roles_and_master_authenticate_from_mysql_without_password_reset(): void
    {
        $roles = ['ADMINISTRATOR', 'HR', 'ACADEMIC', 'MARKETING', 'FINANCE', 'DIRECTOR', 'TEACHER', 'STUDENT'];
        foreach ($roles as $index => $roleName) {
            DB::table('MASTER_ROLE')->insert([
                'Role_ID' => 'ROLE-M2-'.($index + 1),
                'Role_Name' => $roleName,
                'Is_Active' => 1,
            ]);
        }

        $users = app(UserRepository::class);
        $provider = new MySqlUserProvider(app(UserService::class));
        foreach ([...$roles, 'MASTER'] as $index => $roleName) {
            $roleId = $roleName === 'MASTER' ? 'MASTER' : 'ROLE-M2-'.($index + 1);
            $userId = 'USR-M2-ROLE-'.($index + 1);
            $username = 'm2-role-'.strtolower($roleName);
            $hash = Hash::make('RolePassword!2');
            $users->create([
                'User_ID' => $userId,
                'Username' => $username,
                'Password' => $hash,
                'Role_ID' => $roleId,
                'Is_Active' => 'TRUE',
            ]);

            $authenticated = $provider->retrieveByCredentials([
                'login' => $username,
                'password' => 'RolePassword!2',
            ]);
            $this->assertNotNull($authenticated, $roleName);
            $this->assertSame($hash, $authenticated->getAuthPassword(), $roleName.' hash changed');
            $this->assertSame($roleName, app(RoleService::class)->getRoleById($roleId)['Role_Name']);
        }
    }

    public function test_historical_setting_duplicates_resolve_and_update_first_source_row(): void
    {
        DB::table('MASTER_SYSTEM_SETTING')->insert([
            $this->settingRow(900001, 'SET-M2-DUP', 'first'),
            $this->settingRow(900002, 'SET-M2-DUP', 'second'),
        ]);

        $repository = app(SystemSettingRepository::class);
        $this->assertSame('first', $repository->getById('SET-M2-DUP')['Setting_Value']);

        $repository->update('SET-M2-DUP', ['Setting_Value' => 'updated-first']);
        $values = DB::table('MASTER_SYSTEM_SETTING')
            ->where('Setting_ID', 'SET-M2-DUP')
            ->orderBy('_source_row_number')
            ->pluck('Setting_Value')
            ->all();

        $this->assertSame(['updated-first', 'second'], $values);
    }

    public function test_mysql_unique_guards_prevent_duplicate_quiz_attendance_payment_and_ledger_effects(): void
    {
        $attempts = app(QuizAttemptRepository::class);
        $attempts->create(['Attempt_ID' => 'QAT-M2-1', 'Quiz_ID' => 'QIZ-M2', 'Student_ID' => 'STU-M2']);
        $this->assertDuplicate(fn () => $attempts->create([
            'Attempt_ID' => 'QAT-M2-2', 'Quiz_ID' => 'QIZ-M2', 'Student_ID' => 'STU-M2',
        ]));

        $attendance = app(AttendanceRepository::class);
        $attendance->create($this->attendanceRow('ATT-M2-1'));
        $this->assertDuplicate(fn () => $attendance->create($this->attendanceRow('ATT-M2-2')));

        $payments = app(PaymentRepository::class);
        $payments->create(['Payment_ID' => 'PAY-M2-1', 'Idempotency_Key' => '10000000-0000-4000-8000-000000000001']);
        $this->assertDuplicate(fn () => $payments->create([
            'Payment_ID' => 'PAY-M2-2', 'Idempotency_Key' => '10000000-0000-4000-8000-000000000001',
        ]));

        $transactions = app(TransactionRepository::class);
        $transactions->create($this->transactionRow('TRX-M2-1'));
        $this->assertDuplicate(fn () => $transactions->create($this->transactionRow('TRX-M2-2')));
    }

    public function test_multi_repository_failure_rolls_back_quiz_result_and_attempt_finalization(): void
    {
        $attempts = app(QuizAttemptRepository::class);
        $results = app(QuizResultRepository::class);
        $attempts->create(['Attempt_ID' => 'QAT-M2-ROLLBACK', 'Quiz_ID' => 'QIZ-M2-RB', 'Student_ID' => 'STU-M2-RB', 'Status' => 'IN_PROGRESS']);

        try {
            $attempts->transaction(function () use ($attempts, $results): void {
                $results->create([
                    'Result_ID' => 'QRS-M2-ROLLBACK',
                    'Attempt_ID' => 'QAT-M2-ROLLBACK',
                    'Quiz_ID' => 'QIZ-M2-RB',
                    'Student_ID' => 'STU-M2-RB',
                ]);
                $attempts->update('QAT-M2-ROLLBACK', ['Status' => 'SUBMITTED']);
                throw new RuntimeException('forced failure after both writes');
            });
            $this->fail('Forced transaction failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced failure after both writes', $exception->getMessage());
        }

        $this->assertNull($results->findByIdFresh('QRS-M2-ROLLBACK'));
        $this->assertSame('IN_PROGRESS', $attempts->findByIdFresh('QAT-M2-ROLLBACK')['Status']);
    }

    public function test_finance_verification_rolls_back_when_ledger_creation_fails(): void
    {
        DB::table('FINANCE_INVOICE')->insert([
            'Invoice_ID' => 'INV-M2-ROLLBACK',
            'Student_ID' => 'STU-M2-ROLLBACK',
            'Amount' => '100.0000',
            'Status' => 'Waiting Payment',
            'Invoice_Type' => 'STUDENT',
            'Category' => 'Medical',
            'Is_Active' => 1,
        ]);
        DB::table('FINANCE_PAYMENT')->insert([
            'Payment_ID' => 'PAY-M2-ROLLBACK',
            'Invoice_ID' => 'INV-M2-ROLLBACK',
            'Student_ID' => 'STU-M2-ROLLBACK',
            'Amount_Paid' => '50.0000',
            'Payment_Date' => '2026-10-02',
            'Payment_Method' => 'CASH',
            'Status' => 'Waiting Verification',
            'Is_Active' => 1,
        ]);
        Auth::setUser(new GenericUser([
            'id' => 'USR-M2-FINANCE',
            'User_ID' => 'USR-M2-FINANCE',
            'Role' => 'FINANCE',
        ]));

        try {
            app(PaymentService::class)->verifyPayment(
                'PAY-M2-ROLLBACK',
                'USR-M2-FINANCE',
                'Verified',
                '',
                'ACCOUNT-DOES-NOT-EXIST'
            );
            $this->fail('Ledger account failure should abort verification.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('Waiting Verification', DB::table('FINANCE_PAYMENT')
            ->where('Payment_ID', 'PAY-M2-ROLLBACK')->value('Status'));
        $this->assertSame(0, DB::table('FINANCE_TRANSACTION')
            ->where('Reference_ID', 'PAY-M2-ROLLBACK')->count());
    }

    public function test_runtime_schema_contains_all_m2_integrity_guards(): void
    {
        $this->assertTrue(Schema::hasTable('WMS_ID_SEQUENCE'));
        $this->assertTrue(Schema::hasColumns('MASTER_ATTENDANCE', [
            'Employee_ID', 'User_ID', 'Batch_ID', 'Session_ID', 'Late_Minutes',
            'Verification_Method', 'Device_Info', '_runtime_attendance_key',
        ]));

        $indexes = fn (string $table) => collect(Schema::getIndexes($table))->pluck('name');
        $this->assertTrue($indexes('MASTER_ATTENDANCE')->contains('wms_attendance_business_unique'));
        $this->assertTrue($indexes('QUIZ_ATTEMPT')->contains('wms_quiz_attempt_student_unique'));
        $this->assertTrue($indexes('QUIZ_RESULT')->contains('wms_quiz_result_attempt_unique'));
        $this->assertTrue($indexes('FINANCE_PAYMENT')->contains('wms_payment_idempotency_unique'));
        $this->assertTrue($indexes('FINANCE_TRANSACTION')->contains('wms_transaction_reference_unique'));
    }

    public function test_database_guarantees_hold_under_real_parallel_processes(): void
    {
        DB::connection((string) config('wms_runtime.connection'))->rollBack();
        $this->transactionStarted = false;
        $token = strtoupper(bin2hex(random_bytes(4)));

        try {
            foreach (['attendance'] as $mode) {
                $outcomes = $this->runConcurrentWorkers($mode, $token.$mode);
                $this->assertSame(1, collect($outcomes)->where('ok', true)->count(), $mode);
                $this->assertSame(1, collect($outcomes)->where('ok', false)->count(), $mode);
            }

            $this->seedConcurrentQuizSubmit($token);
            $submissions = $this->runConcurrentWorkers('quiz_submit', $token);
            $this->assertSame(2, collect($submissions)->where('ok', true)->count());
            $this->assertSame(1, DB::table('QUIZ_RESULT')->where('Attempt_ID', "QAT-{$token}")->count());
            $this->assertSame('SUBMITTED', DB::table('QUIZ_ATTEMPT')->where('Attempt_ID', "QAT-{$token}")->value('Status'));

            $ids = $this->runConcurrentWorkers('id', $token.'ID');
            $this->assertSame(2, collect($ids)->where('ok', true)->count());
            $this->assertCount(2, collect($ids)->pluck('value')->unique());

            $this->seedConcurrentPaymentVerification($token);
            $payments = $this->runConcurrentWorkers('payment', $token);
            $this->assertSame(1, collect($payments)->where('ok', true)->count());
            $this->assertSame(1, collect($payments)->where('ok', false)->count());
            $this->assertSame(1, DB::table('FINANCE_PAYMENT')
                ->whereIn('Payment_ID', ["PAY-{$token}-1", "PAY-{$token}-2"])
                ->where('Status', 'Verified')
                ->count());
            $this->assertSame(1, DB::table('FINANCE_TRANSACTION')
                ->where('Reference_Type', 'Payment')
                ->whereIn('Reference_ID', ["PAY-{$token}-1", "PAY-{$token}-2"])
                ->count());
        } finally {
            DB::table('FINANCE_TRANSACTION')->whereIn('Reference_ID', ["PAY-{$token}-1", "PAY-{$token}-2"])->delete();
            DB::table('FINANCE_PAYMENT')->whereIn('Payment_ID', ["PAY-{$token}-1", "PAY-{$token}-2"])->delete();
            DB::table('FINANCE_INVOICE')->where('Invoice_ID', "INV-{$token}")->delete();
            DB::table('MASTER_ACCOUNT')->where('Account_ID', "ACC-{$token}")->delete();
            DB::table('MASTER_STUDENT')->where('Student_ID', "STU-{$token}")->delete();
            DB::table('QUIZ_RESULT')->where('Attempt_ID', 'like', 'QAT-'.$token.'%')->delete();
            DB::table('QUIZ_ATTEMPT')->where('Attempt_ID', 'like', 'QAT-'.$token.'%')->delete();
            DB::table('QUIZ_QUESTION')->where('Quiz_ID', 'like', 'QIZ-'.$token.'%')->delete();
            DB::table('QUIZ')->where('Quiz_ID', 'like', 'QIZ-'.$token.'%')->delete();
            DB::table('MASTER_ATTENDANCE')->where('Student_ID', 'like', 'STU-'.$token.'%')->delete();
            DB::table('WMS_ID_SEQUENCE')->where('Sequence_Key', 'like', '%'.strtolower($token).'%')->delete();
        }
    }

    /** @return array<string, mixed> */
    private function settingRow(int $sourceRow, string $id, string $value): array
    {
        return [
            '_source_row_number' => $sourceRow,
            'Setting_ID' => $id,
            'Setting_Key' => $id,
            'Setting_Value' => $value,
        ];
    }

    /** @return array<string, mixed> */
    private function attendanceRow(string $id): array
    {
        return [
            'Attendance_ID' => $id,
            'Student_ID' => 'STU-M2-ATT',
            'Class_ID' => 'CLS-M2-ATT',
            'Attendance_Date' => '2026-10-02',
            'Attendance_Type' => 'CLASS_QR',
            'Status' => 'PRESENT',
        ];
    }

    /** @return array<string, mixed> */
    private function transactionRow(string $id): array
    {
        return [
            'Transaction_ID' => $id,
            'Reference_Type' => 'Payment',
            'Reference_ID' => 'PAY-M2-LEDGER',
            'Type' => 'Income',
            'Amount' => '1000.0000',
        ];
    }

    private function assertDuplicate(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Database uniqueness guard did not reject the duplicate.');
        } catch (DuplicatePrimaryKeyException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return list<array<string, mixed>> */
    private function runConcurrentWorkers(string $mode, string $token): array
    {
        $gateDirectory = storage_path('framework/testing/m2-concurrency-'.strtolower($token).'-'.bin2hex(random_bytes(3)));
        File::ensureDirectoryExists($gateDirectory);
        $processes = [];

        try {
            foreach ([1, 2] as $slot) {
                $pipes = [];
                $process = proc_open([
                    PHP_BINARY,
                    base_path('tests/Support/m2_concurrency_worker.php'),
                    $mode,
                    $token,
                    (string) $slot,
                    $gateDirectory,
                ], [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ], $pipes, base_path());
                if (! is_resource($process)) {
                    throw new RuntimeException('Unable to start M2 concurrency worker.');
                }
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }

            $deadline = microtime(true) + 15;
            while ((! is_file($gateDirectory.'/ready-1') || ! is_file($gateDirectory.'/ready-2')) && microtime(true) < $deadline) {
                usleep(10_000);
            }
            if (! is_file($gateDirectory.'/ready-1') || ! is_file($gateDirectory.'/ready-2')) {
                throw new RuntimeException('M2 concurrency workers did not reach the barrier.');
            }
            File::put($gateDirectory.'/go', 'go');

            $results = [];
            foreach ($processes as [$process, $pipes]) {
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exit = proc_close($process);
                $lines = array_values(array_filter(preg_split('/\R/', trim($stdout)) ?: []));
                $payload = json_decode((string) end($lines), true);
                if (! is_array($payload)) {
                    throw new RuntimeException("Invalid worker response (exit {$exit}): {$stderr} {$stdout}");
                }
                $results[] = $payload;
            }

            return $results;
        } finally {
            foreach ($processes as [$process, $pipes]) {
                try {
                    $status = proc_get_status($process);
                } catch (\TypeError) {
                    continue;
                }
                if ($status['running'] ?? false) {
                    proc_terminate($process);
                }
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
            }
            File::deleteDirectory($gateDirectory);
        }
    }

    private function seedConcurrentPaymentVerification(string $token): void
    {
        DB::table('MASTER_STUDENT')->insert([
            'Student_ID' => "STU-{$token}",
            'Full_Name' => 'M2 Concurrent Student',
            'Enrollment_Status' => 'ACTIVE',
            'Graduation_Status' => 'BELUM LULUS',
            'Is_Active' => 1,
        ]);
        DB::table('MASTER_ACCOUNT')->insert([
            'Account_ID' => "ACC-{$token}",
            'Account_Code' => "ACC-{$token}",
            'Account_Name' => 'M2 Concurrent Cash',
            'Account_Category' => 'ASSET',
            'Is_Active' => 1,
        ]);
        DB::table('FINANCE_INVOICE')->insert([
            'Invoice_ID' => "INV-{$token}",
            'Student_ID' => "STU-{$token}",
            'Amount' => '100.0000',
            'Status' => 'Waiting Payment',
            'Invoice_Type' => 'STUDENT',
            'Category' => 'Medical',
            'Is_Active' => 1,
        ]);
        foreach ([1, 2] as $slot) {
            DB::table('FINANCE_PAYMENT')->insert([
                'Payment_ID' => "PAY-{$token}-{$slot}",
                'Invoice_ID' => "INV-{$token}",
                'Student_ID' => "STU-{$token}",
                'Amount_Paid' => '70.0000',
                'Payment_Date' => '2026-10-02',
                'Payment_Method' => 'CASH',
                'Status' => 'Waiting Verification',
                'Is_Active' => 1,
            ]);
        }
    }

    private function seedConcurrentQuizSubmit(string $token): void
    {
        DB::table('QUIZ')->insert([
            'Quiz_ID' => "QIZ-{$token}",
            'Teacher_ID' => "TCH-{$token}",
            'Class_ID' => "CLS-{$token}",
            'Title' => 'M2 Concurrent Quiz',
            'Start_At' => '2026-01-01 00:00:00',
            'End_At' => '2030-01-01 00:00:00',
            'Duration_Minutes' => 60,
            'Status' => 'PUBLISHED',
        ]);
        DB::table('QUIZ_QUESTION')->insert([
            'Question_ID' => "QQ-{$token}",
            'Quiz_ID' => "QIZ-{$token}",
            'Question_Text' => 'Concurrency?',
            'Option_A' => 'Yes',
            'Option_B' => 'No',
            'Option_C' => 'Maybe',
            'Option_D' => 'Never',
            'Correct_Option' => 'A',
            'Point' => '10.0000',
            'Sort_Order' => 1,
        ]);
        DB::table('QUIZ_ATTEMPT')->insert([
            'Attempt_ID' => "QAT-{$token}",
            'Quiz_ID' => "QIZ-{$token}",
            'Student_ID' => "STU-{$token}",
            'Started_At' => '2026-10-02 07:00:00',
            'Deadline_At' => '2030-01-01 00:00:00',
            'Status' => 'IN_PROGRESS',
        ]);
    }
}
