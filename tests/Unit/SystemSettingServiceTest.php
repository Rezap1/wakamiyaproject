<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\SystemParameterRepositoryInterface;
use App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface;
use App\Services\Core\SystemSettingService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SystemSettingServiceTest extends TestCase
{
    private SystemSettingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $settingRepo = Mockery::mock(SystemSettingRepositoryInterface::class);
        $settingRepo->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());

        $parameterRepo = Mockery::mock(SystemParameterRepositoryInterface::class);
        $parameterRepo->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());

        $this->service = new SystemSettingService($settingRepo, $parameterRepo);
    }

    public function test_finance_and_bank_defaults_are_available_without_sheet_rows(): void
    {
        $this->assertNotEmpty($this->service->category('Finance')->firstWhere('Setting_Key', 'DEFAULT_TUITION_FEE'));
        $this->assertNotEmpty($this->service->category('Company_Bank')->firstWhere('Setting_Key', 'COMPANY_BANK_NAME'));
        $this->assertSame(7500000.0, $this->service->getDefaultTuitionFee());
        $this->assertContains('Biaya Pendidikan', $this->service->getInvoiceCategories());
    }

    public function test_invoice_categories_normalize_legacy_education_label(): void
    {
        Cache::flush();

        $settingRepo = Mockery::mock(SystemSettingRepositoryInterface::class);
        $settingRepo->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect([
            [
                'Setting_ID' => 'SET_FIN_INVOICE_CATEGORIES',
                'Category' => 'Finance',
                'Setting_Key' => 'INVOICE_CATEGORIES',
                'Setting_Value' => 'Pendidikan, Medical, SPP / Biaya Pendidikan',
            ],
        ]));

        $parameterRepo = Mockery::mock(SystemParameterRepositoryInterface::class);
        $parameterRepo->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect());

        $service = new SystemSettingService($settingRepo, $parameterRepo);
        $categories = $service->getInvoiceCategories();

        $this->assertContains('Biaya Pendidikan', $categories);
        $this->assertNotContains('Pendidikan', $categories);
        $this->assertCount(2, $categories);
    }

    public function test_prepare_settings_rejects_invalid_business_values(): void
    {
        [, $errors] = $this->service->prepareSettingsForUpdate([
            'SET_HR_LPK_LATITUDE' => '120',
            'SET_ASSESSMENT_WEIGHT_EXAM' => '50',
            'SET_ASSESSMENT_WEIGHT_ASSIGNMENT' => '40',
            'SET_ASSESSMENT_WEIGHT_ATTENDANCE' => '20',
        ]);

        $this->assertStringContainsString('Latitude', implode(' ', $errors));
        $this->assertStringContainsString('Total bobot penilaian', implode(' ', $errors));
    }

    public function test_prepare_settings_normalizes_boolean_and_color_values(): void
    {
        [$prepared, $errors] = $this->service->prepareSettingsForUpdate([
            'SET_HR_AUTO_ATTENDANCE_DEDUCTION' => 'on',
            'SET_BRAND_PRIMARY' => '#abcdef',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame('true', $prepared['SET_HR_AUTO_ATTENDANCE_DEDUCTION']);
        $this->assertSame('#ABCDEF', $prepared['SET_BRAND_PRIMARY']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
