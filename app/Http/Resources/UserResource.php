<?php

namespace App\Http\Resources;

use App\Models\TermsVersion;
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

        // "Show first name only" applies to everyone except the owner, who always
        // sees their own full name inside Edit Profile.
        $showLastName = $isOwnProfile || (bool) $this->show_last_name;
        $displayName = $showLastName
            ? $this->name
            : $this->first_name;

        $data = [
            'id' => $this->id,
            'name' => $displayName,
            'first_name' => $this->first_name,
            'last_name' => $showLastName ? $this->last_name : null,
            'image' => $this->image ? getimg($this->image) : null,
            'member_since' => $this->created_at
                ? $this->created_at->locale($locale)->translatedFormat('F Y')
                : null,
            // Raw join timestamp so the app can format it in its own locale;
            // `member_since` above stays for older builds.
            'created_at' => $this->created_at?->toIso8601String(),
            'ads_count' => (int) ($this->published_ads_count ?? 0),
            'active_ads_count' => (int) ($this->published_ads_count ?? 0),
            // Restored: these three are part of the published mobile contract
            // (profile header, seller badge, rating row) and were commented out
            // during the parallel rewrite rather than intentionally dropped.
            'average_rating' => round((float) ($this->average_rating ?? 0), 1),
            'total_reviews' => (int) ($this->total_reviews_count ?? 0),
            'is_trusted_seller' => (bool) $this->is_trusted_seller,
            'device_type' => $this->device_type,
            'city' => $this->city ? new CityResource($this->city) : null,
            'status' => ($this->status === '1' || $this->status === 1)
                ? 'active'
                : (($this->status === '2' || $this->status === 2) ? 'pending_verification' : 'inactive'),
        ];

        if ($isOwnProfile) {
            $currentTerms = TermsVersion::query()->where('is_current', true)->latest('effective_at')->first();
            $currentTermsAcceptance = $currentTerms
                ? $this->termsAcceptances()->where('terms_version_id', $currentTerms->id)->first()
                : null;
            $postingRestriction = $this->activeFeatureRestriction('posting');
            $messagingRestriction = $this->activeFeatureRestriction('messaging');
            // Contact details are the account owner's alone. They used to be in
            // the shared block, which handed any authenticated caller another
            // user's personal email and phone number via GET /show-profile/{id}.
            $data['phone'] = $this->phone;
            $data['email'] = $this->email;
            $data['student_email'] = $this->student_email;
            // Student re-verification lifecycle. The mobile app reads these to
            // decide whether to show the "reconfirm your student status" prompt.
            $data['student_verified_at'] = $this->student_verified_at?->toIso8601String();
            $data['student_reverify_due_at'] = $this->student_reverify_due_at?->toIso8601String();
            $data['needs_reverification'] = $this->needsReverification();
            // Private preferences: only ever returned to the account owner.
            $data['settings'] = [
                'privacy' => [
                    'show_last_name' => (bool) $this->show_last_name,
                    'show_approximate_location' => (bool) $this->show_approximate_location,
                    'trusted_users_only' => (bool) $this->trusted_users_only,
                ],
                'notifications' => [
                    'notify_chat' => (bool) $this->notify_chat,
                    'notify_ads' => (bool) $this->notify_ads,
                    // System alerts are always on and cannot be switched off.
                    'notify_system' => true,
                ],
            ];
            $data['terms'] = [
                'current_version' => $currentTerms?->version,
                'accepted' => $currentTermsAcceptance !== null,
                'accepted_at' => $currentTermsAcceptance?->accepted_at?->toIso8601String(),
            ];
            $data['capabilities'] = [
                'can_post' => $postingRestriction === null,
                'can_message' => $messagingRestriction === null,
            ];
            $data['feature_restrictions'] = collect([$postingRestriction, $messagingRestriction])
                ->filter()
                ->map(fn ($restriction) => [
                    'id' => $restriction->id,
                    'feature' => $restriction->feature,
                    'reason' => $restriction->reason,
                    'ends_at' => $restriction->ends_at?->toIso8601String(),
                ])->values()->all();
        } else {
            $data['student_email_masked'] = $mask($this->student_email);
        }

        return $data;
    }
}
