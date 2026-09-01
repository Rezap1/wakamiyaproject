<?php
$file = 'app/Http/Controllers/Academic/AttendanceController.php';
$content = file_get_contents($file);

$start = strpos($content, '// Filter attendances');
$end = strpos($content, '// Group by Resolved Class_ID');

if ($start !== false && $end !== false) {
    $oldBlock = substr($content, $start, $end - $start);
    
    $newBlock = "
// Resolve Class_ID for all attendances first
        \$attendances = \$attendances->map(function(\$a) use (\$schedules) {
            \$cId = \$a['Class_ID'] ?? '';
            if (empty(\$cId) && !empty(\$a['Schedule_ID'])) {
                \$sch = \$schedules[\$a['Schedule_ID']] ?? null;
                if (\$sch && !empty(\$sch['Class_ID'])) {
                    \$cId = \$sch['Class_ID'];
                } else {
                    \$cId = \$a['Schedule_ID']; // Fallback
                }
            }
            \$a['Resolved_Class_ID'] = \$cId;
            return \$a;
        });

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
                
                \$cId = \$a['Resolved_Class_ID'] ?? '';

                // Class filter
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

        ";
        
    $content = substr_replace($content, $newBlock, $start, $end - $start);
    file_put_contents($file, $content);
    echo "Fixed filter logic in AttendanceController.\n";
} else {
    echo "Block not found.\n";
}
