<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ad extends Model
{
    protected $fillable = [
        'user_id',
        'public_id',
        'title',
        'subtitle',
        'license_plate',
        'description',
        'country_id',
        'city_id',
        'region',
        'postcode',
        'location_name',
        'latitude',
        'longitude',
        'main_category_id',
        'sub_category_id',
        'cover_image',
        'price',
        'currency',
        'is_negotiable',
        'is_verified',
        'slug',
        'status',
        'published_at',
        'expires_at',
        'paused_at',
        'sold_at',
        'sold_to_user_id',
        'is_sold_outside',
        'inactive_reason',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_negotiable' => 'boolean',
        'is_verified' => 'boolean',
        'is_sold_outside' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'paused_at' => 'datetime',
        'sold_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Ad $ad) {
            if (empty($ad->public_id)) {
                do {
                    $ad->public_id = strtoupper(Str::random(10));
                } while (static::where('public_id', $ad->public_id)->exists());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function soldToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_to_user_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function mainCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'main_category_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(AdImage::class)->orderBy('sort_order');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(AdAttributeValue::class);
    }

    /**
     * القسم المستخدم لتحميل تعريفات المواصفات (الفرعي إن وُجد وإلا الرئيسي).
     */
    public function specificationsCategoryId(): ?int
    {
        return $this->sub_category_id ?? $this->main_category_id;
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ad_favorites')->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(AdReport::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function coverImageUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        $path = ltrim((string) $this->cover_image, '/');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url('/storage/'.$path);
    }

    public function formattedPrice(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        $symbols = [
            'GBP' => '£',
            'USD' => '$',
            'EUR' => '€',
        ];

        $currency = strtoupper((string) ($this->currency ?? 'GBP'));
        $symbol = $symbols[$currency] ?? $currency.' ';

        return $symbol.number_format((float) $this->price, 0);
    }
}
