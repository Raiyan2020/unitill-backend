<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
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
        return [
            'refresh_token' => 'required|string|min:20',
            'device_id' => 'required_without:device_identifier|string|max:191',
            'device_identifier' => 'required_without:device_id|string|max:191',
        ];
    }
}
