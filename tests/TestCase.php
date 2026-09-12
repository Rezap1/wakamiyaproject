<?php

namespace Tests;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Global view composers must stay offline in tests. Suites that
        // exercise SystemSettingService explicitly flush these keys first.
        Cache::forever('system_theme_tokens_payload', []);
        Cache::forever('system_company_profile_payload', []);

        $this->app->instance(NotificationRepositoryInterface::class, new class implements NotificationRepositoryInterface {
            public function getAll()
            {
                return collect();
            }

            public function getById($id)
            {
                return null;
            }

            public function create(array $data)
            {
                return false;
            }

            public function update($id, array $data)
            {
                return false;
            }

            public function delete($id)
            {
                return false;
            }

            public function clearCache(): void
            {
                // NotificationService calls this concrete repository hook even
                // though the legacy interface does not declare it.
            }
        });
    }
}
