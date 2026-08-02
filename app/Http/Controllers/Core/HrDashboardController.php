<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Services\Dashboard\HrDashboardService;

class HrDashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(HrDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $dashboardData = Cache::remember('dashboard_hr', 300, function () {
            return $this->dashboardService->getDashboardData();
        });

        return view('dashboard.hr', $dashboardData);
    }
}
