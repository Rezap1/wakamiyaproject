<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Services\Dashboard\FinanceDashboardService;

class FinanceDashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(FinanceDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $dashboardData = Cache::remember('dashboard_finance', 300, function () {
            return $this->dashboardService->getDashboardData();
        });

        return view('dashboard.finance', $dashboardData);
    }
}
