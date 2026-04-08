<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Set to true if you want to allow access
    }

    public function rules()
    {
        return [
            'login-email' => 'required|email|exists:users,email',
            'login-password' => 'required|min:6',
        ];
    }

    public function messages()
    {
        return [
            'login-email.required' => 'The email field is required.',
            'login-email.email' => 'Please provide a valid email address.',
            'login-password.required' => 'The password field is required.',
            'login-password.min' => 'The password must be at least 6 characters.',
            'login-email.exists' => 'The email does not exist.',
        ];
    }
}
