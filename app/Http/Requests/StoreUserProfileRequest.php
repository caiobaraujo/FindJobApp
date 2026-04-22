<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_role' => ['nullable', 'string', 'max:255'],
            'professional_summary' => ['nullable', 'string', 'max:5000'],
            'core_skills' => ['nullable', 'string', 'max:5000'],
            'work_experience_text' => ['nullable', 'string', 'max:10000'],
            'education_text' => ['nullable', 'string', 'max:5000'],
            'certifications_text' => ['nullable', 'string', 'max:5000'],
            'languages_text' => ['nullable', 'string', 'max:2000'],
            'base_resume_text' => ['nullable', 'string', 'max:50000'],
        ];
    }
}
