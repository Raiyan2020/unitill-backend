<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',

            'city_id' => 'required|exists:cities,id',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',

            'category_id' => 'required|exists:categories,id',
            'filter_id' => 'nullable|exists:filters,id',

            'status' => 'required|in:0,1',
            'payment_status' => 'required|in:0,1',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'end_date' => 'nullable|date',

            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:51200',
            'video' => 'nullable|mimes:mp4,avi,mov,webm|max:51200',

        ];
    }
}
