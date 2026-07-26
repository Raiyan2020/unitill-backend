<?php

namespace App\Http\Requests;

use App\Support\AdReportReason;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreAdReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(AdReportReason::allowed())],
            'comment' => [
                Rule::requiredIf(fn () => $this->input('reason') === AdReportReason::OTHER),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
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
            'reason.required' => __('api.report.reason_required'),
            'reason.in' => __('api.report.reason_invalid'),
            'comment.required' => __('api.report.details_required'),
        ];
    }
}
