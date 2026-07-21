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
                    __('api.profile.at_least_one_field')
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
            'first_name.required' => __('api.profile.first_name_required'),
            'last_name.required' => __('api.profile.last_name_required'),
            'image.image' => __('api.profile.image_invalid'),
            'image.mimes' => __('api.profile.image_types'),
            'image.max' => __('api.profile.image_max_size'),
            'email.unique' => __('api.profile.email_taken'),
            'phone.unique' => __('api.profile.phone_taken'),
            'city_id.exists' => __('api.profile.invalid_city'),
        ];
    }
}
