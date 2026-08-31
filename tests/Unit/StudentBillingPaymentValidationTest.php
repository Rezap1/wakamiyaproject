<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\StudentBillingController;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentBillingPaymentValidationTest extends TestCase
{
    public function test_valid_payment_metadata_is_passed_to_payment_service(): void
    {
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldReceive('submitPayment')->once()->with(Mockery::on(function (array $data) {
            return ($data['Amount_Paid'] ?? null) === '50000'
                && ($data['Reference_Number'] ?? null) === 'Budi Santoso'
                && ($data['Transfer_Date'] ?? null) === '2026-08-30';
        }))->andReturn(['Payment_ID' => 'PAY-TEST']);

        $response = $this->controller($paymentService)->pay($this->request([
            'Amount_Paid' => '50000',
            'Sender_Name' => 'Budi Santoso',
            'Transfer_Date' => '2026-08-30',
        ]), 'INV-TEST');

        $this->assertSame(route('student.billing.show', 'INV-TEST'), $response->getTargetUrl());
    }

    #[DataProvider('invalidMetadataProvider')]
    public function test_invalid_payment_metadata_is_rejected(array $payload, string $invalidField): void
    {
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldNotReceive('submitPayment');

        try {
            $this->controller($paymentService)->pay($this->request($payload), 'INV-TEST');
            $this->fail('Invalid payment metadata was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($invalidField, $exception->errors());
        }
    }

    public function test_future_transfer_date_remains_allowed_without_a_business_rule(): void
    {
        $futureDate = now()->addYear()->toDateString();
        $paymentService = Mockery::mock(PaymentService::class);
        $paymentService->shouldReceive('submitPayment')->once()->with(Mockery::on(function (array $data) use ($futureDate) {
            return ($data['Transfer_Date'] ?? null) === $futureDate;
        }))->andReturn(['Payment_ID' => 'PAY-FUTURE']);

        $response = $this->controller($paymentService)->pay($this->request([
            'Amount_Paid' => '50000',
            'Sender_Name' => 'Budi Santoso',
            'Transfer_Date' => $futureDate,
        ]), 'INV-TEST');

        $this->assertSame(route('student.billing.show', 'INV-TEST'), $response->getTargetUrl());
    }

    public static function invalidMetadataProvider(): array
    {
        return [
            'empty sender name' => [[
                'Amount_Paid' => '50000',
                'Sender_Name' => '',
                'Transfer_Date' => '2026-08-30',
            ], 'Sender_Name'],
            'sender name over 255 characters' => [[
                'Amount_Paid' => '50000',
                'Sender_Name' => str_repeat('A', 256),
                'Transfer_Date' => '2026-08-30',
            ], 'Sender_Name'],
            'empty transfer date' => [[
                'Amount_Paid' => '50000',
                'Sender_Name' => 'Budi Santoso',
                'Transfer_Date' => '',
            ], 'Transfer_Date'],
            'malformed transfer date' => [[
                'Amount_Paid' => '50000',
                'Sender_Name' => 'Budi Santoso',
                'Transfer_Date' => 'not-a-date',
            ], 'Transfer_Date'],
            'invalid transfer date format' => [[
                'Amount_Paid' => '50000',
                'Sender_Name' => 'Budi Santoso',
                'Transfer_Date' => '30/08/2026',
            ], 'Transfer_Date'],
        ];
    }

    private function controller(PaymentService $paymentService): StudentBillingController
    {
        $this->actingAs(new GenericUser([
            'id' => 'USR-STUDENT',
            'User_ID' => 'USR-STUDENT',
            'Role' => 'STUDENT',
        ]));

        $studentRepository = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepository->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STD-TEST', 'User_ID' => 'USR-STUDENT'],
        ]));

        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->with('INV-TEST')->andReturn([
            'Invoice_ID' => 'INV-TEST',
            'Student_ID' => 'STD-TEST',
            'Status' => 'Waiting Payment',
        ]);

        return new StudentBillingController(
            $invoiceService,
            $paymentService,
            Mockery::mock(SystemSettingService::class),
            $studentRepository,
            Mockery::mock(ProgramRepositoryInterface::class),
            Mockery::mock(BatchRepositoryInterface::class),
        );
    }

    private function request(array $payload): Request
    {
        return Request::create('/student/billing/INV-TEST/pay', 'POST', $payload);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
