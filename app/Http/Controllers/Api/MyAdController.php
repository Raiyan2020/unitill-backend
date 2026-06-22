<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyAdResource;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MyAdController extends Controller
{
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
            return sendError($lang ? 'الإعلان غير موجود' : 'Ad not found', [], 404);
        }

        if ($ad->status !== 'published') {
            return sendError(
                $lang ? 'يمكن عرض المشترين للإعلانات النشطة فقط' : 'Buyers are available for active ads only',
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
            return sendError($lang ? 'الإعلان غير موجود' : 'Ad not found', [], 404);
        }

        if ($ad->status !== 'published') {
            return sendError(
                $lang ? 'يمكن تعليم الإعلانات النشطة فقط كمباعة' : 'Only active ads can be marked as sold',
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
                $lang ? 'اختر مشترياً أو حدد البيع خارج التطبيق' : 'Select a buyer or mark as sold outside UniTill',
                [],
                422
            );
        }

        if ($buyerId && (int) $buyerId === (int) Auth::id()) {
            return sendError(
                $lang ? 'لا يمكنك اختيار نفسك كمشتري' : 'You cannot select yourself as buyer',
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
            $lang ? 'تم تعليم الإعلان كمباع' : 'Ad marked as sold'
        );
    }

    public function pause(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $ad = $this->findOwnedAd($id);

        if (! $ad || $ad->status !== 'published') {
            return sendError(
                $lang ? 'يمكن إيقاف الإعلانات النشطة فقط' : 'Only active ads can be paused',
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
            $lang ? 'تم إيقاف الإعلان' : 'Ad paused successfully'
        );
    }

    public function activate(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $ad = $this->findOwnedAd($id);

        if (! $ad || ! in_array($ad->status, ['paused', 'expired'], true)) {
            return sendError(
                $lang ? 'يمكن تفعيل الإعلانات الموقوفة أو المنتهية فقط' : 'Only paused or expired ads can be activated',
                [],
                422
            );
        }

        $durationDays = (int) (setting('post_duration') ?? 30);

        $ad->update([
            'status' => 'published',
            'published_at' => now(),
            'expires_at' => now()->addDays($durationDays),
            'paused_at' => null,
            'inactive_reason' => null,
        ]);

        return sendResponse(
            new MyAdResource($ad->fresh()),
            $lang ? 'تم تفعيل الإعلان' : 'Ad activated successfully'
        );
    }

    public function destroy(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $ad = $this->findOwnedAd($id);

        if (! $ad) {
            return sendError($lang ? 'الإعلان غير موجود' : 'Ad not found', [], 404);
        }

        if (! in_array($ad->status, ['paused', 'expired', 'draft', 'rejected', 'pending'], true)) {
            return sendError(
                $lang ? 'لا يمكن حذف هذا الإعلان' : 'This ad cannot be deleted',
                [],
                422
            );
        }

        $ad->delete();

        return sendResponse(
            ['ad_id' => (int) $id],
            $lang ? 'تم حذف الإعلان' : 'Ad deleted successfully'
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
