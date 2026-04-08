<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email:rfc|max:255|unique:users,email',
            'phone' => 'required|string|max:30|unique:users,phone',
            'country_code' => 'required|string|max:20',
            'city_id' => 'nullable|integer|exists:cities,id',
            'password' => 'required|string|min:6|confirmed',
            'device_token' => 'nullable|string',
            'device_type' => 'nullable|string|in:ios,android',
            'student_email' => 'nullable|email:rfc|max:255|unique:users,student_email|different:email',
//            'terms_accepted' => 'required_with:student_email|accepted',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('student_email')) {
                return;
            }
            $lower = strtolower((string) $this->input('student_email'));
            if (! str_ends_with($lower, '.ac.uk')) {
                $ar = $this->header('lang') === 'ar';
                $validator->errors()->add(
                    'student_email',
                    $ar
                        ? 'يجب أن يكون بريد الطالب بنطاق جامعي بريطاني (.ac.uk)'
                        : 'Student email must be a UK academic address (.ac.uk)'
                );
            }
        });
    }

    /**
     * نفس شكل sendError في باقي الـ API: status + message + data (بدون مفتاح errors منفصل).
     */
    protected function failedValidation(Validator $validator): void
    {
        $messages = $validator->errors()->toArray();

        throw new HttpResponseException(
            sendError(
                $validator->errors()->first(),
                $messages
            )
        );
    }

    public function messages(): array
    {
        $ar = $this->header('lang') === 'ar';

        return [
            'email.unique' => $ar ? 'البريد الشخصي مستخدم بالفعل' : 'Personal email is already registered',
            'phone.unique' => $ar ? 'رقم الهاتف مسجّل مسبقاً' : 'Phone number is already registered',
            'phone.required' => $ar ? 'رقم الهاتف مطلوب' : 'Phone is required',
            'country_code.required' => $ar ? 'رمز الدولة مطلوب' : 'Country code is required',
            'student_email.unique' => $ar ? 'بريد الطالب مسجّل مسبقاً' : 'Student email is already registered',
            'terms_accepted.accepted' => $ar ? 'يجب الموافقة على الشروط والأحكام' : 'You must accept the terms and conditions',
            'city_id.exists' => $ar ? 'المدينة غير صالحة' : 'Invalid city',
        ];
    }
}
