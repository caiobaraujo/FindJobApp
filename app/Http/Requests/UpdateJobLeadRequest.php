<?php

namespace App\Http\Requests;

use App\Models\JobLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jobLead = $this->route('job_lead');

        if (! $jobLead instanceof JobLead) {
            return false;
        }

        return $this->user()?->id === $jobLead->user_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'job_title' => ['sometimes', 'required', 'string', 'max:255'],
            'source_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_url' => ['sometimes', 'required', 'url', 'max:2048'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'work_mode' => ['sometimes', 'nullable', 'string', Rule::in(JobLead::workModes())],
            'salary_range' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description_excerpt' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'description_text' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'relevance_score' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'lead_status' => ['sometimes', 'required', 'string', Rule::in(JobLead::leadStatuses())],
            'discovered_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
