<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('projects', 'id')->where(fn ($query) => $query
                    ->where('workspace_id', $this->user()?->activeWorkspace()?->id)
                    ->whereNull('deleted_at')),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'done'])],
        ];
    }
}
