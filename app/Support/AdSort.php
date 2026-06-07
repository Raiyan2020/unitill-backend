<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class AdSort
{
    public const NEWEST = 'newest';

    public const PRICE_LOW_TO_HIGH = 'price_low_to_high';

    public const PRICE_HIGH_TO_LOW = 'price_high_to_low';

    public const OLDEST = 'oldest';

    public static function allowed(): array
    {
        return [
            self::NEWEST,
            self::OLDEST,
            self::PRICE_LOW_TO_HIGH,
            self::PRICE_HIGH_TO_LOW,
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
            default => self::NEWEST,
        };
    }

    public static function apply(Builder $query, ?string $sort): void
    {
        match (self::normalize($sort)) {
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
        ];
    }
}
