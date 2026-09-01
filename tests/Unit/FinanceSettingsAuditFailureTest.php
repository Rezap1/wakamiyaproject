<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\FinanceSettingsController;
use App\Services\Core\AuditLogService;
use App\Services\Core\SystemSettingService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class FinanceSettingsAuditFailureTest extends TestCase
{
    public function test_audit_false_result_is_structured_without_rolling_back_setting(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-FIN', 'User_ID' => 'USR-FIN', 'Role' => 'FINANCE']));
        $settings = Mockery::mock(SystemSettingService::class);
        $settings->shouldReceive('prepareSettingsForUpdate')->once()->andReturn([[ 'SET-1' => 'value' ], []]);
        $settings->shouldReceive('set')->once()->with('SET-1', 'value', 'USR-FIN')->andReturn(true);
        $settings->shouldReceive('reloadCache')->once();
        $settings->shouldReceive('updateParameter')->never();
        $audit = Mockery::mock(AuditLogService::class);
        $audit->shouldReceive('log')->once()->andReturn(false);
        Log::shouldReceive('warning')->once()->with('finance.settings_audit_failed', Mockery::type('array'));

        $response = (new FinanceSettingsController($settings, $audit))->update(Request::create('/finance/settings', 'POST', [
            'active_tab' => 'Finance', 'settings' => ['SET-1' => 'value'], 'parameters' => [],
        ]));
        $this->assertSame(302, $response->getStatusCode());
    }
}
