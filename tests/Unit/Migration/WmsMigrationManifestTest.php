<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\GoogleSheetsReadOnlySource;
use App\Support\Migration\WmsMigrationManifest;
use Google_Service_Sheets;
use PHPUnit\Framework\TestCase;

final class WmsMigrationManifestTest extends TestCase
{
    public function test_manifest_preserves_all_discovered_runtime_contracts(): void
    {
        $contracts = WmsMigrationManifest::contracts();

        $this->assertCount(45, $contracts);
        $this->assertSame('Student_ID', $contracts['MASTER_STUDENT']['primary_key']);
        $this->assertSame('Payment_ID', $contracts['FINANCE_PAYMENT']['primary_key']);
        $this->assertSame('Result_ID', $contracts['QUIZ_RESULT']['primary_key']);
        $this->assertTrue($contracts['MASTER_SYSTEM_SETTING']['preserve_duplicate_ids']);
        $this->assertNotContains('README', array_keys($contracts));
    }

    public function test_types_are_compatibility_first_and_money_is_decimal(): void
    {
        $this->assertSame('identifier', WmsMigrationManifest::columnType('Student_ID'));
        $this->assertSame('decimal', WmsMigrationManifest::columnType('Amount_Paid'));
        $this->assertSame('boolean', WmsMigrationManifest::columnType('Is_Active'));
        $this->assertSame('json_text', WmsMigrationManifest::columnType('Submitted_Answers_JSON'));
    }

    public function test_google_source_is_hard_limited_to_read_only_scope(): void
    {
        $source = file_get_contents((new \ReflectionClass(GoogleSheetsReadOnlySource::class))->getFileName());

        $this->assertStringContainsString('SPREADSHEETS_READONLY', $source);
        $this->assertStringNotContainsString('spreadsheets_values->append', $source);
        $this->assertStringNotContainsString('spreadsheets_values->update', $source);
        $this->assertStringNotContainsString('batchUpdate(', $source);
        $this->assertSame('https://www.googleapis.com/auth/spreadsheets.readonly', Google_Service_Sheets::SPREADSHEETS_READONLY);
    }
}
