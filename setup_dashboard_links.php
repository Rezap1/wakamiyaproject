<?php
// Function to replace href="#" with the correct routes in dashboard files
function updateDashboardLinks($filePath, $role) {
    if (!file_exists($filePath)) return;
    
    $content = file_get_contents($filePath);
    
    // KPI Cards
    $content = str_replace('<a href="#" class="block', '<a href="{{ route(\'students.index\') }}" class="block', $content); // generic # replacement isn't safe, let's use targeted regex
    
    // Since kpi-card is a component, it might have an href prop. Let's look at index.blade.php.
    // In index.blade.php: <x-dashboard.kpi-card title="Total Student" ... />
    // Let's modify the kpi-card component to accept an href!
    
    file_put_contents($filePath, $content);
}

// 1. Update KPI Card component to accept href
$kpiCompPath = 'resources/views/components/dashboard/kpi-card.blade.php';
$kpiContent = file_get_contents($kpiCompPath);
if (strpos($kpiContent, '$href') === false) {
    $kpiContent = str_replace(
        "@props(['title', 'value', 'color' => 'blue', 'subtext' => null, 'subtextStatus' => 'neutral'])",
        "@props(['title', 'value', 'color' => 'blue', 'subtext' => null, 'subtextStatus' => 'neutral', 'href' => '#'])",
        $kpiContent
    );
    // Add anchor wrapper
    $kpiContent = str_replace(
        '<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition-shadow">',
        '<a href="{{ $href }}" class="block bg-white rounded-2xl shadow-sm border border-slate-200 p-5 relative overflow-hidden group hover:shadow-md transition-shadow hover:border-{{ $color }}-300">',
        $kpiContent
    );
    // Replace closing div
    $kpiContent = substr_replace($kpiContent, "</a>", strrpos($kpiContent, "</div>"), 6);
    file_put_contents($kpiCompPath, $kpiContent);
    echo "kpi-card component updated.\n";
}

