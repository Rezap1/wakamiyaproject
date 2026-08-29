<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use App\Services\Core\ActivityService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ActivityServiceAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_master_can_monitor_all_activity_logs(): void
    {
        $service = new ActivityService($this->repository());

        $this->assertSame(3, $service->getActivities('master', 'USR-MASTER')->count());
    }

    public function test_finance_module_scope_is_case_insensitive_and_does_not_include_hr(): void
    {
        $service = new ActivityService($this->repository());
        $modules = $service->getActivities('FINANCE', 'USR-FIN')->pluck('Module')->all();

        $this->assertSame(['FINANCE', 'payment'], $modules);
    }

    public function test_student_only_sees_own_activity(): void
    {
        $service = new ActivityService($this->repository());

        $this->assertSame(
            ['LOG-2'],
            $service->getActivities('STUDENT', 'USR-STU')->pluck('Audit_ID')->all()
        );
    }

    private function repository(): ActivityLogRepositoryInterface
    {
        $repository = Mockery::mock(ActivityLogRepositoryInterface::class);
        $repository->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Audit_ID' => 'LOG-1', 'User_ID' => 'USR-FIN', 'Module' => 'FINANCE', 'Timestamp' => '2026-08-29 10:00:00'],
            ['Audit_ID' => 'LOG-2', 'User_ID' => 'USR-STU', 'Module' => 'payment', 'Timestamp' => '2026-08-29 09:00:00'],
            ['Audit_ID' => 'LOG-3', 'User_ID' => 'USR-HR', 'Module' => 'HR', 'Timestamp' => '2026-08-29 08:00:00'],
        ]));

        return $repository;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
