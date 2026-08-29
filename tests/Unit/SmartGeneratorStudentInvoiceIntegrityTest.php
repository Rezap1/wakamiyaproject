<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\SmartGeneratorController;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use Illuminate\Http\Request;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class SmartGeneratorStudentInvoiceIntegrityTest extends TestCase
{
    public function test_student_invoice_documents_use_trusted_invoice_data_instead_of_client_payload(): void
    {
        $invoiceService = Mockery::mock(InvoiceService::class);
        $invoiceService->shouldReceive('getById')->once()->with('INV-STU-001')->andReturn([
            'Invoice_ID' => 'INV-STU-001',
            'Invoice_Type' => 'STUDENT',
            'Student_ID' => 'STU-REAL',
            'Category' => 'Biaya Pendidikan',
            'Status' => 'Waiting Payment',
            'Display_Status' => 'Waiting Payment',
            'Invoice_Date' => '2026-08-01',
            'Due_Date' => '2026-08-15',
            'Amount' => 7500000,
            'Grand_Total' => 7500000,
            'Subtotal_Amount' => 7500000,
            'Total_Discount' => 0,
            'Total_Tax' => 0,
            'Parsed_Line_Items' => [
                ['description' => 'Biaya Pendidikan', 'qty' => 1, 'unit_price' => 7500000, 'subtotal' => 7500000],
            ],
        ]);

        $studentRepository = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepository->shouldReceive('findById')->once()->with('STU-REAL')->andReturn([
            'Student_ID' => 'STU-REAL',
            'Full_Name' => 'Syahwal Asli',
            'Email' => 'syahwal@example.test',
            'Address' => 'Alamat resmi siswa',
        ]);
        $this->app->instance(StudentRepositoryInterface::class, $studentRepository);

        $userRepository = Mockery::mock(UserRepositoryInterface::class);
        $userRepository->shouldNotReceive('findById');
        $this->app->instance(UserRepositoryInterface::class, $userRepository);

        $controller = new SmartGeneratorController(Mockery::mock(SystemSettingService::class), $invoiceService);
        $request = Request::create('/finance/smart-generator/pdf', 'POST', [
            'source_type' => 'student_invoice',
            'source_id' => 'INV-STU-001',
            'student_id' => 'STU-FAKE',
            'doc_type' => 'invoice',
            'doc_number' => 'INV-FAKE',
            'status' => 'PAID',
            'client_name' => 'Nama Palsu',
            'items' => json_encode([
                ['name' => 'Item palsu', 'qty' => 1, 'price' => 1],
            ]),
            'discount' => 7499999,
            'grand_total' => 1,
        ]);

        $data = $this->prepareDocumentData($controller, $request);

        $this->assertSame('INV-STU-001', $data['doc_number']);
        $this->assertSame('STU-REAL', $data['student_id']);
        $this->assertSame('Syahwal Asli', $data['client_name']);
        $this->assertSame('Waiting Payment', $data['status']);
        $this->assertSame(7500000.0, $data['grand_total']);
        $this->assertSame('Biaya Pendidikan', $data['items'][0]['name']);
        $this->assertSame(7500000.0, $data['items'][0]['total']);
    }

    public function test_manual_document_images_reject_path_traversal_and_non_image_data_uris(): void
    {
        $controller = new SmartGeneratorController(
            Mockery::mock(SystemSettingService::class),
            Mockery::mock(InvoiceService::class)
        );

        $request = Request::create('/finance/smart-generator/pdf', 'POST', [
            'source_type' => 'manual_invoice',
            'company_logo' => '../.env',
            'signature' => '..\\storage\\app\\google-credentials.json',
            'stamp' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            'items' => json_encode([
                ['name' => 'Jasa', 'qty' => 1, 'price' => 100],
            ]),
        ]);

        $data = $this->prepareDocumentData($controller, $request);

        $this->assertNull($data['company_logo']);
        $this->assertNull($data['signature']);
        $this->assertNull($data['stamp']);
        $this->assertSame(100.0, $data['grand_total']);
    }

    private function prepareDocumentData(SmartGeneratorController $controller, Request $request): array
    {
        $method = new ReflectionMethod($controller, 'prepareDocumentData');
        $method->setAccessible(true);

        return $method->invoke($controller, $request);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
