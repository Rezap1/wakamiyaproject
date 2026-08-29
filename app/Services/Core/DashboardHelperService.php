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
            
            $user = auth()->user();
            $employees = collect($employeeRepo->fetchAll());
            
            $employee = $employees->firstWhere('User_ID', $userId);
            if (!$employee && $user) {
                $employee = $employees->firstWhere('User_ID', $user->User_ID)
                    ?? (!empty($user->Employee_ID) ? $employees->firstWhere('Employee_ID', $user->Employee_ID) : null)
                    ?? $employees->first(fn($e) => strtolower(trim($e['Full_Name'] ?? '')) === strtolower(trim($user->Full_Name ?? '')));
            }
            
            if ($employee) {
                $empId = $employee['Employee_ID'] ?? null;
                $currentMonth = \Carbon\Carbon::now()->format('Y-m');

                $payrolls = collect($payrollRepo->getAll())
                    ->filter(function($p) use ($empId) {
                        if (($p['Employee_ID'] ?? '') !== $empId) return false;
                        $st = strtolower(trim($p['Status'] ?? ''));
                        return in_array($st, ['approved', 'paid', 'disbursed', 'approved_by_hr', 'completed']);
                    });

                $matchedPayroll = $payrolls->first(function($p) use ($currentMonth) {
                    $period = $p['Payroll_Period'] ?? $p['Period'] ?? '';
                    $createdAt = $p['Created_At'] ?? $p['Generated_At'] ?? null;
                    $paidDate = $p['Paid_Date'] ?? $p['Paid_At'] ?? null;
                    
                    $monthCreated = $createdAt ? \Carbon\Carbon::parse($createdAt)->format('Y-m') : null;
                    $monthPaid = $paidDate ? \Carbon\Carbon::parse($paidDate)->format('Y-m') : null;

                    return $period === $currentMonth || $monthCreated === $currentMonth || $monthPaid === $currentMonth;
                }) ?? $payrolls->first();

                if ($matchedPayroll) {
                    $status = 'Diterima';
                }
            }
        } catch (\Exception $e) {}

        return $status;
    }
}
