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
            'email'        => ['required', 'email', 'unique:mongodb.users,email'],
            'phone'        => ['required', 'string', 'min:10', 'max:15', 'unique:mongodb.users,phone'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'    => 'This email is already registered.',
            'phone.unique'    => 'This phone number is already registered.',
            'password.min'    => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}