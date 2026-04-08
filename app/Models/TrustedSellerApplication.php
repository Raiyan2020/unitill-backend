<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedSellerApplication extends Model
{
    protected static function booted(): void
    {
        static::saved(function (TrustedSellerApplication $application) {
            static::syncTrustedSellerBadgeForUser($application->user_id);
        });

        static::deleted(function (TrustedSellerApplication $application) {
            static::syncTrustedSellerBadgeForUser($application->user_id);
        });
    }

    protected static function syncTrustedSellerBadgeForUser(int $userId): void
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $latestApproved = static::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->orderByDesc('updated_at')
            ->first();

        $user->forceFill([
            'is_trusted_seller' => $latestApproved !== null,
            'trusted_seller_verified_at' => $latestApproved?->updated_at,
        ])->saveQuietly();
    }

    protected $fillable = [
        'user_id',
        'seller_type',
        'is_non_student_confirmed',
        'operations_city',
        'primary_contact_name',
        'contact_email',
        'contact_phone',
        'category_id',
        'offers_summary',
        'estimated_ads_volume',
        'preferred_student_contact_method',
        'ack_review_discretion',
        'ack_unitill_manages_directory',
        'ack_no_app_access',
        'status',
    ];

    protected $casts = [
        'is_non_student_confirmed' => 'boolean',
        'ack_review_discretion' => 'boolean',
        'ack_unitill_manages_directory' => 'boolean',
        'ack_no_app_access' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
