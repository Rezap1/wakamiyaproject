<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Services\Dashboard\StudentDashboardService;

class StudentDashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(StudentDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $userId = Auth::check() ? (Auth::user()->User_ID ?? Auth::id()) : 'U-001';
        
        $data = Cache::remember('dashboard_student_' . $userId, 300, function() {
            return $this->dashboardService->getDashboardData();
        });

        return view('dashboard.student', $data);
    }
}
