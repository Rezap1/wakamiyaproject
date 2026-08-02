<?php
namespace App\Services\Core;

class DashboardHelperService
{
    public static function getSalaryStatus($userId)
    {
        $status = 'Belum Diterima';
        try {
            $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
            $payrollRepo = app(\App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class);
            $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $userId);
            if (!$employee) {
                $user = auth()->user();
                if ($user) {
                    $employee = collect($employeeRepo->fetchAll())->firstWhere('Full_Name', $user->Full_Name);
                }
            }
            
            if ($employee) {
                $payrolls = collect($payrollRepo->getAll())
                    ->where('Employee_ID', $employee['Employee_ID'])
                    ->where('Status', 'Paid')
                    ->sortByDesc('Created_At');
                
                $latestPayroll = $payrolls->first();
                if ($latestPayroll) {
                    $createdAt = $latestPayroll['Created_At'] ?? $latestPayroll['Generated_At'] ?? $latestPayroll['Created_At'] ?? null;
                    $paidDate = $latestPayroll['Paid_Date'] ?? $latestPayroll['Paid_At'] ?? null;
                    
                    $monthCreated = $createdAt ? \Carbon\Carbon::parse($createdAt)->format('Y-m') : \Carbon\Carbon::now()->format('Y-m');
                    $currentMonth = \Carbon\Carbon::now()->format('Y-m');
                    $monthPaid = $paidDate ? \Carbon\Carbon::parse($paidDate)->format('Y-m') : \Carbon\Carbon::now()->format('Y-m');
                    
                    if ($monthCreated === $currentMonth || $monthPaid === $currentMonth) {
                        $status = 'Diterima';
                    }
                }
            }
        } catch (\Exception $e) {}

        return $status;
    }
}
