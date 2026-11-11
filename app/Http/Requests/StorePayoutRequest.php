<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayoutRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'wallet_id' => ['required', 'string', 'max:64'],
            // Amount in cents: 1050 means 10.50 USD. Decimals are rejected on purpose.
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'currency' => ['required', 'string', 'in:USD'],
        ];
    }
}
