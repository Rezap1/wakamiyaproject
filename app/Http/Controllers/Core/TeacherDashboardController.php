<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Dashboard\TeacherDashboardService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class TeacherDashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(TeacherDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $userId = Auth::id() ?? 'U-001';
        $data = Cache::remember('dashboard_teacher_' . $userId, 300, function() {
            return $this->dashboardService->getDashboardData();
        });

        return view('dashboard.teacher', $data);
    }
}
