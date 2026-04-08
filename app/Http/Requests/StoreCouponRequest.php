<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'               => 'required|string|max:50|unique:coupons,code',
            'type'               => 'required|in:percent,fixed',
            'value'              => 'required|numeric|min:0',

            'max_uses'           => 'nullable|integer|min:1',
            'max_uses_per_user'  => 'nullable|integer|min:1',
            'used_count'         => 'nullable|integer|min:0',

            'starts_at'          => 'nullable|date',
            'expires_at'         => 'nullable|date|after_or_equal:starts_at',

            'is_active'          => 'required|boolean',
        ];
    }

    public function prepareForValidation()
    {
        $this->merge([
            'used_count' => $this->used_count ?? 0,
        ]);
    }
}
