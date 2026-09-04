<?php

namespace App\Http\Requests;

use App\Interfaces\GoogleSheets\AcademicYearRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Class_ID' => ['required', 'string', 'max:50', $this->existingActiveRecord(ClassRepositoryInterface::class, 'Kelas')],
            'Subject_ID' => ['required', 'string', 'max:50', $this->existingActiveRecord(SubjectRepositoryInterface::class, 'Materi')],
            'Teacher_ID' => ['required', 'string', 'max:50', $this->existingActiveRecord(TeacherRepositoryInterface::class, 'Pengajar')],
            'Academic_Year_ID' => ['required', 'string', 'max:50', $this->existingActiveRecord(AcademicYearRepositoryInterface::class, 'Tahun ajaran')],
            'Day_Of_Week' => ['required', 'array', 'min:1'],
            'Day_Of_Week.*' => ['required', 'string', Rule::in(self::DAYS)],
            'Start_Time' => ['required', 'date_format:H:i'],
            'End_Time' => ['required', 'date_format:H:i', 'after:Start_Time'],
            'Room' => ['nullable', 'string', 'max:100'],
            'Is_Active' => ['nullable', 'in:TRUE,FALSE'],
        ];
    }

    private function existingActiveRecord(string $repositoryClass, string $label): \Closure
    {
        return function ($attribute, $value, $fail) use ($repositoryClass, $label) {
            $record = app($repositoryClass)->findById(trim((string) $value));
            if (!$record) {
                $fail("{$label} tidak ditemukan.");
                return;
            }

            if (strtoupper(trim((string) ($record['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                $fail("{$label} tidak aktif.");
            }
        };
    }
}
