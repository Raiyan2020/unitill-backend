<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTrustedSellerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            // The mobile app submits a simplified application: the seller entity
            // type plus the non-student confirmation. All other fields remain
            // optional so a richer form (e.g. dashboard) can still provide them.
            'entity_type' => ['required', Rule::in(['business', 'service_provider', 'organization'])],
            'is_non_student_confirmed' => 'nullable|boolean',
            'identity_confirmed_by_others' => 'nullable|boolean',
            'operations_city' => 'nullable|string|max:191',
            'primary_contact_name' => 'nullable|string|max:191',
            'contact_email' => 'nullable|email|max:191',
            'contact_phone' => 'nullable|string|max:32',
            'category_id' => 'nullable|integer|exists:categories,id',
            'offers_summary' => 'nullable|string|max:5000',
            'estimated_listings_count' => ['nullable', Rule::in(['1_10', '10_plus'])],
            'preferred_student_contact_method' => ['nullable', Rule::in(['email', 'phone', 'website'])],
        ];
    }

    public function messages(): array
    {
        $ar = $this->header('lang') === 'ar';

        return [
            'entity_type.required' => $ar ? 'نوع البائع مطلوب' : 'Seller type is required',
            'entity_type.in' => $ar ? 'نوع البائع غير صالح' : 'Invalid seller type',
            'contact_email.email' => $ar ? 'البريد الإلكتروني غير صالح' : 'Invalid contact email',
            'category_id.exists' => $ar ? 'الفئة غير موجودة' : 'Category does not exist',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            sendError($validator->errors()->first(), $validator->errors()->toArray(), 422)
        );
    }

    public function estimatedAdsVolume(): string
    {
        return $this->input('estimated_listings_count') === '10_plus' ? 'multiple' : 'single';
    }
}
