<?php

namespace Tests\Unit;

use Tests\TestCase;

class DocumentArchiveRemovalTest extends TestCase
{
    public function test_obsolete_document_archive_has_no_route_or_navigation_entry_point(): void
    {
        foreach (['documents.index', 'documents.create', 'documents.store', 'documents.show', 'documents.edit', 'documents.update', 'documents.destroy', 'templates.index', 'pdf.preview'] as $name) {
            $this->assertNull(app('router')->getRoutes()->getByName($name), $name);
        }

        $sources = implode("\n", [
            file_get_contents(resource_path('views/components/dashboard/sidebar.blade.php')),
            file_get_contents(resource_path('views/dashboard/marketing.blade.php')),
            file_get_contents(resource_path('views/components/mobile-dashboard-hero.blade.php')),
        ]);
        $this->assertStringNotContainsString('Arsip Dokumen', $sources);
        $this->assertStringNotContainsString('Arsip dokumen', $sources);
        $this->assertStringNotContainsString("route('documents.", $sources);
    }

    public function test_dedicated_archive_code_is_removed_but_shared_document_workflows_remain(): void
    {
        foreach ([
            app_path('Http/Controllers/Core/DocumentController.php'),
            app_path('Services/Document/DocumentService.php'),
            app_path('Interfaces/GoogleSheets/DocumentTemplateRepositoryInterface.php'),
            resource_path('views/documents/index.blade.php'),
            config_path('document.php'),
        ] as $removed) {
            $this->assertFileDoesNotExist($removed);
        }

        foreach ([
            app_path('Interfaces/GoogleSheets/DocumentRepositoryInterface.php'),
            app_path('Repositories/GoogleSheets/DocumentRepository.php'),
            app_path('Services/Core/DocumentAutomationService.php'),
            app_path('Services/Core/DocumentService.php'),
            resource_path('views/pdf/certificate.blade.php'),
            resource_path('views/pdf/official_payslip.blade.php'),
        ] as $preserved) {
            $this->assertFileExists($preserved);
        }

        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString('DocumentRepositoryInterface::class, DocumentRepository::class', $provider);
        $this->assertStringNotContainsString('DocumentTemplateRepositoryInterface', $provider);
        $this->assertStringContainsString('DocumentAutomationService::class', file_get_contents(app_path('Services/HR/PayrollService.php')));
        $this->assertStringContainsString('DocumentAutomationService::class', file_get_contents(app_path('Services/Core/StudentService.php')));
    }
}
