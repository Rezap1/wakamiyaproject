<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Cache\FileStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeveloperPanelController extends Controller
{
    public function index()
    {
        $connection = DB::connection((string) config('wms_runtime.connection'));
        $metrics = [
            'version' => 'v1.0 (Production)',
            'cache_status' => Cache::store()->getStore() instanceof FileStore ? 'Active (File)' : 'Active (Other)',
            'google_sheets_status' => 'Disabled (migration-only)',
            'runtime_database' => $connection->getDatabaseName(),
            'total_modules' => 11,
            'total_tables' => 11,
            'total_apis' => 11,
            'audit_log_count' => 'Dynamic',
            'bug_count' => 0,
            'progress' => '100%',
        ];

        $modules = [
            ['name' => 'MASTER_USER', 'status' => 'LOCK'],
            ['name' => 'MASTER_DEPARTMENT', 'status' => 'LOCK'],
            ['name' => 'MASTER_POSITION', 'status' => 'LOCK'],
            ['name' => 'MASTER_EMPLOYEE', 'status' => 'LOCK'],
            ['name' => 'MASTER_TEACHER', 'status' => 'LOCK'],
            ['name' => 'MASTER_PROGRAM', 'status' => 'LOCK'],
            ['name' => 'MASTER_BATCH', 'status' => 'LOCK'],
            ['name' => 'MASTER_CLASS', 'status' => 'LOCK'],
            ['name' => 'MASTER_STUDENT', 'status' => 'LOCK'],
            ['name' => 'MASTER_COMPANY', 'status' => 'LOCK'],
            ['name' => 'MASTER_PERMISSION', 'status' => 'LOCK'],
        ];

        return view('developer-panel.index', compact('metrics', 'modules'));
    }
}