// 2. Update dashboards to pass href to KPI cards
$dashboards = glob('resources/views/dashboard/*.blade.php');
foreach ($dashboards as $d) {
    $content = file_get_contents($d);
    
    // Quick Actions
    $content = preg_replace('/href="#"(\s+class="[^"]*")>\s*<div[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/div>\s*<span[^>]*>Add Student<\/span>/s', 'href="{{ route(\'students.create\') }}"$1> <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 group-hover:bg-blue-600 group-hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></div> <span class="text-[13px] font-bold text-slate-700 group-hover:text-blue-600 transition-colors">Add Student</span>', $content);
    $content = str_replace('>Add Employee<', '>Add Employee<', $content); // Will use regex for quick actions.
    
    // It's safer to just do a global replace for specific strings.
    $replacements = [
        // KPI Cards
        '<x-dashboard.kpi-card title="Total Student" :value="$kpi[\'students\'] ?? 0" color="blue"' => '<x-dashboard.kpi-card title="Total Student" :value="$kpi[\'students\'] ?? 0" color="blue" href="{{ Route::has(\'students.index\') ? route(\'students.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Total Employee" :value="$kpi[\'employees\'] ?? 0" color="green"' => '<x-dashboard.kpi-card title="Total Employee" :value="$kpi[\'employees\'] ?? 0" color="green" href="{{ Route::has(\'employees.index\') ? route(\'employees.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Total Teacher" :value="$kpi[\'teachers\'] ?? 0" color="purple"' => '<x-dashboard.kpi-card title="Total Teacher" :value="$kpi[\'teachers\'] ?? 0" color="purple" href="{{ Route::has(\'teachers.index\') ? route(\'teachers.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Total Company" :value="$kpi[\'companies\'] ?? 0" color="orange"' => '<x-dashboard.kpi-card title="Total Company" :value="$kpi[\'companies\'] ?? 0" color="orange" href="{{ Route::has(\'companies.index\') ? route(\'companies.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Total Document" :value="$kpi[\'documents\'] ?? 0" color="cyan"' => '<x-dashboard.kpi-card title="Total Document" :value="$kpi[\'documents\'] ?? 0" color="cyan" href="{{ Route::has(\'documents.index\') ? route(\'documents.index\') : \'#\' }}"',
        
        // HR KPI Cards
        '<x-dashboard.kpi-card title="Total Department"' => '<x-dashboard.kpi-card title="Total Department" href="{{ Route::has(\'departments.index\') ? route(\'departments.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Total Position"' => '<x-dashboard.kpi-card title="Total Position" href="{{ Route::has(\'positions.index\') ? route(\'positions.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Active Contract"' => '<x-dashboard.kpi-card title="Active Contract" href="{{ Route::has(\'employees.index\') ? route(\'employees.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="On Leave"' => '<x-dashboard.kpi-card title="On Leave" href="{{ Route::has(\'employees.index\') ? route(\'employees.index\') : \'#\' }}"',
        
        // Academic KPI Cards
        '<x-dashboard.kpi-card title="Total Program"' => '<x-dashboard.kpi-card title="Total Program" href="{{ Route::has(\'programs.index\') ? route(\'programs.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Active Class"' => '<x-dashboard.kpi-card title="Active Class" href="{{ Route::has(\'classes.index\') ? route(\'classes.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Total Subject"' => '<x-dashboard.kpi-card title="Total Subject" href="{{ Route::has(\'subjects.index\') ? route(\'subjects.index\') : \'#\' }}"',
        
        // Marketing KPI Cards
        '<x-dashboard.kpi-card title="Job Orders"' => '<x-dashboard.kpi-card title="Job Orders" href="{{ Route::has(\'job-orders.index\') ? route(\'job-orders.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Ongoing Interview"' => '<x-dashboard.kpi-card title="Ongoing Interview" href="{{ Route::has(\'interviews.index\') ? route(\'interviews.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Matched Student"' => '<x-dashboard.kpi-card title="Matched Student" href="{{ Route::has(\'matchings.index\') ? route(\'matchings.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="COE Processing"' => '<x-dashboard.kpi-card title="COE Processing" href="{{ Route::has(\'coes.index\') ? route(\'coes.index\') : \'#\' }}"',
        
        // Finance KPI Cards
        '<x-dashboard.kpi-card title="Unpaid Invoice"' => '<x-dashboard.kpi-card title="Unpaid Invoice" href="{{ Route::has(\'applications.index\') ? route(\'applications.index\') : \'#\' }}"',
        
        // Teacher KPI Cards
        '<x-dashboard.kpi-card title="My Classes"' => '<x-dashboard.kpi-card title="My Classes" href="{{ Route::has(\'teacher.classes\') ? route(\'teacher.classes\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Pending Score"' => '<x-dashboard.kpi-card title="Pending Score" href="{{ Route::has(\'scores.index\') ? route(\'scores.index\') : \'#\' }}"',
        
        // Student KPI Cards
        '<x-dashboard.kpi-card title="Attendance Rate"' => '<x-dashboard.kpi-card title="Attendance Rate" href="{{ Route::has(\'student.schedule\') ? route(\'student.schedule\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Avg. Score"' => '<x-dashboard.kpi-card title="Avg. Score" href="{{ Route::has(\'scores.index\') ? route(\'scores.index\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Pending Assignment"' => '<x-dashboard.kpi-card title="Pending Assignment" href="{{ Route::has(\'student.subjects\') ? route(\'student.subjects\') : \'#\' }}"',
        '<x-dashboard.kpi-card title="Current Stage"' => '<x-dashboard.kpi-card title="Current Stage" href="{{ Route::has(\'student.progress\') ? route(\'student.progress\') : \'#\' }}"',
        
        // View All Buttons
        'href="#" class="text-[11px] font-bold text-blue-600 hover:text-blue-700 hover:underline uppercase tracking-wider">View All' => 'href="{{ Route::has(\'activity.index\') ? route(\'activity.index\') : \'#\' }}" class="text-[11px] font-bold text-blue-600 hover:text-blue-700 hover:underline uppercase tracking-wider">View All',
        'href="#" class="text-[11px] font-bold text-blue-600 hover:text-blue-700 uppercase tracking-wider hover:underline">View All' => 'href="{{ Route::has(\'announcements.index\') ? route(\'announcements.index\') : \'#\' }}" class="text-[11px] font-bold text-blue-600 hover:text-blue-700 uppercase tracking-wider hover:underline">View All',
        
        // Empty States
        '<div class="text-center py-8">
                            <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-100">
                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">No recent activity</p>
                        </div>' => 
                        '@if(isset($activities) && count($activities) > 0)
                        <!-- Data goes here if any -->
                        @else
                        <div class="text-center py-8">
                            <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-100">
                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">No recent activity</p>
                        </div>
                        @endif',
    ];
    
    // Using preg_replace for quick action buttons since they span multiple lines
    $content = preg_replace(
        '/<a href="#" class="([^"]*)">\s*<div[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/div>\s*<span[^>]*>Add Student<\/span>\s*<\/a>/s', 
        '<a href="{{ Route::has(\'students.create\') ? route(\'students.create\') : (Route::has(\'students.index\') ? route(\'students.index\') : \'#\') }}" class="$1"> <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 group-hover:bg-blue-600 group-hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></div> <span class="text-[13px] font-bold text-slate-700 group-hover:text-blue-600 transition-colors">Add Student</span> </a>', 
        $content
    );
    
    $content = preg_replace(
        '/<a href="#" class="([^"]*)">\s*<div[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/div>\s*<span[^>]*>Add Employee<\/span>\s*<\/a>/s', 
        '<a href="{{ Route::has(\'employees.create\') ? route(\'employees.create\') : (Route::has(\'employees.index\') ? route(\'employees.index\') : \'#\') }}" class="$1"> <div class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center mb-3 group-hover:bg-green-600 group-hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></div> <span class="text-[13px] font-bold text-slate-700 group-hover:text-green-600 transition-colors">Add Employee</span> </a>', 
        $content
    );
    
    $content = preg_replace(
        '/<a href="#" class="([^"]*)">\s*<div[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/div>\s*<span[^>]*>Add Teacher<\/span>\s*<\/a>/s', 
        '<a href="{{ Route::has(\'teachers.create\') ? route(\'teachers.create\') : (Route::has(\'teachers.index\') ? route(\'teachers.index\') : \'#\') }}" class="$1"> <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-3 group-hover:bg-purple-600 group-hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></div> <span class="text-[13px] font-bold text-slate-700 group-hover:text-purple-600 transition-colors">Add Teacher</span> </a>', 
        $content
    );
    
    $content = preg_replace(
        '/<a href="#" class="([^"]*)">\s*<div[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/div>\s*<span[^>]*>Add Program<\/span>\s*<\/a>/s', 
        '<a href="{{ Route::has(\'programs.create\') ? route(\'programs.create\') : (Route::has(\'programs.index\') ? route(\'programs.index\') : \'#\') }}" class="$1"> <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 group-hover:bg-blue-600 group-hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></div> <span class="text-[13px] font-bold text-slate-700 group-hover:text-blue-600 transition-colors">Add Program</span> </a>', 
        $content
    );
    
    $content = preg_replace(
        '/<a href="#" class="([^"]*)">\s*<div[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/div>\s*<span[^>]*>Add Company<\/span>\s*<\/a>/s', 
        '<a href="{{ Route::has(\'companies.create\') ? route(\'companies.create\') : (Route::has(\'companies.index\') ? route(\'companies.index\') : \'#\') }}" class="$1"> <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center mb-3 group-hover:bg-orange-600 group-hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></div> <span class="text-[13px] font-bold text-slate-700 group-hover:text-orange-600 transition-colors">Add Company</span> </a>', 
        $content
    );
    
    $content = preg_replace(
        '/<a href="#" class="([^"]*)">\s*<div[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/div>\s*<span[^>]*>Add Batch<\/span>\s*<\/a>/s', 
        '<a href="{{ Route::has(\'batches.create\') ? route(\'batches.create\') : (Route::has(\'batches.index\') ? route(\'batches.index\') : \'#\') }}" class="$1"> <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3 group-hover:bg-indigo-600 group-hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></div> <span class="text-[13px] font-bold text-slate-700 group-hover:text-indigo-600 transition-colors">Add Batch</span> </a>', 
        $content
    );
    
    $content = preg_replace(
        '/<a href="#" class="([^"]*)">\s*<div[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/div>\s*<span[^>]*>Add Class<\/span>\s*<\/a>/s', 
        '<a href="{{ Route::has(\'classes.create\') ? route(\'classes.create\') : (Route::has(\'classes.index\') ? route(\'classes.index\') : \'#\') }}" class="$1"> <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center mb-3 group-hover:bg-teal-600 group-hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg></div> <span class="text-[13px] font-bold text-slate-700 group-hover:text-teal-600 transition-colors">Add Class</span> </a>', 
        $content
    );
    
    $content = preg_replace(
        '/<a href="#" class="([^"]*)">\s*<div[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<\/div>\s*<span[^>]*>View Report<\/span>\s*<\/a>/s', 
        '<a href="{{ Route::has(\'academic.reports\') ? route(\'academic.reports\') : \'#\' }}" class="$1"> <div class="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center mb-3 group-hover:bg-cyan-600 group-hover:text-white transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></div> <span class="text-[13px] font-bold text-slate-700 group-hover:text-cyan-600 transition-colors">View Report</span> </a>', 
        $content
    );
    
    // Calendar empty state link
    $content = preg_replace('/<button onclick="alert\([^)]+\)" class="w-full text-center py-2 text-xs font-bold text-slate-500 hover:text-blue-600 transition-colors">View All Schedule<\/button>/s', 
        '<a href="{{ Route::has(\'schedules.index\') ? route(\'schedules.index\') : \'#\' }}" class="block w-full text-center py-2 text-xs font-bold text-slate-500 hover:text-blue-600 transition-colors">View All Schedule</a>', 
        $content);

    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    file_put_contents($d, $content);
    echo "Updated $d\n";
}

