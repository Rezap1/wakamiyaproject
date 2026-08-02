<?php
namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\TeacherService;
use App\Services\Core\ClassService;
use App\Services\Academic\ScheduleService;
use Illuminate\Support\Facades\Log;

class TeacherWorkspaceController extends Controller
{
    protected $teacherService;
    protected $classService;
    protected $scheduleService;

    public function __construct(TeacherService $teacherService, ClassService $classService, ScheduleService $scheduleService)
    {
        $this->teacherService = $teacherService;
        $this->classService = $classService;
        $this->scheduleService = $scheduleService;
    }

    private function getTeacherId()
    {
        $user = auth()->user();
        if (isset($user->Employee_ID)) {
            return $user->Employee_ID;
        }
        return null;
    }

    public function myClasses()
    {
        $teacherId = $this->getTeacherId();
        $classes = [];

        try {
            if ($teacherId) {
                $allClasses = $this->classService->getAllClasses();
                // Filter classes where this teacher is assigned (Homeroom_Teacher_ID)
                $classes = array_filter($allClasses, function($c) use ($teacherId) {
                    return isset($c['Homeroom_Teacher_ID']) && $c['Homeroom_Teacher_ID'] == $teacherId;
                });
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch classes for teacher $teacherId: " . $e->getMessage());
        }
        
        return view('academic.teacher.classes', compact('classes', 'teacherId'));
    }

    public function reports()
    {
        $teacherId = $this->getTeacherId();
        return view('academic.teacher.reports', compact('teacherId'));
    }

    public function calendar()
    {
        $teacherId = $this->getTeacherId();
        $events = [];

        try {
            if ($teacherId) {
                // In a real application, fetch schedules specific to the teacher or their classes
                $schedules = $this->scheduleService->getAllSchedules();
                foreach ($schedules as $schedule) {
                    if (isset($schedule['Teacher_ID']) && $schedule['Teacher_ID'] == $teacherId) {
                        $events[] = [
                            'date' => $schedule['Date'] ?? date('Y-m-d'),
                            'title' => ($schedule['Topic'] ?? 'Class') . ' - ' . ($schedule['Class_ID'] ?? ''),
                            'type' => $schedule['Type'] ?? 'Class'
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch schedules for teacher $teacherId: " . $e->getMessage());
        }

        return view('academic.teacher.calendar', compact('events', 'teacherId'));
    }
}
