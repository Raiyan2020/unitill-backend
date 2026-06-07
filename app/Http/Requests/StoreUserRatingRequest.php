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
                    $ar ? 'لقد قيّمت هذا المستخدم مسبقاً' : 'You have already rated this user'
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
            'rated_user_id.required' => $ar ? 'المستخدم المراد تقييمه مطلوب' : 'Rated user is required',
            'rated_user_id.exists' => $ar ? 'المستخدم غير موجود' : 'User not found',
            'rated_user_id.not_in' => $ar ? 'لا يمكنك تقييم نفسك' : 'You cannot rate yourself',
            'score.required' => $ar ? 'التقييم مطلوب' : 'Rating score is required',
            'score.min' => $ar ? 'التقييم يجب أن يكون بين 1 و 5' : 'Rating must be between 1 and 5',
            'score.max' => $ar ? 'التقييم يجب أن يكون بين 1 و 5' : 'Rating must be between 1 and 5',
            'comment.max' => $ar ? 'التعليق طويل جداً' : 'Comment is too long',
            'ad_id.exists' => $ar ? 'الإعلان غير موجود' : 'Ad not found',
        ];
    }
}
