<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AcademicDashboardService;
use Illuminate\Support\Facades\Cache;

class AcademicDashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(AcademicDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $data = Cache::remember('dashboard_academic', 300, function() {
            return $this->dashboardService->getDashboardData();
        });

        return view('dashboard.academic', $data);
    }
}
