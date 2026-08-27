<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactUsRequest extends FormRequest
{
    public function authorize()
    {
        return true; // مسموح للجميع يرسل الفورم
    }

    public function rules()
    {
        // Guests (no bearer token) must leave a way to be reached back;
        // logged-in users already have that on their account. Named guard
        // because this route no longer carries auth:sanctum middleware.
        $isGuest = ! $this->user('sanctum');

        return [
            'contact_reason_id' => [
                'required',
                Rule::exists('contact_reasons', 'id')->where('is_active', true),
            ],
            'message' => 'required|string|min:1|max:5000',
            'guest_name' => [$isGuest ? 'required' : 'sometimes', 'string', 'max:255'],
            'guest_email' => [$isGuest ? 'required' : 'sometimes', 'email', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'contact_reason_id.required' => 'سبب الاتصال مطلوب',
            'contact_reason_id.exists' => 'سبب الاتصال غير صالح',
            'message.required' => 'الرسالة مطلوبة',
            'message.min' => 'الرسالة قصيرة جدًا',
            'guest_name.required' => 'الاسم مطلوب',
            'guest_email.required' => 'البريد الإلكتروني مطلوب',
            'guest_email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
        ];
    }
}
