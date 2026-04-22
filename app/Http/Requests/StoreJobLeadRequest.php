<?php

namespace App\Http\Requests;

use App\Models\JobLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobLeadRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'source_url' => ['required', 'url', 'max:2048'],
            'location' => ['nullable', 'string', 'max:255'],
            'work_mode' => ['nullable', 'string', Rule::in(JobLead::workModes())],
            'salary_range' => ['nullable', 'string', 'max:255'],
            'description_excerpt' => ['nullable', 'string', 'max:5000'],
            'relevance_score' => ['nullable', 'integer', 'min:0'],
            'lead_status' => ['required', 'string', Rule::in(JobLead::leadStatuses())],
            'discovered_at' => ['nullable', 'date'],
        ];
    }
}
