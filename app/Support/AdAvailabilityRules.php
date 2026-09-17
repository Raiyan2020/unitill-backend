<?php

namespace App\Support;

final class AdAvailabilityRules
{
    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'attributes.availability_from' => ['nullable', 'date', 'after_or_equal:today'],
            'attributes.availability_until' => [
                'nullable',
                'date',
                'required_with:attributes.availability_from',
                'after:attributes.availability_from',
            ],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'attributes.availability_from.after_or_equal' => __('api.ad_form.availability_from_future'),
            'attributes.availability_until.required_with' => __('api.ad_form.availability_until_required'),
            'attributes.availability_until.after' => __('api.ad_form.availability_until_after'),
        ];
    }
}
