<?php
// 1. Update HR Dashboard View
$hrBladePath = 'resources/views/dashboard/hr.blade.php';
if(file_exists($hrBladePath)) {
    $hrBlade = file_get_contents($hrBladePath);
    // Let's replace the first set of KPIs with our actual HR KPIs
    // Specifically looking for the KPI grid
    $search = '<div class="grid grid-cols-2 md:grid-cols-4 gap-4">';
    $replace = '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
        <x-dashboard.kpi-card title="Total Employees" :value="$kpi[\'total_employees\']" color="blue" />
        <x-dashboard.kpi-card title="Active Employees" :value="$kpi[\'active_employees\']" color="emerald" />
        <x-dashboard.kpi-card title="Departments" :value="$kpi[\'total_departments\']" color="indigo" />
        <x-dashboard.kpi-card title="Total Payroll" :value="$kpi[\'total_payroll\']" color="purple" />
        <x-dashboard.kpi-card title="Pending Payroll" :value="$kpi[\'pending_payroll\']" color="amber" />
        <x-dashboard.kpi-card title="Paid Payroll" :value="$kpi[\'paid_payroll\']" color="emerald" />
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-dashboard.kpi-card title="Salary Expense (This Month)" :value="\'Rp \'.number_format($kpi[\'salary_expense\'], 0, \',\', \'.\')" color="rose" />
        <x-dashboard.kpi-card title="Average Salary" :value="\'Rp \'.number_format($kpi[\'average_salary\'], 0, \',\', \'.\')" color="sky" />
    </div>';
    
    // We will just do a simple string replacement if possible
    // Alternatively, I'll rewrite the KPI section
    // For safety, I'll use regex to replace the KPI grid
    $hrBlade = preg_replace('/<div class="grid grid-cols-2 md:grid-cols-4 gap-4">.*?<\/div>/s', $replace, $hrBlade, 1);
    file_put_contents($hrBladePath, $hrBlade);
}

// 2. Update Finance Dashboard View
$finBladePath = 'resources/views/dashboard/finance.blade.php';
if(file_exists($finBladePath)) {
    $finBlade = file_get_contents($finBladePath);
    // Replace placeholders with real values from $kpi
    $finBlade = str_replace(
        [
            "title=\"Total Payroll (Soon)\" :value=\"\$kpi['total_employee_payroll']\"",
            "title=\"Payroll (Soon)\" :value=\"\$kpi['payroll']\""
        ],
        [
            "title=\"Total Payroll\" :value=\"\$kpi['payroll_paid']\"",
            "title=\"Payroll Expense\" :value=\"'Rp '.number_format(\$kpi['monthly_payroll_expense'], 0, ',', '.')\""
        ],
        $finBlade
    );
    file_put_contents($finBladePath, $finBlade);
}

// 3. Add Sidebar link for HR Payroll
$sidebarPath = 'resources/views/components/dashboard/sidebar.blade.php';
if(file_exists($sidebarPath)) {
    $sidebar = file_get_contents($sidebarPath);
    $searchStr = "<!-- HR WORKSPACE -->";
    $hrMenuLink = '
            <a href="{{ route(\'payrolls.index\') }}" class="flex items-center pl-8 pr-4 py-3 text-[14px] font-semibold rounded-r-xl {{ request()->routeIs(\'payrolls.*\') ? \'bg-[#2563eb] text-white shadow-lg shadow-blue-900/50\' : \'text-slate-400 hover:bg-slate-800/50 hover:text-white\' }} transition-all">
                <svg class="w-5 h-5 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Payroll & Gaji
            </a>';
    
    // Inject right after Dashboard link for HR
    $dashboardLinkSearch = "Kehadiran\n            </a>";
    if (strpos($sidebar, 'Payroll & Gaji') === false) {
        $sidebar = str_replace($dashboardLinkSearch, $dashboardLinkSearch . $hrMenuLink, $sidebar);
        file_put_contents($sidebarPath, $sidebar);
    }
}

echo "Dashboard Views & Sidebar updated.\n";
?>
