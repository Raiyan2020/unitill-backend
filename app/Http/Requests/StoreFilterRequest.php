<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFilterRequest extends FormRequest
{
    public function authorize()
    {
        return true; // يمكنك تخصيص الصلاحيات لاحقًا
    }

    public function rules()
    {
        return [
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'required|string|max:255',
            'status' => 'nullable|boolean',
            'filters.*' => 'nullable|string|max:255',
        ];
    }
}

