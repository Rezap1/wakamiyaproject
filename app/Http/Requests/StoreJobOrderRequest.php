<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Job_Order_Number' => 'required|string|max:100',
            'Company_ID' => 'required|string',
            'Job_Title' => 'required|string|max:255',
            'Job_Category' => 'nullable|string|max:100',
            'Work_Location' => 'nullable|string|max:255',
            'Prefecture' => 'nullable|string|max:100',
            'Employment_Type' => 'nullable|string|max:100',
            'Visa_Type' => 'nullable|string|max:100',
            'Gender_Requirement' => 'nullable|string|max:50',
            'Minimum_Age' => 'nullable|numeric|min:18|max:60',
            'Maximum_Age' => 'nullable|numeric|min:18|max:60',
            'Education_Requirement' => 'nullable|string|max:100',
            'Japanese_Level' => 'nullable|string|max:50',
            'Required_Skill' => 'nullable|string',
            'Job_Description' => 'nullable|string',
            'Basic_Salary' => 'nullable|numeric|min:0',
            'Overtime_Pay' => 'nullable|string|max:100',
            'Working_Hours' => 'nullable|string|max:100',
            'Working_Days' => 'nullable|string|max:100',
            'Holiday' => 'nullable|string|max:255',
            'Accommodation' => 'nullable|string',
            'Meal' => 'nullable|string',
            'Transportation' => 'nullable|string',
            'Insurance' => 'nullable|string',
            'Recruitment_Quantity' => 'required|numeric|min:1',
            'Interview_Date' => 'nullable|date',
            'Departure_Target' => 'nullable|date',
            'PIC_Employee_ID' => 'nullable|string',
            'Job_Order_Status' => 'nullable|in:OPEN,CLOSED,DRAFT,CANCELLED',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }

    public function messages(): array
    {
        return [
            'Job_Order_Number.required' => 'Nomor Job Order wajib diisi.',
            'Company_ID.required' => 'Perusahaan wajib dipilih.',
            'Job_Title.required' => 'Judul Pekerjaan wajib diisi.',
            'Minimum_Age.numeric' => 'Usia minimal harus berupa angka.',
            'Maximum_Age.numeric' => 'Usia maksimal harus berupa angka.',
            'Basic_Salary.numeric' => 'Gaji pokok harus berupa angka.',
            'Recruitment_Quantity.required' => 'Kuantitas rekrutmen wajib diisi.',
            'Recruitment_Quantity.numeric' => 'Kuantitas rekrutmen harus berupa angka.',
            'Interview_Date.date' => 'Format tanggal wawancara tidak valid.',
            'Departure_Target.date' => 'Format target keberangkatan tidak valid.',
            'Job_Order_Status.in' => 'Status Job Order tidak valid.',
            'Is_Active.in' => 'Status aktif tidak valid.'
        ];
    }
}
