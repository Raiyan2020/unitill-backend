<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
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
            'mainCategory.translations',
            'subCategory:id,parent_id',
            'subCategory.translations',
            'images:id,ad_id,path,sort_order',
            'attributeValues.definition.translations',
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

