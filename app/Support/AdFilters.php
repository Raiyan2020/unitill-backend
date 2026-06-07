<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdFilters
{
    public static function reservedQueryKeys(): array
    {
        return [
            'search',
            'q',
            'sort',
            'per_page',
            'page',
            'category_id',
            'main_category_id',
            'sub_category_id',
            'city_id',
            'price_min',
            'price_max',
            'is_negotiable',
            'filters',
        ];
    }

    public static function extractFromRequest(Request $request): array
    {
        $filters = [];

        if (is_array($request->input('filters'))) {
            $filters = $request->input('filters');
        } elseif (is_string($request->input('filters'))) {
            $decoded = json_decode($request->input('filters'), true);
            if (is_array($decoded)) {
                $filters = $decoded;
            }
        }

        foreach ($request->query() as $key => $value) {
            if (in_array($key, self::reservedQueryKeys(), true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $filters[$key] = is_scalar($value) ? (string) $value : $value;
        }

        return $filters;
    }

    public static function apply(Builder $query, array $filters): void
    {
        foreach ($filters as $slug => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $query->whereHas('attributeValues', function ($attributeQuery) use ($slug, $value) {
                $attributeQuery
                    ->where('value', (string) $value)
                    ->whereHas('definition', function ($definitionQuery) use ($slug) {
                        $definitionQuery->where('slug', $slug);
                    });
            });
        }
    }
}
