<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'nullable|string|min:6|confirmed',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            Rule::unique('admin', 'email')->ignore($this->id),
        ];
    }

    public function messages()
    {
        return [
            'name.required' => trans('validation.required', ['attribute' => trans('validation.attributes.name')]),

            'email.required' => trans('validation.required', ['attribute' => trans('validation.attributes.email')]),
            'email.email' => trans('validation.email', ['attribute' => trans('validation.attributes.email')]),
            'email.unique' => trans('validation.unique', ['attribute' => trans('validation.attributes.email')]),

            'password.min' => trans('validation.min', ['attribute' => trans('validation.attributes.password'), 'min' => 8]),
            'password.confirmed' => trans('validation.confirmed', ['attribute' => trans('validation.attributes.password')]),

            'photo.image' => trans('validation.image', ['attribute' => trans('validation.attributes.photo')]),
            'photo.mimes' => trans('validation.mimes', ['attribute' => trans('validation.attributes.photo'), 'values' => 'jpeg, png, jpg, gif, svg']),
            'photo.max' => trans('validation.max', ['attribute' => trans('validation.attributes.photo'), 'max' => 2]),
        ];
    }

}
