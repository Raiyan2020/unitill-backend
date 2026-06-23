<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\PushNotificationService;
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
            'subCategory:id,parent_id',
            'images:id,ad_id,path,sort_order',
        ])->find($id);

        if (! $ad) {
            return sendError('Ad not found', [], 404);
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

    public function update(Request $request, int $id)
    {
        $ad = Ad::with('user')->find($id);

        if (! $ad) {
            return sendError('Ad not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['draft', 'pending', 'published', 'rejected', 'sold', 'expired'])],
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $oldStatus = $ad->status;
        $newStatus = $validator->validated()['status'];

        $ad->status = $newStatus;
        $ad->save();

        if ($oldStatus !== $newStatus && $ad->user) {
            $lang = $request->header('lang') === 'ar';
            $title = $lang ? 'تحديث حالة الإعلان' : 'Ad Status Update';
            
            $statusName = $newStatus;
            if ($lang) {
                $statusMap = [
                    'published' => 'مقبول / منشور',
                    'rejected' => 'مرفوض',
                    'pending' => 'قيد المراجعة',
                    'expired' => 'منتهي',
                    'draft' => 'مسودة',
                    'sold' => 'مباع',
                ];
                $statusName = $statusMap[$newStatus] ?? $newStatus;
            }

            $body = $lang 
                ? "تم تغيير حالة إعلانك '{$ad->title}' إلى {$statusName}"
                : "Your ad '{$ad->title}' status has been updated to {$statusName}";

            $this->pushNotificationService->sendToUser(
                $ad->user,
                $title,
                $body,
                ['type' => 'ad_status_update', 'ad_id' => $ad->id]
            );
        }

        return sendResponse($ad->fresh(), 'Ad status updated');
    }

    public function destroy(int $id)
    {
        $ad = Ad::find($id);

        if (! $ad) {
            return sendError('Ad not found', [], 404);
        }

        $ad->delete();

        return sendResponse([], 'Ad deleted');
    }
}

