<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ];
    }

    public function messages(): array
    {
        $ar = $this->header('lang') === 'ar';

        return [
            'current_password.required' => __('api.password.current_required'),
            'new_password.required' => __('api.password.new_required'),
            'new_password.min' => __('api.password.new_min'),
            'new_password.confirmed' => __('api.password.confirmation_mismatch'),
        ];
    }
}
