<?php

namespace App\Http\Requests;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        if (! $application instanceof Application) {
            return false;
        }

        return $this->user()?->can('update', $application) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['required', 'string', Rule::in(Application::statuses())],
            'applied_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
