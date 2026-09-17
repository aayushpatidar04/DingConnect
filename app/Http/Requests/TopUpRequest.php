<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'countryIso'   => ['required', 'string', 'size:2'],
            'operatorCode' => ['required', 'string', 'max:100'],
            'mobileNumber' => ['required', 'string', 'min:4', 'max:20'],
            'amount'       => ['required', 'numeric', 'min:1', 'max:10000'],
            'product_type' => ['nullable', 'string', 'in:mobile_topup,egift,voucher,pin,data'],
        ];
    }
}
