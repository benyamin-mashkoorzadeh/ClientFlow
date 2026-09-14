<?php

namespace App\Http\Requests\Concerns;

trait NormalizesCurrencyCode
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency_code'))) {
            $currency = strtoupper(trim($this->input('currency_code')));
            $this->merge(['currency_code' => $currency === '' ? null : $currency]);
        }
    }
}
