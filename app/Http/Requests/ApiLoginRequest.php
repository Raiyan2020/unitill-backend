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
        $type = $this->input('type', 'data');

        $rules = [
            'type' => 'required|string|in:data,fingerprint',
            'device_type' => 'nullable|string|in:ios,android',
            'device_token' => 'nullable|string',
            'device_name' => 'nullable|string|max:191',
            'device_identifier' => 'nullable|string|max:191',
            'city_name' => 'nullable|string|max:191',
            'country_code' => 'nullable|string|max:2',
        ];

        if ($type === 'fingerprint') {
            $rules['user_id'] = 'required|integer|exists:users,id';
            $rules['device_identifier'] = 'required|string|max:191';
        } else {
            $rules['email'] = 'required_without:login|nullable|email';
            $rules['login'] = 'required_without:email|string';
            $rules['password'] = 'required|string';
        }

        return $rules;
    }
}
