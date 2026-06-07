<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActiveSessionResource extends JsonResource
{
    public function __construct($resource, protected ?int $currentTokenId = null)
    {
        parent::__construct($resource);
    }

    public static function collectionWithCurrent($resource, ?int $currentTokenId)
    {
        return $resource->map(fn ($item) => new static($item, $currentTokenId));
    }

    public function toArray(Request $request): array
    {
        $locale = $request->header('lang', 'en') === 'ar' ? 'ar' : 'en';
        $isCurrent = $this->currentTokenId !== null && (int) $this->id === (int) $this->currentTokenId;
        $lastActiveAt = $this->last_used_at ?? $this->created_at;

        return [
            'id' => $this->id,
            'device_name' => $this->device_name ?: ($locale === 'ar' ? 'جهاز غير معروف' : 'Unknown Device'),
            'location_label' => $this->locationLabel(),
            'is_current' => $isCurrent,
            'status_badge' => $isCurrent ? 'current' : 'other',
            'status_label' => $isCurrent
                ? ($locale === 'ar' ? 'الحالي' : 'CURRENT')
                : null,
            'last_active_at' => $lastActiveAt?->toIso8601String(),
            'last_active_label' => $this->lastActiveLabel($locale, $isCurrent, $lastActiveAt),
        ];
    }

    protected function locationLabel(): ?string
    {
        $city = trim((string) ($this->city_name ?? ''));
        $country = strtoupper(trim((string) ($this->country_code ?? '')));

        if ($city !== '' && $country !== '') {
            return strtoupper($city.', '.$country);
        }

        if ($city !== '') {
            return strtoupper($city);
        }

        return $country !== '' ? $country : null;
    }

    protected function lastActiveLabel(string $locale, bool $isCurrent, $lastActiveAt): ?string
    {
        if ($isCurrent) {
            return $locale === 'ar' ? 'نشط الآن' : 'ACTIVE NOW';
        }

        if (! $lastActiveAt) {
            return null;
        }

        $ago = $lastActiveAt->locale($locale)->diffForHumans();

        return $locale === 'ar'
            ? 'آخر نشاط '.$ago
            : 'LAST ACTIVE '.strtoupper($ago);
    }
}
