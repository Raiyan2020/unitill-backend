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
            'current_password.required' => $ar ? 'كلمة المرور الحالية مطلوبة' : 'Current password is required',
            'new_password.required' => $ar ? 'كلمة المرور الجديدة مطلوبة' : 'New password is required',
            'new_password.min' => $ar ? 'كلمة المرور الجديدة 6 أحرف على الأقل' : 'New password must be at least 6 characters',
            'new_password.confirmed' => $ar ? 'تأكيد كلمة المرور غير متطابق' : 'Password confirmation does not match',
        ];
    }
}
