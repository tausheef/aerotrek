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
            'name'         => ['required', 'string', 'min:2', 'max:100'],
            'email'        => ['required', 'email', 'unique:users,email'],
            'phone'        => ['required', 'string', 'min:10', 'max:15', 'unique:users,phone'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'account_type' => ['required', 'in:individual,company'],
            'company_name' => ['required_if:account_type,company', 'nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'             => 'This email is already registered.',
            'phone.unique'             => 'This phone number is already registered.',
            'password.confirmed'       => 'Password confirmation does not match.',
            'account_type.required'    => 'Please select account type (individual or company).',
            'company_name.required_if' => 'Company name is required for company accounts.',
        ];
    }
}