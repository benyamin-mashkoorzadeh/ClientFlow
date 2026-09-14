<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesCurrencyCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    use NormalizesCurrencyCode;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => [
                'required', 'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query
                    ->where('workspace_id', $this->user()?->activeWorkspace()?->id)
                    ->whereNull('deleted_at')),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'status' => ['sometimes', Rule::in(['draft'])],
            'currency_code' => ['nullable', 'string', Rule::in(config('currencies.supported'))],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price_cents' => ['required', 'integer', 'min:0'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'invoice_number' => ['prohibited'],
            'subtotal_cents' => ['prohibited'],
            'discount_cents' => ['prohibited'],
            'tax_cents' => ['prohibited'],
            'total_cents' => ['prohibited'],
            'items.*.line_subtotal_cents' => ['prohibited'],
            'items.*.line_discount_cents' => ['prohibited'],
            'items.*.line_tax_cents' => ['prohibited'],
            'items.*.line_total_cents' => ['prohibited'],
        ];
    }
}
