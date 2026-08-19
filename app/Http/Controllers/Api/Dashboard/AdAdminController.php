<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\ContentModerationAction;
use App\Models\Payment;
use App\Services\PushNotificationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdAdminController extends Controller
{
    public function __construct(protected PushNotificationService $pushNotificationService) {}

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $userId = (int) $request->input('user_id', 0);

        $query = Ad::query()
            ->with('user:id,name,first_name,last_name,email')
            ->orderByDesc('id');

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('public_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $rows = $query->paginate($perPage);

        $rows->getCollection()->transform(function (Ad $ad) {
            $cover = $ad->cover_image ? ltrim((string) $ad->cover_image, '/') : null;
            $userName = trim(
                implode(' ', array_filter([
                    $ad->user?->first_name,
                    $ad->user?->last_name,
                ]))
            );

            if ($userName === '') {
                $userName = (string) ($ad->user?->name ?? $ad->user?->email ?? '-');
            }

            return [
                'id' => $ad->id,
                'public_id' => $ad->public_id,
                'title' => $ad->title,
                'subtitle' => $ad->subtitle,
                'description' => $ad->description,
                'status' => $ad->status,
                'payment_status' => $ad->payment_status,
                'price' => $ad->price,
                'currency' => $ad->currency,
                'is_negotiable' => (bool) $ad->is_negotiable,
                'is_verified' => (bool) $ad->is_verified,
                'published_at' => optional($ad->published_at)->toDateTimeString(),
                'cover_image' => $ad->cover_image,
                'cover_image_url' => $cover ? url('/storage/'.$cover) : null,
                'user_id' => $ad->user_id,
                'user_name' => $userName,
                'created_at' => optional($ad->created_at)->toDateTimeString(),
            ];
        });

        return sendResponse($rows, 'Ads fetched');
    }

    public function show(int $id)
    {
        $ad = Ad::with([
            'user:id,name,first_name,last_name,email,phone',
            'country:id,country_code',
            'city:id,country_id,code',
            'mainCategory:id,parent_id',
            'mainCategory.translations',
            'subCategory:id,parent_id',
            'subCategory.translations',
            'images:id,ad_id,path,sort_order',
            'attributeValues.definition.translations',
        ])->find($id);

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        $cover = $ad->cover_image ? ltrim((string) $ad->cover_image, '/') : null;
        $userName = trim(
            implode(' ', array_filter([
                $ad->user?->first_name,
                $ad->user?->last_name,
            ]))
        );
        if ($userName === '') {
            $userName = (string) ($ad->user?->name ?? $ad->user?->email ?? '-');
        }

        return sendResponse([
            'id' => $ad->id,
            'public_id' => $ad->public_id,
            'title' => $ad->title,
            'subtitle' => $ad->subtitle,
            'description' => $ad->description,
            'status' => $ad->status,
            'price' => $ad->price,
            'currency' => $ad->currency,
            'is_negotiable' => (bool) $ad->is_negotiable,
            'is_verified' => (bool) $ad->is_verified,
            'slug' => $ad->slug,
            'published_at' => optional($ad->published_at)->toDateTimeString(),
            'listing_fee' => $ad->listing_fee,
            'payment_status' => $ad->payment_status,
            'stripe_payment_intent_id' => $ad->stripe_payment_intent_id,
            'refund_status' => $ad->refund_status,
            'refund_requested_at' => optional($ad->refund_requested_at)->toDateTimeString(),
            'refund_request_reason' => $ad->refund_request_reason,
            'refund_reference' => $ad->refund_reference,
            'refund_reason' => $ad->refund_reason,
            'refunded_at' => optional($ad->refunded_at)->toDateTimeString(),
            'refund_declined_at' => optional($ad->refund_declined_at)->toDateTimeString(),
            'cover_image' => $ad->cover_image,
            'cover_image_url' => $cover ? url('/storage/'.$cover) : null,
            'user' => [
                'id' => $ad->user?->id,
                'name' => $userName,
                'email' => $ad->user?->email,
                'phone' => $ad->user?->phone,
            ],
            'country' => $ad->country ? [
                'id' => $ad->country->id,
                'country_code' => $ad->country->country_code,
            ] : null,
            'city' => $ad->city ? [
                'id' => $ad->city->id,
                'code' => $ad->city->code,
            ] : null,
            'main_category_id' => $ad->main_category_id,
            'sub_category_id' => $ad->sub_category_id,
            'main_category_name' => $this->categoryName($ad->mainCategory),
            'sub_category_name' => $this->categoryName($ad->subCategory),
            // Seller-submitted specs, so a moderator can see them while reviewing
            'attributes' => $ad->attributeValues
                ->filter(fn ($value) => $value->definition !== null)
                ->sortBy(fn ($value) => $value->definition->sort_order)
                ->map(fn ($value) => [
                    'slug' => $value->definition->slug,
                    'label' => $value->definition->labelForLanguageCode('en')
                        ?: $value->definition->slug,
                    'input_type' => $value->definition->input_type,
                    'value' => $value->value,
                ])
                ->values(),
            'images' => $ad->images->map(function ($img) {
                $path = ltrim((string) $img->path, '/');

                return [
                    'id' => $img->id,
                    'path' => $img->path,
                    'url' => url('/storage/'.$path),
                    'sort_order' => $img->sort_order,
                ];
            })->values(),
            'created_at' => optional($ad->created_at)->toDateTimeString(),
            'updated_at' => optional($ad->updated_at)->toDateTimeString(),
        ], 'Ad details');
    }

    /**
     * English category name for the dashboard, falling back to any translation.
     */
    private function categoryName(?Category $category): ?string
    {
        if (! $category) {
            return null;
        }

        $translations = $category->translations;

        return $translations->firstWhere('language_id', 1)?->name
            ?? $translations->first()?->name;
    }

    public function update(Request $request, int $id)
    {
        $ad = Ad::with('user')->find($id);

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['draft', 'pending', 'published', 'rejected', 'sold', 'expired'])],
            'reason' => ['required', 'string', 'max:3000'],
            'source_type' => ['nullable', Rule::in(['ad_report', 'chat_report', 'manual'])],
            'source_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $oldStatus = $ad->status;
        $newStatus = $validator->validated()['status'];

        if ($newStatus === 'published'
            && $oldStatus !== 'published'
            && ! in_array($ad->payment_status, ['paid', 'free', 'waived', 'coupon'], true)) {
            return sendError(
                __('api.ad.payment_unsettled'),
                ['payment_status' => $ad->payment_status],
                422
            );
        }

        $ad->status = $newStatus;

        // Publishing from the dashboard has to stamp the same fields the paid
        // publish flow does. Without this the ad goes live with a null
        // published_at and sinks to the bottom of every "newest first" listing.
        if ($newStatus === 'published' && $ad->published_at === null) {
            $publishedAt = now();
            $ad->published_at = $publishedAt;

            if ($ad->expires_at === null) {
                $ad->expires_at = $publishedAt->copy()->addDays((int) setting('post_duration', '30'));
            }
        }

        $ad->save();

        if ($oldStatus !== $newStatus) {
            ContentModerationAction::create([
                'admin_id' => $request->user()?->id,
                'user_id' => $ad->user_id,
                'content_type' => 'ad',
                'content_id' => $ad->id,
                'action' => 'status_changed',
                'reason' => $validator->validated()['reason'],
                'source_type' => $validator->validated()['source_type'] ?? 'manual',
                'source_id' => $validator->validated()['source_id'] ?? null,
                'metadata' => ['old_status' => $oldStatus, 'new_status' => $newStatus],
                'resolved_at' => now(),
            ]);
        }

        if ($oldStatus !== $newStatus && $ad->user) {
            // NOTE: resolves in the acting admin's language, not the ad owner's.
            // Users have no stored locale, so a student can be notified in
            // whatever language the dashboard operator happens to be using.
            $statusKey = "api.ad_status.{$newStatus}";
            $statusName = __($statusKey);
            if ($statusName === $statusKey) {
                $statusName = $newStatus;
            }

            $this->pushNotificationService->sendToUser(
                $ad->user,
                __('api.ad_status.title'),
                __('api.ad_status.body', ['title' => $ad->title, 'status' => $statusName]),
                ['type' => 'ad_status_update', 'ad_id' => $ad->id]
            );
        }

        return sendResponse($ad->fresh(), __('api.ad.status_updated'));
    }

    public function refundRequests(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $status = trim((string) $request->input('status', ''));

        $query = Ad::query()
            ->whereNotNull('refund_status')
            ->with('user:id,name,first_name,last_name,email')
            ->orderByRaw("FIELD(refund_status, 'requested', 'refunded', 'declined')")
            ->orderByDesc('refund_requested_at');

        if (in_array($status, ['requested', 'refunded', 'declined'], true)) {
            $query->where('refund_status', $status);
        }

        $rows = $query->paginate($perPage)->through(function (Ad $ad) {
            $userName = trim(implode(' ', array_filter([$ad->user?->first_name, $ad->user?->last_name])));
            if ($userName === '') {
                $userName = (string) ($ad->user?->name ?? $ad->user?->email ?? '-');
            }

            return [
                'id' => $ad->id,
                'public_id' => $ad->public_id,
                'title' => $ad->title,
                'user_id' => $ad->user_id,
                'user_name' => $userName,
                'user_email' => $ad->user?->email,
                'listing_fee' => $ad->listing_fee,
                'currency' => $ad->currency,
                'refund_status' => $ad->refund_status,
                'refund_requested_at' => optional($ad->refund_requested_at)->toDateTimeString(),
                'refund_request_reason' => $ad->refund_request_reason,
                'refund_reason' => $ad->refund_reason,
                'refunded_at' => optional($ad->refunded_at)->toDateTimeString(),
                'refund_declined_at' => optional($ad->refund_declined_at)->toDateTimeString(),
            ];
        });

        return sendResponse($rows, 'Refund requests fetched');
    }

    public function destroy(Request $request, int $id)
    {
        $ad = Ad::find($id);

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:3000'],
            'source_type' => ['nullable', Rule::in(['ad_report', 'chat_report', 'manual'])],
            'source_id' => ['nullable', 'integer'],
        ]);

        $ad->delete();

        ContentModerationAction::create([
            'admin_id' => $request->user()?->id,
            'user_id' => $ad->user_id,
            'content_type' => 'ad',
            'content_id' => $ad->id,
            'action' => 'content_removed',
            'reason' => $validated['reason'],
            'source_type' => $validated['source_type'] ?? 'manual',
            'source_id' => $validated['source_id'] ?? null,
            'metadata' => ['previous_status' => $ad->status, 'public_id' => $ad->public_id],
            'resolved_at' => now(),
        ]);

        return sendResponse([], __('api.ad.deleted'));
    }

    public function refund(Request $request, int $id)
    {
        $ad = Ad::find($id);

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        if ($ad->payment_status !== 'paid' || ! $ad->stripe_payment_intent_id) {
            return sendError('This ad has no paid Stripe payment to refund.', [], 422);
        }

        if ($ad->refund_status === 'refunded') {
            return sendError('This payment has already been refunded.', [], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $refund = app(StripeService::class)->refund($ad->stripe_payment_intent_id);

        $refundStatus = 'refunded';
        $refundReference = $refund['id'] ?? null;
        $refundReason = $validator->validated()['reason'];
        $refundedAt = now();

        $ad->update([
            'refund_status' => $refundStatus,
            'refund_reference' => $refundReference,
            'refund_reason' => $refundReason,
            'refunded_at' => $refundedAt,
        ]);

        Payment::where('stripe_payment_intent_id', $ad->stripe_payment_intent_id)->update([
            'refund_status' => $refundStatus,
            'refund_reference' => $refundReference,
            'refund_reason' => $refundReason,
            'refunded_at' => $refundedAt,
        ]);

        if ($ad->user) {
            $this->pushNotificationService->sendToUser(
                $ad->user,
                __('api.refund.accepted_title'),
                __('api.refund.accepted_body', ['title' => $ad->title]),
                ['type' => 'ad_refund_accepted', 'ad_id' => $ad->id]
            );
        }

        return sendResponse($ad->fresh(), 'Refund issued');
    }

    public function declineRefund(Request $request, int $id)
    {
        $ad = Ad::find($id);

        if (! $ad) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        if (in_array($ad->refund_status, ['refunded', 'declined'], true)) {
            return sendError('This refund has already been decided on.', [], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $reason = $validator->validated()['reason'];
        $declinedAt = now();

        $ad->update([
            'refund_status' => 'declined',
            'refund_reason' => $reason,
            'refund_declined_at' => $declinedAt,
        ]);

        Payment::where('stripe_payment_intent_id', $ad->stripe_payment_intent_id)->update([
            'refund_status' => 'declined',
            'refund_reason' => $reason,
            'refund_declined_at' => $declinedAt,
        ]);

        if ($ad->user) {
            $this->pushNotificationService->sendToUser(
                $ad->user,
                __('api.refund.declined_title'),
                __('api.refund.declined_body', ['title' => $ad->title, 'reason' => $reason]),
                ['type' => 'ad_refund_declined', 'ad_id' => $ad->id]
            );
        }

        return sendResponse($ad->fresh(), 'Refund declined');
    }
}
