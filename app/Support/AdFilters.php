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
            // Viewer coordinates and the radius filter drive distance sorting,
            // not attribute matching. Without reserving them here they would be
            // swept into $filters below and matched against attribute slugs that
            // never exist, silently emptying the result set.
            'lat',
            'lng',
            'postcode',
            'radius_km',
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

    /**
     * Splits raw filters into numeric ranges and exact/multi-select matches.
     *
     * A "{slug}_min" / "{slug}_max" pair with a numeric value becomes a range on
     * {slug}; everything else is matched by value.
     *
     * @return array{0: array<string, array{min?: float, max?: float}>, 1: array<string, mixed>}
     */
    protected static function partition(array $filters): array
    {
        $ranges = [];
        $exact = [];

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $exact[$key] = $value;

                continue;
            }

            if (! is_scalar($value)) {
                continue;
            }

            foreach (['_min' => 'min', '_max' => 'max'] as $suffix => $bound) {
                if (str_ends_with($key, $suffix) && is_numeric($value)) {
                    $ranges[substr($key, 0, -strlen($suffix))][$bound] = (float) $value;

                    continue 2;
                }
            }

            $exact[$key] = $value;
        }

        return [$ranges, $exact];
    }

    public static function apply(Builder $query, array $filters): void
    {
        [$ranges, $exact] = self::partition($filters);

        foreach ($exact as $slug => $value) {
            // An array means multi-select: match any of the given values (OR).
            $values = is_array($value)
                ? array_values(array_filter($value, 'is_scalar'))
                : [$value];

            if ($values === []) {
                continue;
            }

            $values = array_map(static fn ($item) => (string) $item, $values);

            $query->whereHas('attributeValues', function ($attributeQuery) use ($slug, $values) {
                $attributeQuery
                    ->whereIn('value', $values)
                    ->whereHas('definition', function ($definitionQuery) use ($slug) {
                        $definitionQuery->where('slug', $slug);
                    });
            });
        }

        foreach ($ranges as $slug => $bounds) {
            $query->whereHas('attributeValues', function ($attributeQuery) use ($slug, $bounds) {
                $attributeQuery->whereHas('definition', function ($definitionQuery) use ($slug) {
                    $definitionQuery->where('slug', $slug);
                });

                // Values are stored as TEXT, so compare numerically — a string
                // comparison would rank "9" above "10".
                if (isset($bounds['min'])) {
                    $attributeQuery->whereRaw('CAST(value AS DECIMAL(20,4)) >= ?', [$bounds['min']]);
                }

                if (isset($bounds['max'])) {
                    $attributeQuery->whereRaw('CAST(value AS DECIMAL(20,4)) <= ?', [$bounds['max']]);
                }
            });
        }
    }

    /**
     * Number of distinct attribute filters in effect. A min/max pair on the same
     * slug counts once, matching how the filter panel presents it.
     */
    public static function activeCount(array $filters): int
    {
        [$ranges, $exact] = self::partition($filters);

        return count($ranges) + count($exact);
    }
}
