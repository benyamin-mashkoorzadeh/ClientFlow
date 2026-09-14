<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

final class CurrencyCode
{
    public static function workspaceDefault(Workspace $workspace): string
    {
        $currency = strtoupper($workspace->default_currency);

        if (! in_array($currency, config('currencies.supported'), true)) {
            throw ValidationException::withMessages([
                'currency_code' => 'Choose a supported currency for this item.',
            ]);
        }

        return $currency;
    }
}
