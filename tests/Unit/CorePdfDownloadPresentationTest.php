<?php

namespace Tests\Unit;

use App\Http\Controllers\Core\PdfController;
use App\Services\Document\PdfService;
use App\Services\Document\SignatureService;
use Mockery;
use Tests\TestCase;

class CorePdfDownloadPresentationTest extends TestCase
{
    public function test_document_download_returns_real_pdf_with_safe_filename(): void
    {
        $service = Mockery::mock(PdfService::class);
        $service->shouldReceive('DownloadPDF')->once()->with('DOC-1')->andReturn([
            'document' => ['Generated_File' => '../laporan-resmi.pdf'],
            'html' => '<!doctype html><html lang="id"><body><h1>Dokumen Resmi</h1></body></html>',
            'watermark' => null,
        ]);

        $response = (new PdfController($service, Mockery::mock(SignatureService::class)))->download('DOC-1');

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('laporan-resmi.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('..', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
