<?php

namespace App\Http\Requests;

use App\Models\UniversityDomain;
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
            'student_email' => 'required|email:rfc|max:255|unique:users,student_email|different:email',
            'password' => 'required|string|min:6|confirmed',
            'terms_accepted' => 'required|accepted',
            'phone' => 'nullable|string|max:30|unique:users,phone',
            'country_code' => 'nullable|string|max:20',
            'city_id' => 'nullable|integer|exists:cities,id',
            'device_token' => 'nullable|string',
            'device_type' => 'nullable|string|in:ios,android',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $studentEmail = $this->input('student_email');
            if (! $studentEmail) {
                return;
            }

            // Already flagged as malformed by the `email` rule — skip the domain check.
            if ($validator->errors()->has('student_email')) {
                return;
            }

            $host = strtolower(trim(substr(strrchr((string) $studentEmail, '@') ?: '', 1)));
            if ($host === '' || ! $this->isRegisteredUniversityDomain($host)) {
                $ar = $this->header('lang') === 'ar';
                $validator->errors()->add(
                    'student_email',
                    $ar
                        ? 'يرجى إدخال بريد إلكتروني جامعي بريطاني صالح.'
                        : 'Please enter a valid UK university email address.'
                );
            }
        });
    }

    /**
     * The email host must exactly match an active university domain, or be a
     * subdomain of one (e.g. "stcatz.ox.ac.uk" is accepted when "ox.ac.uk" exists).
     */
    private function isRegisteredUniversityDomain(string $host): bool
    {
        // Build the list of candidate domains: the host itself and each of its
        // parent domains, so a single indexed lookup covers subdomains too.
        $candidates = [];
        $parts = explode('.', $host);
        for ($i = 0; $i < count($parts) - 1; $i++) {
            $candidates[] = implode('.', array_slice($parts, $i));
        }

        if (empty($candidates)) {
            return false;
        }

        return UniversityDomain::query()
            ->where('status', 'active')
            ->whereIn('domain', $candidates)
            ->whereHas('university', fn ($q) => $q->where('status', 'active'))
            ->exists();
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
            'email.required' => $ar ? 'البريد الشخصي مطلوب' : 'Personal email is required',
            'email.unique' => $ar ? 'البريد الشخصي مستخدم بالفعل' : 'Personal email is already registered',
            'student_email.required' => $ar ? 'بريد الطالب الجامعي مطلوب' : 'Student email is required',
            'student_email.unique' => $ar ? 'بريد الطالب مسجّل مسبقاً' : 'Student email is already registered',
            'student_email.different' => $ar ? 'بريد الطالب يجب أن يختلف عن البريد الشخصي' : 'Student email must differ from personal email',
            'phone.unique' => $ar ? 'رقم الهاتف مسجّل مسبقاً' : 'Phone number is already registered',
            'terms_accepted.accepted' => $ar ? 'يجب الموافقة على الشروط والأحكام' : 'You must accept the terms and conditions',
            'terms_accepted.required' => $ar ? 'يجب الموافقة على الشروط والأحكام' : 'You must accept the terms and conditions',
            'password.confirmed' => $ar ? 'تأكيد كلمة المرور غير متطابق' : 'Password confirmation does not match',
            'city_id.exists' => $ar ? 'المدينة غير صالحة' : 'Invalid city',
        ];
    }
}
