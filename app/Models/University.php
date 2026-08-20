<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    protected $fillable = [
        'name',
        'country_code',
        'state',
        'city',
        'city_id',
        'status',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(UniversityDomain::class);
    }

    /**
     * Named cityRef rather than city — the table also has a raw `city` text
     * column (legacy, kept only for backward-compat search/exports), and
     * Eloquent's attribute lookup would silently shadow a same-named
     * relation with that column's string value instead of ever loading this.
     */
    public function cityRef(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * The active university whose domain list contains this email host, or
     * one of its parent domains (so "stcatz.ox.ac.uk" resolves via "ox.ac.uk").
     * Single source of truth for both RegisterRequest's validation and
     * AuthController's city_id auto-fill — keeping them in sync matters,
     * since a mismatch would mean a student passes verification against a
     * university that registration then fails to resolve for location.
     */
    public static function resolveForEmailHost(?string $host): ?self
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return null;
        }

        $parts = explode('.', $host);
        $candidates = [];
        for ($i = 0; $i < count($parts) - 1; $i++) {
            $candidates[] = implode('.', array_slice($parts, $i));
        }

        if (empty($candidates)) {
            return null;
        }

        return static::query()
            ->where('status', 'active')
            ->whereHas('domains', fn ($q) => $q->where('status', 'active')->whereIn('domain', $candidates))
            ->first();
    }
}
