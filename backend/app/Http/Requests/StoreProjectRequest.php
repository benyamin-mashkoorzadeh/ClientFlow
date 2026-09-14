<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesCurrencyCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    use NormalizesCurrencyCode;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query
                    ->where('workspace_id', $this->user()?->activeWorkspace()?->id)
                    ->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'completed', 'on_hold'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_cents' => ['nullable', 'integer', 'min:0'],
            'currency_code' => ['nullable', 'string', Rule::in(config('currencies.supported'))],
        ];
    }
}
