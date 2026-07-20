<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CouponAdminController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');

        $query = Coupon::query()->withCount('redemptions')->orderByDesc('id');

        if ($search !== '') {
            $query->where('code', 'like', "%{$search}%");
        }

        // "expired" and "exhausted" are derived states, not stored columns.
        match ($status) {
            'active' => $query->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            'inactive' => $query->where('is_active', false),
            'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<=', now()),
            default => null,
        };

        $rows = $query->paginate($perPage);
        $rows->getCollection()->transform(fn (Coupon $coupon) => $this->present($coupon));

        return sendResponse(['coupons' => $rows, 'counts' => $this->counts()], 'Coupons fetched');
    }

    public function show(int $id)
    {
        $coupon = Coupon::withCount('redemptions')->find($id);

        if (! $coupon) {
            return sendError('Coupon not found', [], 404);
        }

        $redemptions = $coupon->redemptions()
            ->with(['user:id,name,first_name,last_name,email', 'ad:id,public_id,title'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($redemption) => [
                'id' => $redemption->id,
                'user' => $redemption->user ? [
                    'id' => $redemption->user->id,
                    'name' => $this->displayName($redemption->user),
                    'email' => $redemption->user->email,
                ] : null,
                'ad' => $redemption->ad ? [
                    'id' => $redemption->ad->id,
                    'title' => $redemption->ad->title,
                ] : null,
                'original_amount' => (float) $redemption->original_amount,
                'discount_amount' => (float) $redemption->discount_amount,
                'final_amount' => (float) $redemption->final_amount,
                'created_at' => optional($redemption->created_at)->toDateTimeString(),
            ]);

        return sendResponse(
            $this->present($coupon) + ['redemptions' => $redemptions],
            'Coupon details'
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules($request), $this->messages());

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $coupon = Coupon::create($this->payload($validator->validated()));

        return sendResponse($this->present($coupon->loadCount('redemptions')), 'Coupon created');
    }

    public function update(Request $request, int $id)
    {
        $coupon = Coupon::find($id);

        if (! $coupon) {
            return sendError('Coupon not found', [], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request, $coupon->id), $this->messages());

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $coupon->update($this->payload($validator->validated()));

        return sendResponse($this->present($coupon->fresh()->loadCount('redemptions')), 'Coupon updated');
    }

    /**
     * A coupon that has been redeemed is deactivated rather than deleted, so
     * the redemption history stays intact and past discounts remain auditable.
     */
    public function destroy(int $id)
    {
        $coupon = Coupon::withCount('redemptions')->find($id);

        if (! $coupon) {
            return sendError('Coupon not found', [], 404);
        }

        if ($coupon->redemptions_count > 0) {
            $coupon->update(['is_active' => false]);

            return sendResponse(
                $this->present($coupon->fresh()->loadCount('redemptions')),
                'Coupon has been used, so it was deactivated instead of deleted'
            );
        }

        $coupon->delete();

        return sendResponse([], 'Coupon deleted');
    }

    /**
     * The cap on `value` depends on `type`, so the type is read from the passed
     * request rather than the global request() helper — the helper is unset
     * when the controller is invoked outside the HTTP kernel and would silently
     * drop the percentage ceiling.
     */
    private function rules(Request $request, ?int $ignoreId = null): array
    {
        $isPercentage = $request->input('type') === 'percentage';

        return [
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($ignoreId),
            ],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            // Over 100% would make the listing fee negative.
            'value' => ['required', 'numeric', 'min:0.01', $isPercentage ? 'max:100' : 'max:99999'],
            'max_discount' => 'nullable|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_redemptions' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * The default max/min messages come out as raw translation keys in this
     * project, so the coupon-specific ones are spelled out.
     */
    private function messages(): array
    {
        return [
            "value.max" => "A percentage discount cannot be more than 100%.",
            "value.min" => "The discount value must be greater than zero.",
            "code.regex" => "The code may only contain letters, numbers, hyphens and underscores.",
            "code.unique" => "This code already exists.",
            "expires_at.after" => "The expiry date must be after the start date.",
        ];
    }
    private function payload(array $data): array
    {
        return [
            'code' => strtoupper(trim($data['code'])),
            'type' => $data['type'],
            'value' => $data['value'],
            'max_discount' => $data['max_discount'] ?? null,
            'min_amount' => $data['min_amount'] ?? null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    private function counts(): array
    {
        return [
            'total' => Coupon::count(),
            'active' => Coupon::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'expired' => Coupon::whereNotNull('expires_at')->where('expires_at', '<=', now())->count(),
            'redemptions' => \App\Models\CouponRedemption::count(),
        ];
    }

    private function present(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'value_label' => $coupon->type === 'percentage'
                ? rtrim(rtrim(number_format((float) $coupon->value, 2, '.', ''), '0'), '.').'%'
                : '£'.number_format((float) $coupon->value, 2),
            'max_discount' => $coupon->max_discount !== null ? (float) $coupon->max_discount : null,
            'min_amount' => $coupon->min_amount !== null ? (float) $coupon->min_amount : null,
            'max_redemptions' => $coupon->max_redemptions,
            'redemptions_count' => $coupon->redemptions_count ?? $coupon->redemptions()->count(),
            'starts_at' => optional($coupon->starts_at)->toDateTimeString(),
            'expires_at' => optional($coupon->expires_at)->toDateTimeString(),
            'is_active' => (bool) $coupon->is_active,
            'is_expired' => $coupon->isExpired(),
            'is_exhausted' => $coupon->isExhausted(),
            'created_at' => optional($coupon->created_at)->toDateTimeString(),
        ];
    }

    private function displayName($user): string
    {
        $name = trim(implode(' ', array_filter([$user->first_name, $user->last_name])));

        return $name !== '' ? $name : (string) ($user->name ?? $user->email ?? '-');
    }
}
