<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = strtoupper(trim($this->input('Assessment_Category', 'GENERAL')));

        $rules = [
            'Student_ID' => 'required|string',
            'Assessment_ID' => 'nullable|string',
            'Assessment_Category' => 'required|in:GENERAL,SPORTS,LANGUAGE',
            'Assessment_Date' => 'nullable|date',
            'Notes' => 'nullable|string',
            'Remarks' => 'nullable|string',
        ];

        if ($category === 'LANGUAGE') {
            // H8.21: Bahasa — scale 1-5
            $rules['speaking'] = 'required|integer|min:1|max:5';
            $rules['writing'] = 'required|integer|min:1|max:5';
            $rules['listening'] = 'required|integer|min:1|max:5';
            $rules['reading'] = 'required|integer|min:1|max:5';
            $rules['ethics'] = 'required|integer|min:1|max:5';
            $rules['motivation'] = 'required|integer|min:1|max:5';
            $rules['attendance'] = 'required|integer|min:1|max:5';
        } elseif ($category === 'SPORTS') {
            // H8.21: Olahraga
            $rules['running_distance'] = 'nullable|numeric|min:0';
            $rules['running_time'] = 'nullable|numeric|min:0';
            $rules['push_up'] = 'nullable|integer|min:0';
            $rules['sit_up'] = 'nullable|integer|min:0';
        } else {
            // H8.21: Ujian Bab — 1-100
            $rules['Subject_ID'] = 'required|string';
            $rules['Score_Value'] = 'required|numeric|min:1|max:100';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'speaking.min' => 'Nilai Bicara minimal 1.',
            'speaking.max' => 'Nilai Bicara maksimal 5.',
            'writing.min' => 'Nilai Menulis minimal 1.',
            'writing.max' => 'Nilai Menulis maksimal 5.',
            'listening.min' => 'Nilai Mendengar minimal 1.',
            'listening.max' => 'Nilai Mendengar maksimal 5.',
            'reading.min' => 'Nilai Membaca minimal 1.',
            'reading.max' => 'Nilai Membaca maksimal 5.',
            'ethics.min' => 'Nilai Sikap/Etika minimal 1.',
            'ethics.max' => 'Nilai Sikap/Etika maksimal 5.',
            'motivation.min' => 'Nilai Motivasi Belajar minimal 1.',
            'motivation.max' => 'Nilai Motivasi Belajar maksimal 5.',
            'attendance.min' => 'Nilai Kehadiran minimal 1.',
            'attendance.max' => 'Nilai Kehadiran maksimal 5.',
            'Score_Value.min' => 'Nilai harus minimal 1.',
            'Score_Value.max' => 'Nilai harus maksimal 100.',
            'Subject_ID.required' => 'Mata Pelajaran wajib dipilih untuk Ujian Bab.',
        ];
    }
}
