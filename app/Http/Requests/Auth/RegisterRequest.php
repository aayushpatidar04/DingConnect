<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'phone'                 => ['required', 'string', 'max:20', 'unique:users,phone'],
            'shop_name'             => ['nullable', 'string', 'max:255'],
            'address'               => ['nullable', 'string'],
            'city'                  => ['nullable', 'string', 'max:100'],
            'county'                => ['nullable', 'string', 'max:100'],
            'postcode'              => ['nullable', 'string', 'max:20'],
            'vat_number'            => ['nullable', 'string', 'max:50'],
            'company_reg_number'    => ['nullable', 'string', 'max:50'],
        ];
    }
}
