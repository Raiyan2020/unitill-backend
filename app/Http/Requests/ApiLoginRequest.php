<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required_without:login|nullable|email',
            'login' => 'required_without:email|string',
            'password' => 'required|string',
            'device_type' => 'nullable|string|in:ios,android',
            'device_token' => 'nullable|string',
        ];
    }
}
