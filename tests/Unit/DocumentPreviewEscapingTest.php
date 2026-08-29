<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Interfaces\GoogleSheets\DocumentTemplateRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Services\Document\DocumentService;
use Mockery;
use Tests\TestCase;

class DocumentPreviewEscapingTest extends TestCase
{
    public function test_document_preview_escapes_template_and_document_values(): void
    {
        $documentRepository = Mockery::mock(DocumentRepositoryInterface::class);
        $documentRepository->shouldReceive('getById')->once()->with('DOC-XSS')->andReturn([
            'Document_ID' => 'DOC-XSS',
            'Document_Number' => '<script>alert("doc")</script>',
            'Template_ID' => 'TPL-XSS',
        ]);

        $templateRepository = Mockery::mock(DocumentTemplateRepositoryInterface::class);
        $templateRepository->shouldReceive('getById')->once()->with('TPL-XSS')->andReturn([
            'Template_ID' => 'TPL-XSS',
            'Template_Name' => '<img src=x onerror=alert(1)>',
        ]);

        $service = new DocumentService(
            $documentRepository,
            $templateRepository,
            Mockery::mock(EnterpriseEventService::class)
        );

        $preview = $service->PreviewDocument('DOC-XSS');

        $this->assertStringNotContainsString('<script>', $preview['html']);
        $this->assertStringNotContainsString('<img', $preview['html']);
        $this->assertStringContainsString('&lt;script&gt;', $preview['html']);
        $this->assertStringContainsString('&lt;img', $preview['html']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
