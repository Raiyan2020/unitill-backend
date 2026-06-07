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
            'seller_type' => ['required', Rule::in(['business', 'service_provider', 'organization'])],
            'identity_confirmed_by_others' => 'nullable|boolean',
            'operations_city' => 'required|string|max:191',
            'primary_contact_name' => 'required|string|max:191',
            'contact_email' => 'required|email|max:191',
            'contact_phone' => 'required|string|max:32',
            'category_id' => 'required|integer|exists:categories,id',
            'offers_summary' => 'required|string|min:10|max:5000',
            'estimated_listings_count' => ['required', Rule::in(['1_10', '10_plus'])],
            'preferred_student_contact_method' => ['required', Rule::in(['email', 'phone', 'website'])],
            'confirm_not_student' => 'accepted',
            'ack_review' => 'accepted',
            'ack_removal_rights' => 'accepted',
            'ack_terms' => 'accepted',
        ];
    }

    public function messages(): array
    {
        $ar = $this->header('lang') === 'ar';

        return [
            'seller_type.required' => $ar ? 'نوع البائع مطلوب' : 'Seller type is required',
            'operations_city.required' => $ar ? 'مدينة العمل مطلوبة' : 'City of operations is required',
            'primary_contact_name.required' => $ar ? 'اسم جهة الاتصال مطلوب' : 'Primary contact name is required',
            'contact_email.required' => $ar ? 'البريد الإلكتروني مطلوب' : 'Contact email is required',
            'contact_phone.required' => $ar ? 'رقم الهاتف مطلوب' : 'Contact phone is required',
            'category_id.required' => $ar ? 'الفئة مطلوبة' : 'Category is required',
            'offers_summary.required' => $ar ? 'وصف العروض مطلوب' : 'Offer description is required',
            'estimated_listings_count.required' => $ar ? 'العدد التقديري للإعلانات مطلوب' : 'Estimated listings count is required',
            'preferred_student_contact_method.required' => $ar ? 'طريقة التواصل المفضلة مطلوبة' : 'Preferred contact method is required',
            'confirm_not_student.accepted' => $ar ? 'يجب تأكيد أنك لست طالباً' : 'You must confirm you are not a student',
            'ack_review.accepted' => $ar ? 'يجب الموافقة على مراجعة الطلب' : 'You must acknowledge the review process',
            'ack_removal_rights.accepted' => $ar ? 'يجب الموافقة على حق UniTill في إزالة الحالة' : 'You must acknowledge removal rights',
            'ack_terms.accepted' => $ar ? 'يجب قبول الشروط وسياسة الخصوصية' : 'You must accept the terms and privacy policy',
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
