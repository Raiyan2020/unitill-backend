<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ad;
use App\Models\UserNotification;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);
        $orders = Order::query()
            ->where('buyer_id', Auth::id())
            ->with(['ad:id,public_id,title,cover_image,price,currency,status'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return sendResponse($orders->through(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'created_at' => $order->created_at->toIso8601String(),
                'ad' => [
                    'id' => $order->ad->id ?? null,
                    'public_id' => $order->ad->public_id ?? null,
                    'title' => $order->ad->title ?? '',
                    'cover_image_url' => $order->ad->cover_image_url ?? '',
                    'price' => $order->ad->price ?? 0,
                    'currency' => $order->ad->currency ?? 'KWD',
                    'status_label' => $order->status,
                ],
            ];
        }));
    }

    public function store(Request $request)
    {
        $request->validate([
            'ad_id' => 'required|exists:ads,id',
            'payment_method' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $ad = Ad::findOrFail($request->ad_id);

        if ($ad->user_id === Auth::id()) {
            return sendError('Cannot order your own ad', [], 422);
        }

        if ($ad->status !== 'published') {
            return sendError('Ad is not available for purchase', [], 422);
        }

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'buyer_id' => Auth::id(),
            'seller_id' => $ad->user_id,
            'ad_id' => $ad->id,
            'status' => 'completed',
            'amount' => $ad->price,
            'currency' => $ad->currency ?? 'KWD',
            'payment_method' => $request->payment_method ?? 'cash',
            'notes' => $request->notes,
        ]);

        // Mark ad as sold optionally
        $ad->update([
            'status' => 'sold',
            'sold_to_user_id' => Auth::id(),
            'sold_at' => now(),
        ]);

        // Notify the seller that their item was purchased (push + inbox).
        $seller = $ad->user;
        if ($seller) {
            $lang = $request->header('lang') === 'ar';
            $buyerName = trim((string) (Auth::user()->first_name ?? '').' '.(Auth::user()->last_name ?? ''))
                ?: (Auth::user()->name ?? '');

            app(PushNotificationService::class)->notifyUser(
                $seller,
                $lang ? 'تم بيع إعلانك' : 'Your item was purchased',
                $lang
                    ? "قام {$buyerName} بشراء \"{$ad->title}\""
                    : "{$buyerName} purchased \"{$ad->title}\"",
                [
                    'type' => 'order',
                    'related_data' => (string) $order->id,
                    'order_id' => (string) $order->id,
                    'ad_id' => (string) $ad->id,
                ],
                UserNotification::TYPE_SYSTEM,
                'notify_ads',
            );
        }

        return sendResponse(['order' => $order], 'Order created successfully');
    }
}
