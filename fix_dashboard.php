<?php
$file = 'app/Services/Dashboard/StudentDashboardService.php';
$content = file_get_contents($file);

// Fix attendance percentage
$content = str_replace(
    "\$presentCount = \$myAttendances->whereIn('Status', ['Present', 'Late'])->count();",
    "\$presentCount = \$myAttendances->filter(function(\$a) { return in_array(strtoupper(trim(\$a['Status'] ?? '')), ['PRESENT', 'LATE', 'HADIR', 'TERLAMBAT']); })->count();",
    $content
);

// Fix today class
$content = str_replace(
    "return strtolower(\$s['Day'] ?? \$s['Day_Of_Week'] ?? '') === strtolower(\$todayIndo);",
    "\$day = strtolower(\$s['Day'] ?? \$s['Day_Of_Week'] ?? ''); return \$day === strtolower(\$todayIndo) || \$day === strtolower(date('l'));",
    $content
);

file_put_contents($file, $content);
echo 'Fixed StudentDashboardService.php';
