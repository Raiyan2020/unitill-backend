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

    public const MILEAGE_LOW_TO_HIGH = 'mileage_low_to_high';

    public const MILEAGE_HIGH_TO_LOW = 'mileage_high_to_low';

    public const YEAR_NEW_TO_OLD = 'year_new_to_old';

    public const YEAR_OLD_TO_NEW = 'year_old_to_new';

    /**
     * Sorts offered only on the Cars category, keyed by the attribute slug they
     * read and the direction they apply.
     *
     * @var array<string, array{slug: string, direction: string}>
     */
    protected const ATTRIBUTE_SORTS = [
        self::MILEAGE_LOW_TO_HIGH => ['slug' => 'mileage', 'direction' => 'asc'],
        self::MILEAGE_HIGH_TO_LOW => ['slug' => 'mileage', 'direction' => 'desc'],
        self::YEAR_NEW_TO_OLD => ['slug' => 'year', 'direction' => 'desc'],
        self::YEAR_OLD_TO_NEW => ['slug' => 'year', 'direction' => 'asc'],
    ];

    public static function allowed(): array
    {
        return [
            self::NEWEST,
            self::OLDEST,
            self::PRICE_LOW_TO_HIGH,
            self::PRICE_HIGH_TO_LOW,
            self::DISTANCE_NEAREST,
            self::DISTANCE_FARTHEST,
            self::MILEAGE_LOW_TO_HIGH,
            self::MILEAGE_HIGH_TO_LOW,
            self::YEAR_NEW_TO_OLD,
            self::YEAR_OLD_TO_NEW,
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
            self::MILEAGE_LOW_TO_HIGH => self::MILEAGE_LOW_TO_HIGH,
            self::MILEAGE_HIGH_TO_LOW => self::MILEAGE_HIGH_TO_LOW,
            self::YEAR_NEW_TO_OLD => self::YEAR_NEW_TO_OLD,
            self::YEAR_OLD_TO_NEW => self::YEAR_OLD_TO_NEW,
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

        if (isset(self::ATTRIBUTE_SORTS[$normalized])) {
            self::applyAttributeSort($query, self::ATTRIBUTE_SORTS[$normalized]);

            return;
        }

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

    /**
     * Orders by a numeric attribute (mileage, year) held in ad_attribute_values.
     * A correlated subquery keeps the row count intact — joining would duplicate
     * ads that carry several attribute rows.
     *
     * @param  array{slug: string, direction: string}  $config
     */
    protected static function applyAttributeSort(Builder $query, array $config): void
    {
        // Stored as TEXT, so cast before ordering: "9" sorts above "10" otherwise.
        $subquery = '(select cast(av.value as decimal(20,4)) from ad_attribute_values av '
            .'inner join category_attribute_definitions cad on cad.id = av.category_attribute_definition_id '
            .'where av.ad_id = ads.id and cad.slug = ? limit 1)';

        // Ads without the attribute sort last regardless of direction.
        $query->orderByRaw("({$subquery}) is null asc", [$config['slug']])
            ->orderByRaw("{$subquery} {$config['direction']}", [$config['slug']])
            ->orderByDesc('id');
    }

    /**
     * The global sort list, plus the Cars-only mileage/year add-ons when
     * $includeVehicleSorts is set.
     */
    public static function options(string $lang = 'en', bool $includeVehicleSorts = false): array
    {
        $ar = $lang === 'ar';

        $options = [
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

        if ($includeVehicleSorts) {
            $options = array_merge($options, [
                [
                    'value' => self::MILEAGE_LOW_TO_HIGH,
                    'label' => $ar ? 'المسافة المقطوعة: من الأقل للأعلى' : 'Mileage: Low to High',
                ],
                [
                    'value' => self::MILEAGE_HIGH_TO_LOW,
                    'label' => $ar ? 'المسافة المقطوعة: من الأعلى للأقل' : 'Mileage: High to Low',
                ],
                [
                    'value' => self::YEAR_NEW_TO_OLD,
                    'label' => $ar ? 'سنة الصنع: من الأحدث للأقدم' : 'Year: New to Old',
                ],
                [
                    'value' => self::YEAR_OLD_TO_NEW,
                    'label' => $ar ? 'سنة الصنع: من الأقدم للأحدث' : 'Year: Old to New',
                ],
            ]);
        }

        return $options;
    }
}
