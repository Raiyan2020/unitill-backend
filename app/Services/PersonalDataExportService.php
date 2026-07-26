<?php

namespace App\Services;

use App\Models\AdReport;
use App\Models\ChatReport;
use App\Models\CouponRedemption;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;

/**
 * Builds the GDPR Subject Access Request payload.
 *
 * The six disclosure sections required by the spec (purposes of processing,
 * categories of data, recipients, retention periods, rights, and the copy of
 * the data itself) are assembled here so the controller only has to deliver it.
 */
class PersonalDataExportService
{
    public function build(User $user): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'subject' => [
                'user_id' => $user->id,
                'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'personal_email' => $user->email,
                'university_email' => $user->student_email,
                'phone' => $user->phone,
                'city_id' => $user->city_id,
                'account_status' => $user->status,
                'account_created_at' => optional($user->created_at)->toIso8601String(),
                'account_deleted_at' => optional($user->deleted_at)->toIso8601String(),
            ],
            'personal_data' => $this->personalData($user),
            'disclosures' => $this->disclosures(),
        ];
    }

    /**
     * Everything held about the user, not just their ads.
     */
    protected function personalData(User $user): array
    {
        return [
            'ads' => $user->ads()->withTrashed()->latest()->get()
                ->map(fn ($ad) => [
                    'id' => $ad->id,
                    'public_id' => $ad->public_id,
                    'title' => $ad->title,
                    'status' => $ad->status,
                    'price' => $ad->price,
                    'currency' => $ad->currency,
                    'postcode' => $ad->postcode,
                    'created_at' => optional($ad->created_at)->toIso8601String(),
                    'sold_at' => optional($ad->sold_at)->toIso8601String(),
                ])->values(),

            'favourites' => $user->favoriteAds()->get(['ads.id', 'ads.title'])
                ->map(fn ($ad) => [
                    'ad_id' => $ad->id,
                    'title' => $ad->title,
                    'favourited_at' => optional($ad->pivot->created_at)->toIso8601String(),
                ])->values(),

            'conversations' => $this->conversations($user),

            'messages_sent' => Message::query()
                ->where('sender_id', $user->id)
                ->latest()
                ->get(['id', 'conversation_id', 'body', 'created_at'])
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'conversation_id' => $m->conversation_id,
                    'body' => $m->body,
                    'sent_at' => optional($m->created_at)->toIso8601String(),
                ])->values(),

            'ad_reports_submitted' => AdReport::query()
                ->where('user_id', $user->id)
                ->get(['id', 'ad_id', 'reason', 'comment', 'status', 'created_at'])
                ->values(),

            'chat_reports_submitted' => ChatReport::query()
                ->where('reporter_id', $user->id)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'conversation_id' => $r->conversation_id,
                    'reason' => $r->reason,
                    'status' => $r->status,
                    'created_at' => optional($r->created_at)->toIso8601String(),
                ])->values(),

            'ratings_given' => $user->ratingsGiven()->get(['id', 'rated_user_id', 'score', 'comment', 'created_at'])->values(),
            'ratings_received' => $user->ratingsReceived()->get(['id', 'rater_id', 'score', 'comment', 'created_at'])->values(),

            'orders_as_buyer' => Order::query()->where('buyer_id', $user->id)->get()->values(),
            'orders_as_seller' => Order::query()->where('seller_id', $user->id)->get()->values(),

            'coupon_redemptions' => CouponRedemption::query()
                ->where('user_id', $user->id)
                ->get(['id', 'coupon_id', 'ad_id', 'original_amount', 'discount_amount', 'final_amount', 'created_at'])
                ->values(),

            'devices' => $user->userDevices()->get()->values(),

            'login_history' => $user->loginLogs()->latest()->limit(500)->get()->values(),

            'notifications' => $user->notifications()->latest()->limit(500)->get()->values(),

            'contact_us_messages' => $user->contactUsMessages()->get()->values(),
        ];
    }

    protected function conversations(User $user): array
    {
        $rows = [];

        foreach (['buyerConversations' => 'buyer', 'sellerConversations' => 'seller'] as $relation => $role) {
            foreach ($user->{$relation}()->get() as $conversation) {
                $rows[] = [
                    'id' => $conversation->id,
                    'role' => $role,
                    'ad_id' => $conversation->ad_id,
                    'status' => $conversation->status,
                    'created_at' => optional($conversation->created_at)->toIso8601String(),
                ];
            }
        }

        return $rows;
    }

    /**
     * The six GDPR disclosure sections.
     *
     * Intentionally left as placeholders: these carry legal weight and must be
     * Copy lives in resources/lang/{locale}/api.php under `disclosures`, so the
     * user reads it in their own language.
     *
     * The wording is INTERIM: the English was supplied by the operator and is not
     * yet signed off with management, and the other four locales are unreviewed
     * translations. It carries legal weight, so pendingReview() keeps flagging it
     * in the SAR log until DISCLOSURES_APPROVED is set to true.
     */
    public const SECTIONS = [
        'purposes_of_processing',
        'categories_of_data',
        'recipients',
        'retention_periods',
        'your_rights',
    ];

    public function disclosures(): array
    {
        $out = [];

        foreach (self::SECTIONS as $section) {
            $key = "api.disclosures.$section";
            $text = __($key);
            // __() echoes the key back when a locale has no entry; fall back to
            // English rather than shipping "api.disclosures.recipients" to a user.
            $out[$section] = $text === $key ? __($key, [], 'en') : $text;
        }

        return $out;
    }

    /**
     * Whether the disclosure copy above is still the interim wording.
     *
     * Flip to true once the final text has been signed off, so the reminder in
     * the SAR log stops firing.
     */
    public const DISCLOSURES_APPROVED = false;

    /**
     * Disclosure sections needing attention: any that are empty, plus every
     * section while the copy remains unapproved.
     */
    public function pendingReview(): array
    {
        $empty = array_keys(array_filter(
            $this->disclosures(),
            fn ($text) => trim((string) $text) === ''
        ));

        if ($empty !== []) {
            return $empty;
        }

        return self::DISCLOSURES_APPROVED
            ? []
            : ['interim_wording_pending_sign_off'];
    }
}
