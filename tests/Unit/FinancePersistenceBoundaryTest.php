<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Finance\AccountService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class FinancePersistenceBoundaryTest extends TestCase
{
    public function test_account_update_returns_the_updated_domain_record(): void
    {
        $this->actingAs($this->financeUser());

        $repository = Mockery::mock(AccountRepositoryInterface::class);
        $repository->shouldReceive('findById')->with('ACC-1')->once()->andReturn([
            'Account_ID' => 'ACC-1',
            'Account_Name' => 'Kas Lama',
            'Account_Category' => 'ASSET',
        ]);
        $repository->shouldReceive('update')->with('ACC-1', Mockery::on(
            fn (array $data) => $data['Account_Name'] === 'Kas Utama' && !empty($data['Updated_At'])
        ))->once()->andReturnTrue();
        $repository->shouldReceive('clearCache')->once();

        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->once();

        $updated = (new AccountService($repository, $events))->update('ACC-1', [
            'Account_Name' => 'Kas Utama',
        ]);

        $this->assertSame('ACC-1', $updated['Account_ID']);
        $this->assertSame('Kas Utama', $updated['Account_Name']);
    }

    public function test_account_update_stops_before_cache_and_event_when_repository_fails(): void
    {
        $this->actingAs($this->financeUser());

        $repository = Mockery::mock(AccountRepositoryInterface::class);
        $repository->shouldReceive('findById')->once()->andReturn([
            'Account_ID' => 'ACC-1',
            'Account_Category' => 'ASSET',
        ]);
        $repository->shouldReceive('update')->once()->andReturnFalse();
        $repository->shouldNotReceive('clearCache');

        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldNotReceive('dispatch');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gagal memperbarui akun ACC-1.');

        (new AccountService($repository, $events))->update('ACC-1', ['Account_Name' => 'Kas']);
    }

    public function test_payment_submission_stops_before_cache_and_event_when_repository_fails(): void
    {
        $this->actingAs($this->financeUser());

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->with('INV-1')->once()->andReturn([
            'Invoice_ID' => 'INV-1',
            'Student_ID' => 'STU-1',
            'Invoice_Type' => 'STUDENT',
            'Status' => 'Waiting Payment',
            'Amount' => 100000,
            'Is_Active' => 'TRUE',
        ]);
        $invoiceService->shouldReceive('calculateRemainingAmount')->once()->andReturn(100000.0);
        $this->app->instance(InvoiceService::class, $invoiceService);

        $payments = Mockery::mock(PaymentRepositoryInterface::class);
        $payments->shouldReceive('getAll')->once()->andReturn(collect());
        $payments->shouldReceive('create')->once()->andReturnFalse();
        $payments->shouldNotReceive('clearCache');

        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldNotReceive('dispatch');

        $service = new PaymentService(
            $payments,
            Mockery::mock(InvoiceRepositoryInterface::class),
            Mockery::mock(StudentRepositoryInterface::class),
            Mockery::mock(CompanyRepositoryInterface::class),
            Mockery::mock(AccountRepositoryInterface::class),
            Mockery::mock(TransactionRepositoryInterface::class),
            $events
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gagal menyimpan pembayaran PAY-1.');

        $service->submitPayment([
            'Payment_ID' => 'PAY-1',
            'Invoice_ID' => 'INV-1',
            'Amount_Paid' => 50000,
        ]);
    }

    public function test_mixed_case_student_cannot_submit_payment_for_another_students_invoice(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-STU-1',
            'User_ID' => 'USR-STU-1',
            'Role' => 'student',
        ]));

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->with('INV-OTHER')->once()->andReturn([
            'Invoice_ID' => 'INV-OTHER',
            'Student_ID' => 'STU-2',
            'Status' => 'Waiting Payment',
            'Amount' => 100000,
            'Is_Active' => 'TRUE',
        ]);
        $invoiceService->shouldNotReceive('calculateRemainingAmount');
        $this->app->instance(InvoiceService::class, $invoiceService);

        $students = Mockery::mock(StudentRepositoryInterface::class);
        $students->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-1', 'User_ID' => 'USR-STU-1'],
        ]));

        $payments = Mockery::mock(PaymentRepositoryInterface::class);
        $payments->shouldNotReceive('create');

        $service = new PaymentService(
            $payments,
            Mockery::mock(InvoiceRepositoryInterface::class),
            $students,
            Mockery::mock(CompanyRepositoryInterface::class),
            Mockery::mock(AccountRepositoryInterface::class),
            Mockery::mock(TransactionRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class)
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('bukan milik akun Anda');

        $service->submitPayment([
            'Invoice_ID' => 'INV-OTHER',
            'Student_ID' => 'STU-2',
            'Amount_Paid' => 50000,
        ]);
    }

    private function financeUser(): GenericUser
    {
        return new GenericUser([
            'id' => 'USR-FIN',
            'User_ID' => 'USR-FIN',
            'Role' => 'FINANCE',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
