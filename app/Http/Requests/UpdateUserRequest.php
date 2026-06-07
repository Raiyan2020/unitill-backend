<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $userId = Auth::id();

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'image' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', "unique:users,phone,{$userId}"],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', "unique:users,email,{$userId}"],
            'city_id' => ['sometimes', 'nullable', 'exists:cities,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (
                ! $this->hasAny(['first_name', 'last_name', 'phone', 'email', 'city_id'])
                && ! $this->hasFile('image')
            ) {
                $ar = $this->header('lang') === 'ar';
                $validator->errors()->add(
                    'profile',
                    $ar ? 'يجب إرسال حقل واحد على الأقل للتحديث' : 'At least one field is required to update'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            sendError(
                $validator->errors()->first(),
                $validator->errors()->toArray(),
                422
            )
        );
    }

    public function messages(): array
    {
        $ar = $this->header('lang') === 'ar';

        return [
            'first_name.required' => $ar ? 'الاسم الأول مطلوب' : 'First name is required',
            'last_name.required' => $ar ? 'اسم العائلة مطلوب' : 'Last name is required',
            'image.image' => $ar ? 'يجب أن تكون الصورة بصيغة صالحة' : 'Image must be a valid file',
            'image.mimes' => $ar ? 'صيغ الصورة المسموحة: jpeg, png, jpg, webp' : 'Allowed image types: jpeg, png, jpg, webp',
            'image.max' => $ar ? 'حجم الصورة يجب ألا يتجاوز 5MB' : 'Image size must not exceed 5MB',
            'email.unique' => $ar ? 'البريد الشخصي مستخدم بالفعل' : 'Email is already in use',
            'phone.unique' => $ar ? 'رقم الهاتف مسجّل مسبقاً' : 'Phone number is already in use',
            'city_id.exists' => $ar ? 'المدينة غير صالحة' : 'Invalid city',
        ];
    }
}
