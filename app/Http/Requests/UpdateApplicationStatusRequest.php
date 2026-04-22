<?php

namespace App\Http\Requests;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(Application::statuses())],
        ];
    }
}
