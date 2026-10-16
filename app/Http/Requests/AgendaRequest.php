<?php

namespace App\Http\Requests;

use App\Services\Core\AgendaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Agenda_Date' => ['required', 'date_format:Y-m-d'],
            'Start_Time' => ['required', 'date_format:H:i'],
            'End_Time' => ['required', 'date_format:H:i', 'after:Start_Time'],
            'Agenda_Type' => ['required', Rule::in(array_keys(AgendaService::TYPES))],
            'Related_Parties' => ['required', 'string', 'max:1000'],
            'Purpose' => ['required', 'string', 'max:5000'],
            'PIC_User_ID' => ['required', 'string', 'max:191'],
            'Status' => ['required', Rule::in(array_keys(AgendaService::STATUSES))],
            'Activity_Result' => ['nullable', 'string', 'max:10000'],
            'Follow_Up' => ['nullable', 'string', 'max:10000'],
            'Follow_Up_Deadline' => ['nullable', 'date_format:Y-m-d'],
            'Follow_Up_Status' => ['required', Rule::in(array_keys(AgendaService::FOLLOW_UP_STATUSES))],
            'Document_Notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'End_Time.after' => 'Waktu selesai harus lebih akhir dari waktu mulai.',
            'Agenda_Date.date_format' => 'Tanggal agenda tidak valid.',
            'Follow_Up_Deadline.date_format' => 'Deadline tindak lanjut tidak valid.',
        ];
    }
}
