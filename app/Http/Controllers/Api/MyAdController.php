<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdRequest;
use App\Http\Resources\AdDetailResource;
use App\Http\Resources\MyAdResource;
use App\Models\Ad;
use App\Models\AdAttributeValue;
use App\Models\AdImage;
use App\Models\CategoryAttributeDefinition;
use App\Models\City;
use App\Models\Conversation;
use App\Models\Payment;
use App\Services\ChatService;
use App\Services\ListingPaymentService;
use App\Support\AdAvailabilityRules;
use App\Traits\HandlesListingPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
            ->withCount('views')
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

        $publication = $this->startExtension($ad, $request->input('coupon_code'), $request->has('coupon_code'));

        if (isset($publication['coupon_error'])) {
            return sendError(
                __('api.ad.coupon_failed'),
                [
                    'coupon_code' => $publication['coupon_error'],
                    'coupon_error' => $publication['coupon_error'],
                    'publication' => $publication,
                ],
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
     * Sold → Active, on the ad's own id (unlike sell-again, which copies it
     * into a brand-new ad). New v2-only action, deliberately not folded into
     * activate() since that method is shared with the frozen v1 routes.
     * Same money logic as activate(): free inside the paid period, the extend
     * fee (not a full new-listing fee) outside it.
     */
    public function reactivate(Request $request, string $id)
    {
        $ad = $this->findOwnedAd($id);

        if (! $ad || $ad->status !== 'sold') {
            return sendError(__('api.ad.only_sold_can_reactivate'), [], 422);
        }

        $ad->update([
            'sold_at' => null,
            'sold_to_user_id' => null,
            'is_sold_outside' => false,
        ]);

        if ($this->hasUnexpiredPaidPeriod($ad)) {
            $ad->update(['status' => 'published']);
            $resumed = $ad->fresh();

            return sendResponse(
                [
                    'ad' => new MyAdResource($resumed),
                    'publication' => app(ListingPaymentService::class)->publicationState($resumed),
                ],
                __('api.ad.reactivated')
            );
        }

        $publication = $this->startExtension($ad->fresh(), $request->input('coupon_code'), $request->has('coupon_code'));

        if (isset($publication['coupon_error'])) {
            return sendError(
                __('api.ad.coupon_failed'),
                [
                    'coupon_code' => $publication['coupon_error'],
                    'coupon_error' => $publication['coupon_error'],
                    'publication' => $publication,
                ],
                422
            );
        }

        return sendResponse(
            [
                'ad' => new MyAdResource($ad->fresh()),
                'publication' => $publication,
            ],
            $publication['published'] ? __('api.ad.reactivated') : __('api.ad.payment_required')
        );
    }

    public function requestRefund(Request $request, string $id)
    {
        $ad = $this->findOwnedAd($id);

        if (! $ad || $ad->payment_status !== 'paid' || ! $ad->stripe_payment_intent_id) {
            return sendError(__('api.ad.refund_not_available'), [], 422);
        }

        if ($ad->refund_status !== null) {
            return sendError(__('api.ad.refund_already_decided'), [], 422);
        }

        if (! $ad->published_at || $ad->published_at->lt(now()->subDays(14))) {
            return sendError(__('api.ad.refund_window_expired'), [], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ], [
            'reason.required' => __('api.ad.refund_reason_required'),
        ]);

        $requestedAt = now();

        $ad->update([
            'refund_status' => 'requested',
            'refund_requested_at' => $requestedAt,
            'refund_request_reason' => $validated['reason'],
        ]);

        Payment::where('stripe_payment_intent_id', $ad->stripe_payment_intent_id)->update([
            'refund_status' => 'requested',
            'refund_requested_at' => $requestedAt,
            'refund_request_reason' => $validated['reason'],
        ]);

        return sendResponse(new MyAdResource($ad->fresh()), __('api.ad.refund_requested'));
    }

    public function refundRequests(Request $request)
    {
        $perPage = (int) ($request->input('per_page', 20));

        $ads = Ad::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('refund_status')
            ->orderByDesc('refund_requested_at')
            ->paginate($perPage);

        $ads->through(fn (Ad $ad) => [
            'id' => $ad->id,
            'public_id' => $ad->public_id,
            'title' => $ad->title,
            'listing_fee' => $ad->listing_fee,
            'currency' => $ad->currency,
            'refund_status' => $ad->refund_status,
            'refund_requested_at' => optional($ad->refund_requested_at)->toIso8601String(),
            'refund_request_reason' => $ad->refund_request_reason,
            'refund_reason' => $ad->refund_reason,
            'refunded_at' => optional($ad->refunded_at)->toIso8601String(),
            'refund_declined_at' => optional($ad->refund_declined_at)->toIso8601String(),
        ]);

        return sendResponse($ads);
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
        $user = Auth::user();

        if ($user->needsReverification()) {
            return sendError(
                $lang
                    ? 'يجب إعادة تأكيد حالتك كطالب قبل إنشاء إعلان جديد'
                    : 'Please re-verify your student status before creating a new listing',
                ['needs_reverify' => true],
                403
            );
        }

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

        $availabilityAttributes = $ad->attributeValues()
            ->with('definition:id,slug')
            ->get()
            ->filter(fn (AdAttributeValue $value) => in_array(
                $value->definition?->slug,
                ['availability_from', 'availability_until'],
                true
            ))
            ->mapWithKeys(fn (AdAttributeValue $value) => [$value->definition->slug => $value->value])
            ->all();

        Validator::make(
            ['attributes' => $availabilityAttributes],
            AdAvailabilityRules::rules(),
            AdAvailabilityRules::messages()
        )->validate();

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
        $publication = $this->startPublication($copy, $request->input('coupon_code'), null, 'listing', $request->has('coupon_code'));

        if (isset($publication['coupon_error'])) {
            return sendError(
                __('api.ad.coupon_failed'),
                [
                    'coupon_code' => $publication['coupon_error'],
                    'coupon_error' => $publication['coupon_error'],
                    'publication' => $publication,
                ],
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

    /**
     * Updates listing content only. Publication, coupon, payment and image state
     * are deliberately untouched: an edit is not a second publish operation.
     */
    public function update(UpdateAdRequest $request, string $id)
    {
        $ad = Ad::query()
            ->where(fn ($query) => $query->where('id', $id)->orWhere('public_id', $id))
            ->first();

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        if ((int) $ad->user_id !== (int) Auth::id()) {
            return sendError(__('api.ad.not_owner'), ['error_code' => 'not_owner'], 403);
        }

        if ($ad->status === 'sold') {
            return sendError(__('api.ad.sold_cannot_edit'), ['error_code' => 'sold_listing'], 409);
        }

        $data = $request->validated();
        $attributes = (array) ($data['attributes'] ?? []);
        $definitionCategoryIds = array_values(array_filter([
            (int) $data['main_category_id'],
            (int) ($data['sub_category_id'] ?? 0),
        ]));

        DB::transaction(function () use ($ad, $data, $attributes, $definitionCategoryIds): void {
            $city = City::query()->findOrFail($data['city_id']);

            $ad->update([
                'main_category_id' => $data['main_category_id'],
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'title' => $data['title'],
                'subtitle' => $data['subtitle'] ?? null,
                'license_plate' => $data['license_plate'] ?? null,
                'price' => $data['price'],
                'description' => $data['description'],
                'currency' => strtoupper($data['currency'] ?? 'GBP'),
                'country_id' => $city->country_id,
                'city_id' => $city->id,
                'postcode' => $data['postcode'] ?? null,
                'region' => $data['region'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_negotiable' => (bool) ($data['is_negotiable'] ?? false),
                'slug' => Str::slug($data['title'].'-'.$ad->public_id),
            ]);

            $definitions = CategoryAttributeDefinition::query()
                ->whereIn('category_id', $definitionCategoryIds)
                ->where('is_active', true)
                ->get()
                ->sortBy(fn (CategoryAttributeDefinition $definition) => $definition->category_id === (int) ($data['sub_category_id'] ?? 0) ? 1 : 0
                )
                ->keyBy('slug');

            $ad->attributeValues()->delete();
            foreach ($attributes as $slug => $rawValue) {
                $definition = $definitions->get($slug);
                if (! $definition) {
                    continue;
                }

                $values = array_values(array_unique(array_filter(
                    is_array($rawValue) ? $rawValue : [$rawValue],
                    static fn ($value) => is_scalar($value) && (string) $value !== ''
                )));

                foreach ($values as $value) {
                    AdAttributeValue::create([
                        'ad_id' => $ad->id,
                        'category_attribute_definition_id' => $definition->id,
                        'value' => (string) $value,
                    ]);
                }
            }
        });

        $ad->refresh()->load([
            'images',
            'city.translations',
            'mainCategory.translations',
            'subCategory.translations',
            'attributeValues.definition.translations',
        ]);

        return sendResponse(['ad' => new AdDetailResource($ad)], __('api.ad.updated'));
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
