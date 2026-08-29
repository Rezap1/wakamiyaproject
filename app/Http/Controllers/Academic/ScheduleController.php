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

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
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

        $scheduleGroups = $schedules
            ->groupBy(fn ($schedule) => trim((string) ($schedule['Day_Of_Week'] ?? $schedule['Day'] ?? 'NO_DAY')) ?: 'NO_DAY')
            ->map(function ($group, $day) {
                return [
                    'id' => $day,
                    'title' => $day,
                    'total' => $group->count(),
                    'items' => $group->sortBy('Start_Time')->values(),
                ];
            })
            ->sortBy(function ($group) {
                $order = array_search($group['id'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], true);
                return $order === false ? 99 : $order;
            })
            ->values();

        return view('academic.schedules.index', compact('schedules', 'scheduleGroups'));
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
            $data = $request->only([
                'Class_ID', 'Subject_ID', 'Teacher_ID', 'Academic_Year_ID',
                'Day_Of_Week', 'Start_Time', 'End_Time', 'Room', 'Is_Active'
            ]);
            
            $days = is_array($data['Day_Of_Week'] ?? '') ? $data['Day_Of_Week'] : [$data['Day_Of_Week']];
            
            if (empty($days) || (count($days) === 1 && empty($days[0]))) {
                throw new \Exception('Pilih setidaknya satu hari.');
            }

            foreach ($days as $day) {
                $scheduleData = $data;
                $scheduleData['Day_Of_Week'] = $day;
                $this->scheduleService->create($scheduleData);
            }
            
            return redirect()->route('schedules.index')->with('success', 'Schedule(s) created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show(
        $id,
        \App\Repositories\GoogleSheets\ClassRepository $classRepo,
        \App\Repositories\GoogleSheets\SubjectRepository $subjectRepo,
        \App\Repositories\GoogleSheets\TeacherRepository $teacherRepo,
        \App\Repositories\GoogleSheets\AcademicYearRepository $ayRepo
    ) {
        return $this->edit($id, $classRepo, $subjectRepo, $teacherRepo, $ayRepo);
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
            $data = $request->only([
                'Class_ID', 'Subject_ID', 'Teacher_ID', 'Academic_Year_ID',
                'Day_Of_Week', 'Start_Time', 'End_Time', 'Room', 'Is_Active'
            ]);
            $this->scheduleService->update($id, $data);
            return redirect()->route('schedules.index')->with('success', 'Schedule updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->scheduleService->delete($id);
            return redirect()->route('schedules.index')->with('success', 'Schedule deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }
}
