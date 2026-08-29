<?php

namespace Tests\Unit;

use Tests\TestCase;

class DocumentPrivateStorageTest extends TestCase
{
    public function test_automated_documents_are_written_only_to_private_storage(): void
    {
        $source = file_get_contents(base_path('app/Services/Core/DocumentAutomationService.php'));

        $this->assertStringContainsString("Storage::disk('local')->put", $source);
        $this->assertStringNotContainsString("Storage::disk('public')->put", $source);
        $this->assertStringContainsString("Storage::disk('local')->delete", $source);
        $this->assertStringContainsString('$this->documentRepo->generateNewId', $source);
        $this->assertStringNotContainsString('$this->documentRepo->getAll()->count() + 1', $source);
        $this->assertGreaterThan(
            strpos($source, '$this->documentRepo->create'),
            strpos($source, '$this->documentRepo->update'),
            'Versi lama hanya boleh diarsipkan setelah metadata versi baru tersimpan.'
        );
    }

    public function test_public_document_directory_is_not_present_in_workspace(): void
    {
        $this->assertDirectoryDoesNotExist(storage_path('app/public/documents'));
    }
}
