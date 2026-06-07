<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lang = $request->header('lang', 'en');
        $locale = $lang === 'ar' ? 'ar' : 'en';
        $isOwnProfile = $request->user()?->id === $this->id;
        $mask = function (?string $e) {
            if (! $e || ! str_contains($e, '@')) {
                return null;
            }
            [$local, $domain] = explode('@', $e, 2);

            return (strlen($local) <= 2 ? substr($local, 0, 1) : substr($local, 0, 2)).'***@'.$domain;
        };

        $name = $lang === 'en' ? 'name_en' : 'name_ar';

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'image' => $this->image ? getimg($this->image) : null,
            'phone' => $this->phone,
            'email' => $this->email,
            'member_since' => $this->created_at
                ? $this->created_at->locale($locale)->translatedFormat('F Y')
                : null,
            'ads_count' => (int) ($this->published_ads_count ?? 0),
            'average_rating' => round((float) ($this->average_rating ?? 0), 1),
            'total_reviews' => (int) ($this->total_reviews_count ?? 0),
            'is_trusted_seller' => (bool) $this->is_trusted_seller,
            'device_type' => $this->device_type,
            'city_id' => (int) $this->city_id,
            'city_name' => $this->city ? $this->city->$name : null,
            'status' => ($this->status === '1' || $this->status === 1)
                ? 'active'
                : (($this->status === '2' || $this->status === 2) ? 'pending_verification' : 'inactive'),
        ];

        if ($isOwnProfile) {
            $data['student_email'] = $this->student_email;
        } else {
            $data['student_email_masked'] = $mask($this->student_email);
        }

        return $data;
    }
}
