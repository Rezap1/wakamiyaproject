<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\StudentBillingController;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\RoleService;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class StudentSelfServicePaymentTest extends TestCase
{
    public function test_student_can_submit_self_service_payment_without_invoice(): void
    {
        Storage::fake('local');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([['Student_ID' => 'STU-1', 'User_ID' => 'USR-STU']]));
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldReceive('submitPayment')->once()->with(Mockery::on(function ($data) {
            return ($data['Self_Service'] ?? false) === true
                && ($data['Student_ID'] ?? '') === 'STU-1'
                && ($data['Invoice_ID'] ?? null) === null
                && ($data['Payment_Type'] ?? null) === null;
        }))->andReturn(['Payment_ID' => 'PAY-SELF', 'Status' => 'Waiting Verification']);
        $controller = new StudentBillingController(
            Mockery::mock(InvoiceService::class), $paymentService,
            Mockery::mock(SystemSettingService::class), $studentRepo,
            Mockery::mock(ProgramRepositoryInterface::class), Mockery::mock(BatchRepositoryInterface::class)
        );
        $request = Request::create('/student/billing/self-service', 'POST', [
            'Amount_Paid' => 250, 'Payment_Method' => 'TRANSFER', 'Sender_Name' => 'Student A',
            'Transfer_Date' => '2026-09-01', 'Idempotency_Key' => '88888888-8888-4888-8888-888888888888',
        ], [], ['Proof_File' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')]);
        $response = $controller->selfServicePay($request);
        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_self_service_rejects_unsafe_upload_type(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([['Student_ID' => 'STU-1', 'User_ID' => 'USR-STU']]));
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldNotReceive('submitPayment');
        $controller = new StudentBillingController(
            Mockery::mock(InvoiceService::class), $paymentService,
            Mockery::mock(SystemSettingService::class), $studentRepo,
            Mockery::mock(ProgramRepositoryInterface::class), Mockery::mock(BatchRepositoryInterface::class)
        );
        $request = Request::create('/student/billing/self-service', 'POST', [
            'Amount_Paid' => 250, 'Payment_Method' => 'TRANSFER', 'Sender_Name' => 'Student A',
            'Transfer_Date' => '2026-09-01', 'Idempotency_Key' => '99999999-9999-4999-8999-999999999999',
        ], [], ['Proof_File' => UploadedFile::fake()->create('proof.exe', 100, 'application/x-msdownload')]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->selfServicePay($request);
    }

    public function test_student_cannot_download_another_students_payment_proof(): void
    {
        Storage::fake('local');
        $this->actingAs(new GenericUser(['id' => 'USR-STU-A', 'User_ID' => 'USR-STU-A', 'Role' => 'STUDENT']));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'User_ID' => 'USR-STU-A'],
        ]));
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldReceive('getAll')->once()->andReturn(collect([
            ['Payment_ID' => 'PAY-B', 'Student_ID' => 'STU-B', 'Proof_File' => 'payments/proof.pdf'],
        ]));
        $controller = new StudentBillingController(
            Mockery::mock(InvoiceService::class), $paymentService,
            Mockery::mock(SystemSettingService::class), $studentRepo,
            Mockery::mock(ProgramRepositoryInterface::class), Mockery::mock(BatchRepositoryInterface::class)
        );

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->downloadPaymentProof(Request::create('/student/billing/payments/PAY-B/proof', 'GET'), 'PAY-B');
    }

    public function test_student_proof_path_traversal_fails_closed(): void
    {
        Storage::fake('local');
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-1', 'User_ID' => 'USR-STU'],
        ]));
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldReceive('getAll')->once()->andReturn(collect([
            ['Payment_ID' => 'PAY-SELF', 'Student_ID' => 'STU-1', 'Proof_File' => '../secret.txt'],
        ]));
        $controller = new StudentBillingController(
            Mockery::mock(InvoiceService::class), $paymentService,
            Mockery::mock(SystemSettingService::class), $studentRepo,
            Mockery::mock(ProgramRepositoryInterface::class), Mockery::mock(BatchRepositoryInterface::class)
        );

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->downloadPaymentProof(Request::create('/student/billing/payments/PAY-SELF/proof', 'GET'), 'PAY-SELF');
    }

    public function test_role_id_student_scope_is_resolved_from_authoritative_role_service(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STU-A', 'User_ID' => 'USR-STU-A', 'Role_ID' => 'ROLE-STUDENT']));
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->once()->with('ROLE-STUDENT')
            ->andReturn(['Role_ID' => 'ROLE-STUDENT', 'Role_Name' => 'STUDENT', 'Is_Active' => 'TRUE']);
        $this->app->instance(RoleService::class, $roleService);

        $payments = Mockery::mock(PaymentRepositoryInterface::class);
        $payments->shouldReceive('getAll')->once()->andReturn(collect([
            ['Payment_ID' => 'PAY-A', 'Student_ID' => 'STU-A', 'Is_Active' => 'TRUE'],
            ['Payment_ID' => 'PAY-B', 'Student_ID' => 'STU-B', 'Is_Active' => 'TRUE'],
        ]));
        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'User_ID' => 'USR-STU-A'],
        ]));

        $service = new PaymentService(
            $payments,
            Mockery::mock(\App\Interfaces\GoogleSheets\InvoiceRepositoryInterface::class),
            $students,
            Mockery::mock(\App\Interfaces\GoogleSheets\CompanyRepositoryInterface::class),
            Mockery::mock(\App\Interfaces\GoogleSheets\AccountRepositoryInterface::class),
            Mockery::mock(\App\Interfaces\GoogleSheets\TransactionRepositoryInterface::class),
            Mockery::mock(\App\Services\Core\EnterpriseEventService::class)
        );

        $this->assertSame(['PAY-A'], $service->getAll()->pluck('Payment_ID')->all());
    }
}
