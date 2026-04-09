<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TrustedSellerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TrustedSellerApplicationAdminController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $userId = (int) $request->input('user_id', 0);

        $query = TrustedSellerApplication::query()
            ->with(['user:id,name,first_name,last_name,email', 'category:id'])
            ->orderByDesc('id');

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'draft'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('seller_type', 'like', "%{$search}%")
                    ->orWhere('operations_city', 'like', "%{$search}%")
                    ->orWhere('primary_contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $rows = $query->paginate($perPage);

        $rows->getCollection()->transform(function (TrustedSellerApplication $row) {
            $userName = trim(
                implode(' ', array_filter([
                    $row->user?->first_name,
                    $row->user?->last_name,
                ]))
            );
            if ($userName === '') {
                $userName = (string) ($row->user?->name ?? $row->user?->email ?? '-');
            }

            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user_name' => $userName,
                'user_email' => $row->user?->email,
                'seller_type' => $row->seller_type,
                'operations_city' => $row->operations_city,
                'primary_contact_name' => $row->primary_contact_name,
                'contact_email' => $row->contact_email,
                'contact_phone' => $row->contact_phone,
                'estimated_ads_volume' => $row->estimated_ads_volume,
                'preferred_student_contact_method' => $row->preferred_student_contact_method,
                'status' => $row->status,
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ];
        });

        return sendResponse($rows, 'Trusted seller applications fetched');
    }

    public function pendingCount()
    {
        $count = TrustedSellerApplication::query()
            ->whereIn('status', ['pending', 'draft'])
            ->count();

        return sendResponse(['count' => $count], 'Pending trusted seller applications count');
    }

    public function show(int $id)
    {
        $row = TrustedSellerApplication::query()
            ->with(['user:id,name,first_name,last_name,email,phone,is_trusted_seller,trusted_seller_verified_at', 'category:id'])
            ->find($id);

        if (! $row) {
            return sendError('Trusted seller application not found', [], 404);
        }

        $userName = trim(
            implode(' ', array_filter([
                $row->user?->first_name,
                $row->user?->last_name,
            ]))
        );
        if ($userName === '') {
            $userName = (string) ($row->user?->name ?? $row->user?->email ?? '-');
        }

        return sendResponse([
            'id' => $row->id,
            'user_id' => $row->user_id,
            'user_name' => $userName,
            'user_email' => $row->user?->email,
            'user_phone' => $row->user?->phone,
            'user_is_trusted_seller' => (bool) ($row->user?->is_trusted_seller),
            'user_trusted_seller_verified_at' => optional($row->user?->trusted_seller_verified_at)->toDateTimeString(),
            'seller_type' => $row->seller_type,
            'is_non_student_confirmed' => (bool) $row->is_non_student_confirmed,
            'operations_city' => $row->operations_city,
            'primary_contact_name' => $row->primary_contact_name,
            'contact_email' => $row->contact_email,
            'contact_phone' => $row->contact_phone,
            'category_id' => $row->category_id,
            'offers_summary' => $row->offers_summary,
            'estimated_ads_volume' => $row->estimated_ads_volume,
            'preferred_student_contact_method' => $row->preferred_student_contact_method,
            'ack_review_discretion' => (bool) $row->ack_review_discretion,
            'ack_unitill_manages_directory' => (bool) $row->ack_unitill_manages_directory,
            'ack_no_app_access' => (bool) $row->ack_no_app_access,
            'status' => $row->status,
            'created_at' => optional($row->created_at)->toDateTimeString(),
            'updated_at' => optional($row->updated_at)->toDateTimeString(),
        ], 'Trusted seller application details');
    }

    public function update(Request $request, int $id)
    {
        $row = TrustedSellerApplication::find($id);

        if (! $row) {
            return sendError('Trusted seller application not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'draft'])],
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $row->status = $validator->validated()['status'];
        $row->save();

        return sendResponse($row->fresh(), 'Trusted seller application status updated');
    }
}

