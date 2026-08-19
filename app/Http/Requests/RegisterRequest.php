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
            'terms_version' => 'nullable|string|max:50',
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
                $validator->errors()->add(
                    'student_email',
                    __('api.auth.invalid_university_email')
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
            'email.required' => __('api.register.personal_email_required'),
            'email.unique' => __('api.register.personal_email_taken'),
            'student_email.required' => __('api.register.student_email_required'),
            'student_email.unique' => __('api.register.student_email_taken'),
            'student_email.different' => __('api.register.student_email_must_differ'),
            'phone.unique' => __('api.register.phone_taken'),
            'terms_accepted.accepted' => __('api.register.terms_required'),
            'terms_accepted.required' => __('api.register.terms_required'),
            'password.confirmed' => __('api.register.password_confirmed'),
            'city_id.exists' => __('api.register.invalid_city'),
        ];
    }
}
