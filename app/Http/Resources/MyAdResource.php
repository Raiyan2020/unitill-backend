<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyAdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lang = $request->header('lang', 'en');
        $locale = $lang === 'ar' ? 'ar' : 'en';
        $statusMeta = $this->statusMeta($locale);

        $data = [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'title' => $this->title,
            'price' => $this->price !== null ? (float) $this->price : null,
            'currency' => $this->currency,
            'formatted_price' => $this->formattedPrice(),
            'cover_image_url' => $this->coverImageUrl(),
            'status' => $this->status,
            'status_label' => $statusMeta['label'],
            'status_badge' => $statusMeta['badge'],
            'available_actions' => $statusMeta['actions'],
            'payment_status' => $this->payment_status,
            'listing_fee' => $this->listing_fee !== null ? (float) $this->listing_fee : null,
            'payment_required' => $this->status === 'pending'
                && $this->payment_status === 'requires_payment',
        ];

        if ($this->status === 'published') {
            $data['expires_at'] = $this->expires_at?->toIso8601String();
            $data['expires_in_days'] = $this->expiresInDays();
            $data['expires_label'] = $this->expiresLabel($locale);
        }

        if (in_array($this->status, ['expired', 'paused'], true)) {
            $data['inactive_reason'] = $this->inactive_reason;
            $data['inactive_reason_label'] = $this->inactiveReasonLabel($locale);
            $data['deactivated_at'] = ($this->paused_at ?? $this->expires_at)?->toIso8601String();
            $data['deactivated_ago'] = ($this->paused_at ?? $this->expires_at)
                ? ($this->paused_at ?? $this->expires_at)->locale($locale)->diffForHumans()
                : null;
        }

        if ($this->status === 'sold') {
            $data['sold_at'] = $this->sold_at?->toIso8601String();
            $data['sold_on_label'] = $this->sold_at
                ? strtoupper($this->sold_at->locale($locale)->translatedFormat('M d'))
                : null;
            $data['is_sold_outside'] = (bool) $this->is_sold_outside;
            $data['buyer'] = $this->soldBuyerPayload($locale);
        }

        return $data;
    }

    protected function statusMeta(string $locale): array
    {
        $ar = $locale === 'ar';

        return match ($this->status) {
            'published' => [
                'label' => __('api.my_ad.status_live'),
                'badge' => 'live',
                'actions' => ['edit', 'mark_as_sold'],
            ],
            'paused' => [
                'label' => __('api.my_ad.status_paused'),
                'badge' => 'paused',
                'actions' => ['activate', 'delete'],
            ],
            'expired' => [
                'label' => __('api.my_ad.status_expired'),
                'badge' => 'expired',
                'actions' => ['activate', 'delete'],
            ],
            'sold' => [
                'label' => __('api.my_ad.status_sold'),
                'badge' => 'sold',
                'actions' => ['see_details', 'relist'],
            ],
            'pending' => [
                'label' => __('api.my_ad.status_pending'),
                'badge' => 'pending',
                'actions' => ['see_details', 'pay', 'delete'],
            ],
            'draft' => [
                'label' => __('api.my_ad.status_draft'),
                'badge' => 'draft',
                'actions' => ['see_details', 'publish', 'delete'],
            ],
            default => [
                'label' => strtoupper((string) $this->status),
                'badge' => (string) $this->status,
                'actions' => ['see_details'],
            ],
        };
    }

    protected function expiresInDays(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }

        return max(0, now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false));
    }

    protected function expiresLabel(string $locale): ?string
    {
        $days = $this->expiresInDays();
        if ($days === null) {
            return null;
        }

        return $locale === 'ar'
            ? "ينتهي خلال {$days} يوم"
            : "EXPIRES IN {$days} DAYS";
    }

    protected function inactiveReasonLabel(string $locale): ?string
    {
        $ar = $locale === 'ar';

        return match ($this->inactive_reason) {
            'auto_expired' => __('api.my_ad.auto_expired'),
            'manual_pause' => __('api.my_ad.manually_paused'),
            default => null,
        };
    }

    protected function soldBuyerPayload(string $locale): ?array
    {
        if ($this->is_sold_outside) {
            return [
                'name' => $locale === 'ar' ? 'مشتري خارج التطبيق' : 'External Buyer',
                'badge' => $locale === 'ar' ? 'بيع خارجي' : 'SOLD OUTSIDE',
                'is_sold_outside' => true,
            ];
        }

        if (! $this->soldToUser) {
            return null;
        }

        $name = trim(($this->soldToUser->first_name ?? '').' '.($this->soldToUser->last_name ?? ''));
        if ($name === '') {
            $name = (string) ($this->soldToUser->name ?? '');
        }

        return [
            'id' => $this->soldToUser->id,
            'name' => $name,
            'badge' => $locale === 'ar' ? 'بيع آمن' : 'UNITILL SECURE',
            'is_sold_outside' => false,
        ];
    }
}
