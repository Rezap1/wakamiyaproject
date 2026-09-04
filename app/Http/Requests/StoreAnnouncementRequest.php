<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    private const TARGET_ROLES = ['ALL', 'ALL_USERS', 'TEACHER', 'STUDENT'];
    private const PRIORITIES = ['Low', 'Normal', 'High'];
    private const STATUSES = ['DRAFT', 'PUBLISHED'];

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
            'Title' => 'required|string|max:255',
            'Content' => 'required|string',
            'Target_Role' => ['required', 'string', Rule::in(self::TARGET_ROLES)],
            'Target_ID' => 'nullable|string|max:100',
            'Priority' => ['nullable', 'string', Rule::in(self::PRIORITIES)],
            'Status' => ['nullable', 'string', Rule::in(self::STATUSES)],
            'Publish_Date' => 'nullable|date',
            'Expired_Date' => 'nullable|date|after_or_equal:Publish_Date',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string|max:1000',
        ];
    }
}
