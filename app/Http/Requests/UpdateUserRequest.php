<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUserRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }

    public function rules()
    {
        $userId = Auth::id();
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'phone'      => ['required', 'string', 'max:20', "unique:users,phone,{$userId}"],
            'email'      => ['required', 'email', 'max:255', "unique:users,email,{$userId}"],
            'city_id'    => ['nullable', 'exists:cities,id'],

        ];
    }
}