// 3. Update Topbar
$topbarPath = 'resources/views/components/dashboard/topbar.blade.php';
$topbar = file_get_contents($topbarPath);

// Profile dropdown fixes
$topbar = str_replace('href="{{ Route::has(\'profile.index\') ? route(\'profile.index\') : \'#\' }}"', 'href="{{ route(\'profile.index\') }}"', $topbar);
$topbar = str_replace('href="{{ Route::has(\'settings.index\') ? route(\'settings.index\') : \'#\' }}"', 'href="{{ route(\'profile.index\') }}#security"', $topbar);

// Search action
$topbar = preg_replace('/action="[^"]*"/', 'action="{{ Route::has(\'search.index\') ? route(\'search.index\') : \'#\' }}"', $topbar, 1);

// Add Activity log link right before Logout
if (strpos($topbar, 'Keluar') !== false && strpos($topbar, 'Aktivitas Log') === false) {
    $activityLink = '<a href="{{ Route::has(\'activity.index\') ? route(\'activity.index\') : \'#\' }}" class="flex items-center gap-3 px-4 py-2.5 text-[13px] font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Aktivitas Log
                    </a>';
    $topbar = str_replace('<div class="border-t border-slate-100 py-2">', '<div class="border-t border-slate-100 py-2">' . "\n" . '                    ' . $activityLink, $topbar);
}

file_put_contents($topbarPath, $topbar);
echo "Updated Topbar\n";

?>
