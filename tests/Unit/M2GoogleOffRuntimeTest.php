<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Repositories\GoogleSheets\UserRepository;
use App\Repositories\MySql\BaseMySqlRepository;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class M2GoogleOffRuntimeTest extends TestCase
{
    public function test_google_api_symbols_are_confined_to_read_only_migration_source(): void
    {
        $allowed = str_replace('\\', '/', app_path('Services/Migration/GoogleSheetsReadOnlySource.php'));
        $violations = [];

        foreach (File::allFiles(app_path()) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($path === $allowed) {
                continue;
            }
            $source = $file->getContents();
            foreach (['Google_Client', 'Google_Service_Sheets', 'spreadsheets_values', 'sheets.googleapis.com'] as $needle) {
                if (str_contains($source, $needle)) {
                    $violations[] = $path.' contains '.$needle;
                }
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_runtime_repository_base_has_no_google_client_and_auth_is_mysql(): void
    {
        $source = File::get(app_path('Repositories/MySql/BaseMySqlRepository.php'));

        $this->assertStringNotContainsString('Google_Client', $source);
        $this->assertStringNotContainsString('Google_Service_Sheets', $source);
        $this->assertStringNotContainsString('spreadsheets_values', $source);
        $this->assertStringNotContainsString('retry(', $source);
        $this->assertStringNotContainsString('Cache::lock', $source);
        $this->assertSame('wms_mysql', config('auth.providers.users.driver'));
        $this->assertTrue(is_subclass_of(
            UserRepository::class,
            BaseMySqlRepository::class
        ));
    }

    public function test_runtime_boots_with_google_settings_unavailable(): void
    {
        config()->set('services.google.spreadsheet_id', null);
        putenv('GOOGLE_APPLICATION_CREDENTIALS');

        $repository = app(UserRepositoryInterface::class);

        $this->assertInstanceOf(BaseMySqlRepository::class, $repository);
        $this->assertNotNull(app('auth')->createUserProvider('users'));
    }
}
