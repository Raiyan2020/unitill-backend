<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));

        $query = User::query()->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return sendResponse($query->paginate($perPage), 'Users fetched');
    }

    public function show(int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return sendError('User not found', [], 404);
        }

        return sendResponse($user, 'User details');
    }

    public function update(Request $request, int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return sendError('User not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($id)],
            'country_code' => 'sometimes|nullable|string|max:20',
            'city_id' => 'sometimes|nullable|integer|exists:cities,id',
            'status' => ['sometimes', 'required', Rule::in(['1', '2', '3'])],
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $first = $data['first_name'] ?? $user->first_name ?? '';
            $last = $data['last_name'] ?? $user->last_name ?? '';
            $data['name'] = trim($first.' '.$last);
        }

        $user->update($data);

        return sendResponse($user->fresh(), 'User updated');
    }

    public function destroy(int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return sendError('User not found', [], 404);
        }

        $user->delete();

        return sendResponse([], 'User deleted');
    }
}
