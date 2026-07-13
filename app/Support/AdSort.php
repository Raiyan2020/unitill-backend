<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class AdSort
{
    public const NEWEST = 'newest';

    public const PRICE_LOW_TO_HIGH = 'price_low_to_high';

    public const PRICE_HIGH_TO_LOW = 'price_high_to_low';

    public const OLDEST = 'oldest';

    public const DISTANCE_NEAREST = 'distance_nearest';

    public const DISTANCE_FARTHEST = 'distance_farthest';

    public static function allowed(): array
    {
        return [
            self::NEWEST,
            self::OLDEST,
            self::PRICE_LOW_TO_HIGH,
            self::PRICE_HIGH_TO_LOW,
            self::DISTANCE_NEAREST,
            self::DISTANCE_FARTHEST,
            'price_asc',
            'price_desc',
        ];
    }

    public static function normalize(?string $sort): string
    {
        return match ($sort) {
            'price_asc', self::PRICE_LOW_TO_HIGH => self::PRICE_LOW_TO_HIGH,
            'price_desc', self::PRICE_HIGH_TO_LOW => self::PRICE_HIGH_TO_LOW,
            self::OLDEST => self::OLDEST,
            self::DISTANCE_NEAREST => self::DISTANCE_NEAREST,
            self::DISTANCE_FARTHEST => self::DISTANCE_FARTHEST,
            default => self::NEWEST,
        };
    }

    /**
     * Applies the sort. Distance sorts require the viewer's coordinates; when
     * they are missing the query falls back to "newest first".
     */
    public static function apply(
        Builder $query,
        ?string $sort,
        ?float $lat = null,
        ?float $lng = null
    ): void {
        $normalized = self::normalize($sort);

        if ($normalized === self::DISTANCE_NEAREST || $normalized === self::DISTANCE_FARTHEST) {
            if ($lat === null || $lng === null) {
                $query->orderByDesc('published_at')->orderByDesc('id');

                return;
            }

            // Great-circle (haversine) distance in km. LEAST() guards against
            // floating point rounding pushing the acos() argument above 1.
            $haversine = '(6371 * acos(LEAST(1, cos(radians(?)) * cos(radians(latitude)) '
                .'* cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))';
            $direction = $normalized === self::DISTANCE_NEAREST ? 'asc' : 'desc';

            $query->orderByRaw('(latitude is null) asc')
                ->orderByRaw("{$haversine} {$direction}", [$lat, $lng, $lat])
                ->orderByDesc('id');

            return;
        }

        match ($normalized) {
            self::OLDEST => $query->orderBy('published_at')->orderBy('id'),
            self::PRICE_LOW_TO_HIGH => $query->orderBy('price')->orderByDesc('id'),
            self::PRICE_HIGH_TO_LOW => $query->orderByDesc('price')->orderByDesc('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    public static function options(string $lang = 'en'): array
    {
        $ar = $lang === 'ar';

        return [
            [
                'value' => self::NEWEST,
                'label' => $ar ? 'الأحدث أولاً' : 'Newest First',
            ],
            [
                'value' => self::OLDEST,
                'label' => $ar ? 'الأقدم أولاً' : 'Oldest First',
            ],
            [
                'value' => self::PRICE_LOW_TO_HIGH,
                'label' => $ar ? 'السعر: من الأقل للأعلى' : 'Price: Low to High',
            ],
            [
                'value' => self::PRICE_HIGH_TO_LOW,
                'label' => $ar ? 'السعر: من الأعلى للأقل' : 'Price: High to Low',
            ],
            [
                'value' => self::DISTANCE_NEAREST,
                'label' => $ar ? 'الأقرب أولاً' : 'Distance: Nearest',
            ],
            [
                'value' => self::DISTANCE_FARTHEST,
                'label' => $ar ? 'الأبعد أولاً' : 'Distance: Farthest',
            ],
        ];
    }
}
