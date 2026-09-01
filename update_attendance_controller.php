<?php
$file = 'app/Http/Controllers/Academic/AttendanceController.php';
$content = file_get_contents($file);

$startPos = strpos($content, 'public function index(Request $request)');
if ($startPos === false) die("Could not find index method");

$endPos = strpos($content, 'public function create()', $startPos);
if ($endPos === false) die("Could not find end of index method");

$oldIndex = substr($content, $startPos, $endPos - $startPos);

$newIndex = "public function index(Request \$request)
    {
        \$attendances = \$this->attendanceService->getAll();
        
        \$classRepo = app(\App\Interfaces\GoogleSheets\ClassRepositoryInterface::class);
        \$allClasses = \$classRepo->fetchAll();
        \$classes = \$allClasses->filter(function(\$c) {
            \$isActive = strtoupper(trim(\$c['Is_Active'] ?? ''));
            return \$isActive === 'TRUE' || \$isActive === '';
        })->values();

        \$classOptions = [];
        foreach (\$classes as \$c) {
            \$cid = trim((string) (\$c['Class_ID'] ?? ''));
            if (\$cid !== '') {
                \$classOptions[\$cid] = (\$c['Class_Name'] ?? \$cid) . (!empty(\$c['Class_Code']) ? ' (' . \$c['Class_Code'] . ')' : '');
            }
        }

        \$studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
        \$students = \$studentRepo->fetchAll()->keyBy('Student_ID');
        
        \$dateFilter = \$request->input('date', date('Y-m-d'));
        \$dateEndFilter = \$request->input('date_end');
        \$classFilter = \$request->input('class_id');
        \$statusFilter = \$request->input('status');
        \$search = strtolower(\$request->input('search', ''));
        
        // Filter attendances
        \$attendances = \$attendances->filter(function(\$a) use (\$dateFilter, \$dateEndFilter, \$classFilter, \$statusFilter, \$search, \$students) {
            try {
                if (empty(\$a['Attendance_Date'])) return false;
                \$aDate = \Carbon\Carbon::parse(str_replace('/', '-', \$a['Attendance_Date']))->format('Y-m-d');
                
                // Date logic
                if (\$dateEndFilter) {
                    if (\$aDate < \$dateFilter || \$aDate > \$dateEndFilter) return false;
                } else {
                    if (\$dateFilter && \$aDate !== \$dateFilter) return false;
                }
                
                // Class filter
                \$cId = \$a['Class_ID'] ?? \$a['Schedule_ID'] ?? '';
                if (\$classFilter && \$cId !== \$classFilter) return false;

                // Status filter
                if (\$statusFilter) {
                    if (strtolower(\$a['Status'] ?? '') !== strtolower(\$statusFilter)) return false;
                }

                // Search filter (by student ID or Name)
                if (\$search) {
                    \$sId = strtolower(\$a['Student_ID'] ?? '');
                    \$student = \$students[\$a['Student_ID'] ?? ''] ?? null;
                    \$sName = strtolower(\$student['Full_Name'] ?? \$student['Name'] ?? '');
                    
                    if (strpos(\$sId, \$search) === false && strpos(\$sName, \$search) === false) {
                        return false;
                    }
                }
                
                return true;
            } catch (\Exception \$e) {
                return false;
            }
        });

        // Group by Class
        \$groupedAttendances = \$attendances->groupBy(function(\$a) {
            return \$a['Class_ID'] ?? \$a['Schedule_ID'] ?? 'Unknown';
        });

        \$classSummary = \$groupedAttendances->map(function(\$classAttendances, \$classId) use (\$classOptions, \$students, \$dateFilter, \$dateEndFilter) {
            \$className = \$classOptions[\$classId] ?? \$classId;
            
            // Enrich students in this class attendances
            \$enrichedStudents = \$classAttendances->map(function(\$a) use (\$students) {
                \$student = \$students[\$a['Student_ID'] ?? ''] ?? null;
                \$a['Student_Name'] = \$student['Full_Name'] ?? \$student['Name'] ?? 'Unknown';
                return \$a;
            })->values();

            \$total = \$classAttendances->count();
            \$hadir = \$classAttendances->filter(fn(\$a) => in_array(strtolower(\$a['Status'] ?? ''), ['hadir', 'present']))->count();
            \$sakit = \$classAttendances->filter(fn(\$a) => in_array(strtolower(\$a['Status'] ?? ''), ['sakit', 'sick']))->count();
            \$izin = \$classAttendances->filter(fn(\$a) => in_array(strtolower(\$a['Status'] ?? ''), ['izin', 'leave', 'permission']))->count();
            \$alpha = \$classAttendances->filter(fn(\$a) => in_array(strtolower(\$a['Status'] ?? ''), ['alpha', 'absent', 'alpa']))->count();
            
            // For range display
            \$dateDisplay = \$dateFilter;
            if (\$dateEndFilter && \$dateFilter !== \$dateEndFilter) {
                \$dateDisplay = \$dateFilter . ' - ' . \$dateEndFilter;
            }

            return [
                'Class_ID' => \$classId,
                'Class_Name' => \$className,
                'Date_Display' => \$dateDisplay,
                'Total' => \$total,
                'Hadir' => \$hadir,
                'Sakit' => \$sakit,
                'Izin' => \$izin,
                'Alpha' => \$alpha,
                'Students' => \$enrichedStudents
            ];
        })->values();

        \$paginatedClasses = \App\Helpers\CollectionHelper::paginate(\$classSummary, 10)->withQueryString();

        return view('academic.attendances.index', compact('paginatedClasses', 'classOptions', 'dateFilter', 'dateEndFilter', 'search', 'statusFilter', 'classFilter'));
    }

    ";

$content = str_replace($oldIndex, $newIndex, $content);
file_put_contents($file, $content);
echo "Updated AttendanceController@index successfully.\n";
