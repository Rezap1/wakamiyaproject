<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Employee_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $employeeService = app(\App\Services\Core\EmployeeService::class);
                    $employee = $employeeService->getEmployeeById($value);
                    if (!$employee) {
                        $fail('Pegawai tidak ditemukan.');
                    }
                }
            ],
            'Net_Salary' => 'required|numeric|min:0',
            'Payroll_Period' => 'required|string'
        ];
    }
}
