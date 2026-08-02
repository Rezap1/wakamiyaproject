<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    public function clearAdmin()
    {
        Cache::forget('dashboard_admin');
    }

    public function clearAcademic()
    {
        Cache::forget('dashboard_academic');
    }

    public function clearTeacher($teacherId)
    {
        Cache::forget('dashboard_teacher_' . $teacherId);
    }

    public function clearStudent($studentId)
    {
        Cache::forget('dashboard_student_' . $studentId);
    }

    public function clearHR()
    {
        Cache::forget('dashboard_hr');
    }

    public function clearFinance()
    {
        Cache::forget('dashboard_finance');
    }

    public function clearMarketing()
    {
        Cache::forget('dashboard_marketing');
    }

    public function clearDirector()
    {
        Cache::forget('dashboard_director');
    }

    public function clearAll()
    {
        $this->clearAdmin();
        $this->clearAcademic();
        $this->clearHR();
        $this->clearFinance();
        $this->clearMarketing();
        $this->clearDirector();
        // Note: For wildcard clears on teacher/student, if using Redis it's possible with keys, 
        // but with standard file/memcached, we can't wildcard forget. 
        // Usually clearing the global ones is enough for widespread events.
    }
}
