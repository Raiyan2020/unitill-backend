<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentMethodAdminController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));

        $query = PaymentMethod::query()->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return sendResponse($query->paginate($perPage), 'Payment methods fetched');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:payment_methods,slug',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('payment-methods', 'public');
        }

        $paymentMethod = PaymentMethod::create($data);

        return sendResponse($paymentMethod, 'Payment method created');
    }

    public function update(Request $request, int $id)
    {
        $paymentMethod = PaymentMethod::find($id);

        if (! $paymentMethod) {
            return sendError('Payment method not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name_ar' => 'sometimes|required|string|max:255',
            'name_en' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('payment_methods', 'slug')->ignore($id)],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('image')) {
            if (! empty($paymentMethod->image)) {
                Storage::disk('public')->delete($paymentMethod->image);
            }
            $data['image'] = $request->file('image')->store('payment-methods', 'public');
        }

        $paymentMethod->update($data);

        return sendResponse($paymentMethod->fresh(), 'Payment method updated');
    }

    public function destroy(int $id)
    {
        $paymentMethod = PaymentMethod::find($id);

        if (! $paymentMethod) {
            return sendError('Payment method not found', [], 404);
        }

        if (! empty($paymentMethod->image)) {
            Storage::disk('public')->delete($paymentMethod->image);
        }

        $paymentMethod->delete();

        return sendResponse([], 'Payment method deleted');
    }
}
