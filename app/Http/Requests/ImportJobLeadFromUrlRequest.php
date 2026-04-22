<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportJobLeadFromUrlRequest extends FormRequest
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
            'source_url' => ['required', 'url', 'max:2048'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
