<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('device_id') && ! $this->filled('device_identifier')) {
            $this->merge(['device_identifier' => $this->input('device_id')]);
        }
    }

    public function rules(): array
    {
        $type = $this->input('type', 'data');

        $rules = [
            'type' => 'required|string|in:data,fingerprint',
            'device_id' => 'nullable|string|max:191',
            'device_identifier' => 'nullable|string|max:191',
            'device_type' => 'nullable|string|in:ios,android',
            'device_token' => 'nullable|string',
            'device_name' => 'nullable|string|max:191',
            'city_name' => 'nullable|string|max:191',
            'country_code' => 'nullable|string|max:2',
            'enable_biometric' => 'nullable|boolean',
        ];

        if ($type === 'fingerprint') {
            $rules['biometric_token'] = 'required|string|min:20';
            $rules['device_id'] = 'required_without:device_identifier|string|max:191';
            $rules['device_identifier'] = 'required_without:device_id|string|max:191';
        } else {
            $rules['email'] = 'required_without:login|nullable|email';
            $rules['login'] = 'required_without:email|string';
            $rules['password'] = 'required|string';
            $rules['device_id'] = 'required_without:device_identifier|string|max:191';
            $rules['device_identifier'] = 'required_without:device_id|string|max:191';
        }

        return $rules;
    }
}
