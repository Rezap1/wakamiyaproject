<?php

namespace App\Http\Requests;

use App\Services\Core\RoleService;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMasterManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $roleName = strtoupper(trim((string) ($user->Role ?? $user->Role_Name ?? '')));
        if ($roleName === '') {
            $roleId = trim((string) ($user->Role_ID ?? ''));
            $roleName = strtoupper(trim((string) (app(RoleService::class)->getRoleById($roleId)['Role_Name'] ?? '')));
        }

        return $roleName === 'MASTER';
    }

    public function rules(): array
    {
        return [
            'Batch_ID' => ['required', 'string', 'max:191'],
            'Class_ID' => ['required', 'string', 'max:191'],
            'Attendance_Date' => ['required', 'date_format:Y-m-d'],
            'Attendance_Type' => ['required', 'in:CLASS_QR'],
            'Manual_Reason' => ['required', 'string', 'min:5', 'max:500'],
            'Student_IDs' => ['required', 'array', 'min:1'],
            'Student_IDs.*' => ['required', 'string', 'max:191', 'distinct'],
            'Status' => ['required', 'in:PRESENT'],
        ];
    }

    public function messages(): array
    {
        return [
            'Manual_Reason.required' => 'Alasan pencatatan manual wajib diisi.',
            'Manual_Reason.min' => 'Alasan pencatatan manual minimal 5 karakter.',
            'Student_IDs.required' => 'Pilih minimal satu siswa yang benar-benar hadir.',
            'Student_IDs.min' => 'Pilih minimal satu siswa yang benar-benar hadir.',
            'Status.in' => 'Absensi manual MASTER hanya dapat mencatat status Hadir.',
        ];
    }
}
