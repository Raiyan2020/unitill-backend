<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreUserRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'rated_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([Auth::id()]),
            ],
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'ad_id' => 'nullable|integer|exists:ads,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $exists = \App\Models\UserRating::query()
                ->where('rater_id', Auth::id())
                ->where('rated_user_id', $this->input('rated_user_id'))
                ->exists();

            if ($exists) {
                $ar = $this->header('lang') === 'ar';
                $validator->errors()->add(
                    'rated_user_id',
                    __('api.rating.already_rated')
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
            'rated_user_id.required' => __('api.rating.rated_user_required'),
            'rated_user_id.exists' => __('api.rating.user_not_found'),
            'rated_user_id.not_in' => __('api.rating.cannot_rate_self'),
            'score.required' => __('api.rating.score_required'),
            'score.min' => __('api.rating.score_range'),
            'score.max' => __('api.rating.score_range'),
            'comment.max' => __('api.rating.comment_too_long'),
            'ad_id.exists' => __('api.rating.ad_not_found'),
        ];
    }
}
