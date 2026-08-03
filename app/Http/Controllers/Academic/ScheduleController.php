<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\ScheduleService;
use App\Services\Core\ActivityLogService;

class ScheduleController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Schedule_Date';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $schedules = $this->scheduleService->getAll();
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $schedules = $schedules->filter(function($item) use ($search) {
                return str_contains(strtolower($item['Schedule_ID'] ?? ''), $search);
            })->values();
        }
        
        return [
            'moduleName' => 'Jadwal (Schedule)',
            'data' => collect(array_values($schedules->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Jadwal', 'Kelas', 'Mata Pelajaran', 'Guru', 'Hari', 'Jam'],
            'mapRow' => function($row) {

                return [
                    $row['Schedule_ID'] ?? '-',
                    $row['Class_ID'] ?? '-',
                    $row['Subject_ID'] ?? '-',
                    $row['Teacher_ID'] ?? '-',
                    $row['Day_Of_Week'] ?? '-',
                    ($row['Start_Time'] ?? '') . ' - ' . ($row['End_Time'] ?? '')
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$schedules->count().'</td></tr>'
        ];
    }

    protected $scheduleService;
    protected $activityLogService;

    public function __construct(ScheduleService $scheduleService, ActivityLogService $activityLogService)
    {
        $this->scheduleService = $scheduleService;
        $this->activityLogService = $activityLogService;
    }

    public function index(Request $request)
    {
        $schedules = $this->scheduleService->getAll();
        
        $classRepo = app(\App\Repositories\GoogleSheets\ClassRepository::class);
        $classes = $classRepo->fetchAll()->keyBy('Class_ID');
        
        $subjectRepo = app(\App\Repositories\GoogleSheets\SubjectRepository::class);
        $subjects = $subjectRepo->fetchAll()->keyBy('Subject_ID');
        
        $teacherRepo = app(\App\Repositories\GoogleSheets\TeacherRepository::class);
        $teachers = $teacherRepo->fetchAll()->keyBy('Teacher_ID');

        $schedules = $schedules->map(function($item) use ($classes, $subjects, $teachers) {
            $classId = $item['Class_ID'] ?? null;
            $subjectId = $item['Subject_ID'] ?? null;
            $teacherId = $item['Teacher_ID'] ?? null;
            
            $className = $classId && isset($classes[$classId]) ? $classes[$classId]['Class_Name'] : $classId;
            $subjectName = $subjectId && isset($subjects[$subjectId]) ? $subjects[$subjectId]['Subject_Name'] : $subjectId;
            $teacherName = $teacherId && isset($teachers[$teacherId]) ? $teachers[$teacherId]['Full_Name'] : $teacherId;
            
            $item['Class_Name'] = $className;
            $item['Subject_Name'] = $subjectName;
            $item['Teacher_Name'] = $teacherName;
            
            return $item;
        });

        return view('academic.schedules.index', compact('schedules'));
    }

    public function create(
        \App\Repositories\GoogleSheets\ClassRepository $classRepo,
        \App\Repositories\GoogleSheets\SubjectRepository $subjectRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\AcademicYearRepository $ayRepo
    ) {
        $classes = $classRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $academicYears = $ayRepo->fetchAll();
        
        $currentTeacherId = '';
        if (auth()->check() && (auth()->user()->Role ?? '') === 'TEACHER') {
            $userId = auth()->user()->User_ID ?? '';
            $teacher = collect($teachers)->firstWhere('User_ID', $userId);
            if ($teacher) {
                $currentTeacherId = $teacher['Teacher_ID'];
            }
        }
        
        return view('academic.schedules.create', compact('classes', 'subjects', 'teachers', 'academicYears', 'currentTeacherId'));
    }

    public function store(\App\Http\Requests\StoreScheduleRequest $request)
    {
        try {
            $data = $request->except('_token');
            $this->scheduleService->create($data);
            $this->activityLogService->log(auth()->id(), 'CREATED', 'Master Schedule', 'Added new schedule for class ' . $request->Class_ID);
            return redirect()->route('schedules.index')->with('success', 'Schedule created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function edit(
        $id,
        \App\Repositories\GoogleSheets\ClassRepository $classRepo,
        \App\Repositories\GoogleSheets\SubjectRepository $subjectRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\AcademicYearRepository $ayRepo
    ) {
        $schedule = $this->scheduleService->getById($id);
        if (!$schedule) return redirect()->route('schedules.index')->withErrors(['error' => 'Not found']);
        
        $classes = $classRepo->fetchAll();
        $subjects = $subjectRepo->fetchAll();
        $teachers = $teacherRepo->fetchAll();
        $academicYears = $ayRepo->fetchAll();
        
        $currentTeacherId = '';
        if (auth()->check() && (auth()->user()->Role ?? '') === 'TEACHER') {
            $userId = auth()->user()->User_ID ?? '';
            $teacher = collect($teachers)->firstWhere('User_ID', $userId);
            if ($teacher) {
                $currentTeacherId = $teacher['Teacher_ID'];
            }
        }
        
        return view('academic.schedules.edit', compact('schedule', 'classes', 'subjects', 'teachers', 'academicYears', 'currentTeacherId'));
    }

    public function update(\App\Http\Requests\UpdateScheduleRequest $request, $id)
    {
        try {
            $data = $request->except(['_token', '_method']);
            $this->scheduleService->update($id, $data);
            $this->activityLogService->log(auth()->id(), 'UPDATED', 'Master Schedule', 'Updated schedule ' . $id);
            return redirect()->route('schedules.index')->with('success', 'Schedule updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->scheduleService->delete($id);
            $this->activityLogService->log(auth()->id(), 'DELETED', 'Master Schedule', 'Deleted schedule ' . $id);
            return redirect()->route('schedules.index')->with('success', 'Schedule deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
