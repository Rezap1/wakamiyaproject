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
        try {
            $dashboardData = Cache::remember('dashboard_finance', 300, function () {
                return $this->dashboardService->getDashboardData();
            });
        } catch (\Exception $e) {
            $dashboardData = ['api_error' => true, 'error_message' => $this->safeExceptionMessage($e)];
        }

        return view('dashboard.finance', $dashboardData);
    }
}
