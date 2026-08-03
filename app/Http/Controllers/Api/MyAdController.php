<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyAdResource;
use App\Models\Ad;
use App\Models\AdAttributeValue;
use App\Models\AdImage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use App\Services\ListingPaymentService;
use App\Traits\HandlesListingPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MyAdController extends Controller
{
    use HandlesListingPayments;

    public function __construct(protected ChatService $chatService) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'sold'])],
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $user = Auth::user();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $lang = $request->header('lang', 'en');

        $this->expirePublishedAdsForUser($user->id);

        $query = Ad::query()
            ->where('user_id', $user->id)
            ->with(['soldToUser:id,first_name,last_name,name'])
            ->orderByDesc('updated_at');

        match ($validated['status']) {
            'active' => $query->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                }),
            'inactive' => $query->whereIn('status', ['expired', 'paused', 'pending', 'rejected', 'draft']),
            'sold' => $query->where('status', 'sold'),
        };

        $ads = $query->paginate($perPage);
        $response = MyAdResource::collection($ads)->response()->getData(true);
        $response['current_tab'] = $validated['status'];

        return sendResponse($response);
    }

    public function purchases(Request $request)
    {
        $perPage = (int) ($request->input('per_page', 20));

        $ads = Ad::query()
            ->where('sold_to_user_id', Auth::id())
            ->where('status', 'sold')
            ->with(['user:id,first_name,last_name,name,image'])
            ->orderByDesc('sold_at')
            ->paginate($perPage);

        return sendResponse(MyAdResource::collection($ads)->response()->getData(true));
    }

    public function buyers(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $ad = $this->findOwnedAd($id);

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        if ($ad->status !== 'published') {
            return sendError(
                __('api.ad.buyers_active_only'),
                [],
                422
            );
        }

        return sendResponse([
            'ad_id' => $ad->id,
            'buyers' => Conversation::query()
                ->where('ad_id', $ad->id)
                ->with([
                    'buyer:id,first_name,last_name,name,image',
                    'latestMessage',
                ])
                ->orderByDesc('last_message_at')
                ->get()
                ->map(function (Conversation $conversation) {
                    $buyer = $conversation->buyer;

                    return [
                        'user_id' => $buyer?->id,
                        'name' => $buyer?->name,
                        'first_name' => $buyer?->first_name,
                        'last_name' => $buyer?->last_name,
                        'image' => $buyer?->image ? getimg($buyer->image) : null,
                        'conversation_id' => $conversation->id,
                        'last_message_preview' => $conversation->last_message_preview,
                        'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    ];
                })
                ->values(),
        ]);
    }

    public function markAsSold(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $ad = $this->findOwnedAd($id);

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        if ($ad->status !== 'published') {
            return sendError(
                __('api.ad.only_active_can_be_sold'),
                [],
                422
            );
        }

        $validated = $request->validate([
            'buyer_id' => 'nullable|integer|exists:users,id',
            'is_sold_outside' => 'nullable|boolean',
        ]);

        $isSoldOutside = (bool) ($validated['is_sold_outside'] ?? false);
        $buyerId = $validated['buyer_id'] ?? null;

        if (! $isSoldOutside && ! $buyerId) {
            return sendError(
                __('api.ad.select_buyer_or_outside'),
                [],
                422
            );
        }

        if ($buyerId && (int) $buyerId === (int) Auth::id()) {
            return sendError(
                __('api.ad.cannot_select_self'),
                [],
                422
            );
        }

        $ad->update([
            'status' => 'sold',
            'sold_at' => now(),
            'sold_to_user_id' => $isSoldOutside ? null : $buyerId,
            'is_sold_outside' => $isSoldOutside,
        ]);

        $this->chatService->archiveConversationsForAd($ad->fresh(), 'sold');

        $ad->load('soldToUser:id,first_name,last_name,name');

        return sendResponse(
            new MyAdResource($ad),
            __('api.ad.marked_sold')
        );
    }

    public function pause(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $ad = $this->findOwnedAd($id);

        if (! $ad || $ad->status !== 'published') {
            return sendError(
                __('api.ad.only_active_can_pause'),
                [],
                422
            );
        }

        $ad->update([
            'status' => 'paused',
            'paused_at' => now(),
            'inactive_reason' => 'manual_pause',
        ]);

        return sendResponse(
            new MyAdResource($ad->fresh()),
            __('api.ad.paused')
        );
    }

    public function activate(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $ad = $this->findOwnedAd($id);

        if (! $ad || ! in_array($ad->status, ['paused', 'expired'], true)) {
            return sendError(
                __('api.ad.only_paused_expired_activate'),
                [],
                422
            );
        }

        // A pause does not consume the paid posting period: inside it,
        // reactivating simply resumes the ad with no second fee.
        if ($this->hasUnexpiredPaidPeriod($ad)) {
            $ad->update([
                'status' => 'published',
                'paused_at' => null,
                'inactive_reason' => null,
            ]);

            $resumed = $ad->fresh();

            return sendResponse(
                [
                    'ad' => new MyAdResource($resumed),
                    'publication' => app(ListingPaymentService::class)->publicationState($resumed),
                ],
                __('api.ad.activated')
            );
        }

        // Otherwise the period is over, so this starts a new paid one. The old
        // intent must be cleared first — startPublication treats an existing one
        // as "payment in progress" and would let the ad go live without paying.
        $ad->update([
            'paused_at' => null,
            'inactive_reason' => null,
            'listing_fee' => null,
            'payment_status' => 'not_required',
            'stripe_payment_intent_id' => null,
        ]);

        $publication = $this->startPublication($ad->fresh(), $request->input('coupon_code'));

        if (isset($publication['coupon_error'])) {
            return sendError(
                __('api.ad.coupon_failed'),
                ['coupon_code' => $publication['coupon_error']],
                422
            );
        }

        return sendResponse(
            [
                'ad' => new MyAdResource($ad->fresh()),
                'publication' => $publication,
            ],
            __('api.ad.activated')
        );
    }

    /**
     * True when the posting period was settled (paid, free quota, coupon or
     * waiver) and has not run out — the case where reactivating must be free.
     */
    protected function hasUnexpiredPaidPeriod(Ad $ad): bool
    {
        $settled = in_array($ad->payment_status, ['paid', 'free', 'waived', 'coupon'], true);

        return $settled
            && $ad->expires_at !== null
            && $ad->expires_at->isFuture();
    }

    /**
     * "Sell again" on a sold ad: copies it into a brand new listing rather than
     * reviving the original.
     *
     * The original keeps its sale record, and its conversations stay archived —
     * reviving it in place would leave archived threads pointing at a live ad,
     * which ChatService refuses to unarchive once an ad has been sold.
     */
    public function sellAgain(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $ad = $this->findOwnedAd($id);

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        if ($ad->status !== 'sold') {
            return sendError(
                __('api.ad.only_sold_relist'),
                [],
                422
            );
        }

        $copy = DB::transaction(function () use ($ad) {
            $publicId = strtoupper(Str::random(10));

            $copy = $ad->replicate([
                // Identity, lifecycle, sale record and payment state all start over.
                'public_id', 'slug', 'status', 'published_at', 'expires_at', 'paused_at',
                'sold_at', 'sold_to_user_id', 'is_sold_outside', 'inactive_reason',
                'listing_fee', 'payment_status', 'stripe_payment_intent_id',
                'is_free_listing', 'is_verified',
            ]);

            $copy->public_id = $publicId;
            $copy->slug = Str::slug($ad->title.'-'.$publicId);
            $copy->status = 'pending';
            $copy->is_sold_outside = false;
            $copy->is_free_listing = false;
            $copy->is_verified = false;
            $copy->save();

            // Image rows point at the same stored files. Deleting an ad is a soft
            // delete and a sold ad cannot be deleted at all, so nothing removes
            // the originals from disk.
            foreach ($ad->images()->orderBy('sort_order')->get() as $image) {
                AdImage::create([
                    'ad_id' => $copy->id,
                    'path' => $image->path,
                    'sort_order' => $image->sort_order,
                ]);
            }

            foreach ($ad->attributeValues as $value) {
                AdAttributeValue::create([
                    'ad_id' => $copy->id,
                    'category_attribute_definition_id' => $value->category_attribute_definition_id,
                    'value' => $value->value,
                ]);
            }

            return $copy;
        });

        // A relist is a fresh 30-day listing, so it goes through the same fee
        // path as any new ad: free quota, then coupon, then Stripe.
        $publication = $this->startPublication($copy, $request->input('coupon_code'));

        if (isset($publication['coupon_error'])) {
            return sendError(
                __('api.ad.coupon_failed'),
                ['coupon_code' => $publication['coupon_error']],
                422
            );
        }

        return sendResponse(
            [
                'ad' => new MyAdResource($copy->fresh()),
                'relisted_from_ad_id' => (int) $ad->id,
                'publication' => $publication,
            ],
            __('api.ad.relisted')
        );
    }

    public function destroy(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $ad = $this->findOwnedAd($id);

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        // A sold ad keeps its record (purchase history); everything else the
        // owner created can be removed. Archive any open conversations first.
        if ($ad->status === 'sold') {
            return sendError(
                __('api.ad.sold_cannot_delete'),
                [],
                422
            );
        }

        if ($ad->status === 'published') {
            $this->chatService->archiveConversationsForAd($ad, 'listing_removed');
        }

        $ad->delete();

        return sendResponse(
            ['ad_id' => (int) $id],
            __('api.ad.deleted')
        );
    }

    protected function findOwnedAd(string $id): ?Ad
    {
        return Ad::query()
            ->where('user_id', Auth::id())
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('public_id', $id);
            })
            ->first();
    }

    protected function expirePublishedAdsForUser(int $userId): void
    {
        Ad::query()
            ->where('user_id', $userId)
            ->where('status', 'published')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'inactive_reason' => 'auto_expired',
            ]);
    }
}
