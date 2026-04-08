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
        return [
            'contact_reason_id' => [
                'required',
                Rule::exists('contact_reasons', 'id')->where('is_active', true),
            ],
            'message' => 'required|string|min:1|max:5000',
        ];
    }

    public function messages()
    {
        return [
            'contact_reason_id.required' => 'سبب الاتصال مطلوب',
            'contact_reason_id.exists' => 'سبب الاتصال غير صالح',
            'message.required' => 'الرسالة مطلوبة',
            'message.min' => 'الرسالة قصيرة جدًا',
        ];
    }
}
