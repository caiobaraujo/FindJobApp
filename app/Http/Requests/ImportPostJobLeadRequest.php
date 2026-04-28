<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportPostJobLeadRequest extends FormRequest
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
            'source_platform' => ['required', 'string', 'max:50'],
            'source_post_url' => ['required', 'url', 'max:2048'],
            'source_author' => ['nullable', 'string', 'max:255'],
            'source_context_text' => ['required', 'string', 'max:10000'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
