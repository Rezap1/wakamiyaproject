<?php

namespace Tests\Feature\GoogleSheets;

use Tests\TestCase;
use App\Services\Core\UserService;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;

class UserServiceTest extends TestCase
{
    protected $userService;
    protected $userRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));

        $this->userRepositoryMock = Mockery::mock(UserRepositoryInterface::class);
        $this->app->instance(UserRepositoryInterface::class, $this->userRepositoryMock);
        
        $this->userService = $this->app->make(UserService::class);
    }

    public function test_can_create_user_with_generated_id()
    {
        $this->userRepositoryMock->shouldReceive('generateNewId')
            ->once()
            ->with('USR', 6)
            ->andReturn('USR000001');

        $this->userRepositoryMock->shouldReceive('create')
            ->once()
            ->andReturn(true);

        $data = [
            'Username' => 'testuser',
            'Full_Name' => 'Test User',
            'Email' => 'test@wakamiya.co.id',
            'Password' => 'Secret!123',
            'Role_ID' => 'ROL000002',
        ];

        $result = $this->userService->createUser($data);

        $this->assertEquals('USR000001', $result['User_ID']);
        $this->assertEquals('testuser', $result['Username']);
        $this->assertTrue(Hash::check('Secret!123', $result['Password']));
        $this->assertEquals('TRUE', $result['Is_Active']);
    }

    public function test_delete_user_calls_repository_delete()
    {
        $userId = 'USR000001';

        $this->bindCascadeRepositories();

        $this->userRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($userId)
            ->andReturn([
                'User_ID' => $userId,
                'Username' => 'testuser',
                'Full_Name' => 'Test User',
                'Email' => 'test@wakamiya.co.id',
                'Employee_ID' => '',
            ]);

        $this->userRepositoryMock->shouldReceive('delete')
            ->once()
            ->with($userId)
            ->andReturn(true);

        $result = $this->userService->deleteUser($userId);
        
        $this->assertTrue($result);
    }

    public function test_delete_user_cascades_all_owned_records()
    {
        $userId = 'USR000001';

        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('payments/proof.png', 'payment');
        Storage::disk('local')->put('attendance-evidence/letter.png', 'letter');
        Storage::disk('public')->put('documents/certificates/certificate.pdf', 'certificate');

        $this->bindCascadeRepositories([
            \App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class => [
                'rows' => [['Employee_ID' => 'EMP001', 'User_ID' => $userId, 'Email' => 'user@example.test']],
                'delete' => ['EMP001'],
            ],
            \App\Interfaces\GoogleSheets\StudentRepositoryInterface::class => [
                'rows' => [['Student_ID' => 'STU001', 'User_ID' => $userId, 'Email' => 'user@example.test']],
                'delete' => ['STU001'],
            ],
            \App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class => [
                'rows' => [['Teacher_ID' => 'TCH001', 'Employee_ID' => 'EMP001', 'User_ID' => $userId]],
                'delete' => ['TCH001'],
            ],
            \App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class => [
                'rows' => [['Payroll_ID' => 'PAYROLL001', 'Employee_ID' => 'EMP001']],
                'delete' => ['PAYROLL001'],
            ],
            \App\Interfaces\GoogleSheets\LeaveRepositoryInterface::class => [
                'rows' => [
                    ['Leave_ID' => 'LEV001', 'Employee_ID' => 'EMP001'],
                    ['Leave_ID' => 'LEV999', 'Employee_ID' => 'EMP999'],
                ],
                'delete' => ['LEV001'],
            ],
            \App\Interfaces\GoogleSheets\OvertimeRepositoryInterface::class => [
                'rows' => [
                    ['Overtime_ID' => 'OT001', 'Employee_ID' => 'EMP001'],
                    ['Overtime_ID' => 'OT999', 'Employee_ID' => 'EMP999'],
                ],
                'delete' => ['OT001'],
            ],
            \App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class => [
                'rows' => [['Invoice_ID' => 'INV001', 'Student_ID' => 'STU001']],
                'delete' => ['INV001'],
            ],
            \App\Interfaces\GoogleSheets\PaymentRepositoryInterface::class => [
                'rows' => [['Payment_ID' => 'PMT001', 'Student_ID' => 'STU001', 'Invoice_ID' => 'INV001', 'Proof_File' => 'payments/proof.png']],
                'delete' => ['PMT001'],
            ],
            \App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class => [
                'rows' => [
                    ['Transaction_ID' => 'TRX001', 'Reference_ID' => 'PMT001'],
                    ['Transaction_ID' => 'TRX002', 'Reference_ID' => 'PAYROLL001'],
                ],
                'delete' => ['TRX001', 'TRX002'],
            ],
            \App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class => [
                'rows' => [
                    ['Score_ID' => 'SCR001', 'Student_ID' => 'STU001'],
                    ['Score_ID' => 'SCR002', 'Teacher_ID' => 'TCH001'],
                ],
                'delete' => ['SCR001', 'SCR002'],
            ],
            \App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class => [
                'rows' => [
                    ['Attendance_ID' => 'ATT001', 'Employee_ID' => 'EMP001'],
                    ['Attendance_ID' => 'ATT002', 'Student_ID' => 'STU001'],
                ],
                'delete' => ['ATT001', 'ATT002'],
            ],
            \App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface::class => [
                'rows' => [['Request_ID' => 'REQ001', 'Student_ID' => 'STU001', 'Evidence_URL' => 'storage/attendance-evidence/letter.png']],
                'delete' => ['REQ001'],
            ],
            \App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface::class => [
                'rows' => [['Enrollment_ID' => 'ENR001', 'Student_ID' => 'STU001']],
                'delete' => ['ENR001'],
            ],
            \App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class => [
                'rows' => [['Schedule_ID' => 'SCH001', 'Teacher_ID' => 'TCH001']],
                'delete' => ['SCH001'],
            ],
            \App\Interfaces\GoogleSheets\AssignmentRepositoryInterface::class => [
                'rows' => [['Assignment_ID' => 'ASN001', 'Teacher_ID' => 'TCH001']],
                'delete' => ['ASN001'],
            ],
            \App\Interfaces\GoogleSheets\AssessmentRepositoryInterface::class => [
                'rows' => [['Assessment_ID' => 'ASM001', 'Teacher_ID' => 'TCH001']],
                'delete' => ['ASM001'],
            ],
            \App\Interfaces\GoogleSheets\DocumentRepositoryInterface::class => [
                'rows' => [['Document_ID' => 'DOC001', 'Student_ID' => 'STU001', 'File_Path' => 'documents/certificates/certificate.pdf']],
                'delete' => ['DOC001'],
            ],
            \App\Interfaces\GoogleSheets\ApprovalRepositoryInterface::class => [
                'rows' => [['Approval_ID' => 'APR001', 'Reference_ID' => 'INV001']],
                'delete' => ['APR001'],
            ],
            \App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface::class => [
                'rows' => [['History_ID' => 'APH001', 'Reference_ID' => 'PMT001']],
                'delete' => ['APH001'],
            ],
            \App\Interfaces\GoogleSheets\WorkflowRepositoryInterface::class => [
                'rows' => [['Workflow_ID' => 'WFL001', 'Requester_ID' => $userId]],
                'delete' => ['WFL001'],
            ],
            \App\Interfaces\GoogleSheets\AuditLogRepositoryInterface::class => [
                'rows' => [['Audit_ID' => 'AUD001', 'User_ID' => $userId]],
                'delete' => ['AUD001'],
            ],
            \App\Interfaces\GoogleSheets\NotificationRepositoryInterface::class => [
                'rows' => [
                    ['Notification_ID' => 'NTF001', 'User_ID' => $userId],
                    ['Notification_ID' => 'NTF002', 'Recipient_Email' => 'user@example.test'],
                ],
                'delete' => ['NTF001', 'NTF002'],
            ],
        ]);

        $this->userRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($userId)
            ->andReturn([
                'User_ID' => $userId,
                'Username' => 'user@example.test',
                'Full_Name' => 'Clean Delete User',
                'Email' => 'user@example.test',
                'Employee_ID' => 'EMP001',
            ]);

        $this->userRepositoryMock->shouldReceive('delete')
            ->once()
            ->with($userId)
            ->andReturn(true);

        $this->assertTrue($this->userService->deleteUser($userId));
        Storage::disk('local')->assertMissing('payments/proof.png');
        Storage::disk('local')->assertMissing('attendance-evidence/letter.png');
        Storage::disk('public')->assertMissing('documents/certificates/certificate.pdf');
    }

    public function test_delete_user_returns_false_when_user_not_found()
    {
        $userId = 'USR404';

        $this->userRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($userId)
            ->andReturn(null);

        $this->userRepositoryMock->shouldReceive('delete')->never();

        $result = $this->userService->deleteUser($userId);

        $this->assertFalse($result);
    }

    public function test_delete_user_does_not_claim_student_profile_by_parent_email(): void
    {
        $userId = 'USR-PARENT';

        $this->bindCascadeRepositories([
            \App\Interfaces\GoogleSheets\StudentRepositoryInterface::class => [
                'rows' => [[
                    'Student_ID' => 'STU-OTHER',
                    'User_ID' => 'USR-STUDENT',
                    'Email' => 'student@example.test',
                    'Parent_Email' => 'parent@example.test',
                ]],
            ],
        ]);

        $this->userRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($userId)
            ->andReturn([
                'User_ID' => $userId,
                'Username' => 'parent@example.test',
                'Email' => 'parent@example.test',
            ]);

        $this->userRepositoryMock->shouldReceive('delete')
            ->once()
            ->with($userId)
            ->andReturn(true);

        $this->assertTrue($this->userService->deleteUser($userId));
    }

    public function test_update_user_formats_data_correctly()
    {
        $userId = 'USR000001';
        $updateData = [
            'Full_Name' => 'Updated Name',
            'Is_Active' => 'FALSE'
        ];

        $this->userRepositoryMock->shouldReceive('update')
            ->once()
            ->with($userId, Mockery::on(function ($data) {
                return $data['Full_Name'] === 'Updated Name' 
                    && $data['Is_Active'] === 'FALSE' 
                    && isset($data['Updated_At']);
            }))
            ->andReturn(true);

        $result = $this->userService->updateUser($userId, $updateData);
        $this->assertTrue($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindCascadeRepositories(array $config = []): void
    {
        $interfaces = [
            \App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\StudentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\TeacherRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\LeaveRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\OvertimeRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\PaymentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ScoreRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AttendanceRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AttendanceRequestRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ScheduleRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AssignmentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AssessmentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\DocumentRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ApprovalRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\WorkflowRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\AuditLogRepositoryInterface::class,
            \App\Interfaces\GoogleSheets\NotificationRepositoryInterface::class,
        ];

        foreach ($interfaces as $interface) {
            $rows = collect($config[$interface]['rows'] ?? []);
            $deleteIds = $config[$interface]['delete'] ?? [];
            $mock = Mockery::mock($interface);

            $mock->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($rows);
            $mock->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn($rows);
            $mock->shouldReceive('clearCache')->zeroOrMoreTimes()->andReturnNull();

            foreach ($deleteIds as $deleteId) {
                if (in_array($interface, [
                    \App\Interfaces\GoogleSheets\LeaveRepositoryInterface::class,
                    \App\Interfaces\GoogleSheets\OvertimeRepositoryInterface::class,
                ], true)) {
                    $mock->shouldReceive('hardDelete')->once()->with($deleteId)->andReturn(true);
                } else {
                    $mock->shouldReceive('delete')->once()->with($deleteId)->andReturn(true);
                }
            }

            $this->app->instance($interface, $mock);
        }
    }
}
