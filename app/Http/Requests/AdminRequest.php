<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true; // Change to false if you want to restrict access
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email|max:255',
            'password' => 'required|string|min:6|confirmed',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => trans('validation.required', ['attribute' => trans('validation.attributes.name')]),
            'name_ar.required' => trans('validation.required', ['attribute' => trans('validation.attributes.name_ar')]),
            'email.required' => trans('validation.required', ['attribute' => trans('validation.attributes.email')]),
            'email.email' => trans('validation.email'),
            'email.unique' => trans('validation.unique', ['attribute' => trans('validation.attributes.email')]),
            'password.required' => trans('validation.required', ['attribute' => trans('validation.attributes.password')]),
            'password.min' => trans('validation.min', ['attribute' => trans('validation.attributes.password'), 'min' => 8]),
            'password.confirmed' => trans('validation.confirmed', ['attribute' => trans('validation.attributes.password')]),
            'photo.image' => trans('validation.image', ['attribute' => trans('validation.attributes.photo')]),
            'photo.mimes' => trans('validation.mimes', ['attribute' => trans('validation.attributes.photo')]),
            'photo.max' => trans('validation.max', ['attribute' => trans('validation.attributes.photo'), 'max' => 2]),
        ];
    }

}
