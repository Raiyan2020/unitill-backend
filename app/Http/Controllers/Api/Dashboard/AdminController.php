<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Ad;
use App\Models\ContactUsMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));

        $query = Admin::query()->with('roles')->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $admins = $query->paginate($perPage)->through(function (Admin $admin) {
            return [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => $admin->getRoleNames()->values(),
                'created_at' => $admin->created_at,
            ];
        });

        return sendResponse($admins, 'Admins fetched');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:admins,email',
            'password' => 'required|string|min:6',
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        $admin = Admin::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'roles_name' => isset($data['roles']) ? json_encode(array_values($data['roles'])) : null,
        ]);

        if (! empty($data['roles'])) {
            $admin->syncRoles($data['roles']);
        }

        return sendResponse($admin->load('roles'), 'Admin created');
    }

    public function show(int $id)
    {
        $admin = Admin::with('roles.permissions')->find($id);

        if (! $admin) {
            return sendError('Admin not found', [], 404);
        }

        return sendResponse([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => $admin->getRoleNames()->values(),
            'permissions' => $admin->getAllPermissions()->pluck('name')->values(),
        ], 'Admin details');
    }

    public function update(Request $request, int $id)
    {
        $admin = Admin::find($id);

        if (! $admin) {
            return sendError('Admin not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($id)],
            'password' => 'sometimes|nullable|string|min:6',
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        if (array_key_exists('password', $data) && empty($data['password'])) {
            unset($data['password']);
        }

        if (array_key_exists('roles', $data)) {
            $admin->roles_name = json_encode(array_values($data['roles']));
            $admin->syncRoles($data['roles']);
            unset($data['roles']);
        }

        $admin->update($data);

        return sendResponse($admin->fresh()->load('roles'), 'Admin updated');
    }

    public function destroy(int $id)
    {
        $admin = Admin::find($id);

        if (! $admin) {
            return sendError('Admin not found', [], 404);
        }

        $admin->delete();

        return sendResponse([], 'Admin deleted');
    }

    public function profile(Request $request)
    {
        /** @var Admin|null $admin */
        $admin = $request->user();

        if (! $admin) {
            return sendError('Unauthorized', [], 401);
        }

        return sendResponse([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => $admin->getRoleNames()->values(),
            'permissions' => $admin->getAllPermissions()->pluck('name')->values(),
        ], 'Profile fetched');
    }

    public function updateProfile(Request $request)
    {
        /** @var Admin|null $admin */
        $admin = $request->user();

        if (! $admin) {
            return sendError('Unauthorized', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $admin->update($validator->validated());

        return sendResponse($admin->fresh(), 'Profile updated');
    }

    public function updatePassword(Request $request)
    {
        /** @var Admin|null $admin */
        $admin = $request->user();

        if (! $admin) {
            return sendError('Unauthorized', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $data = $validator->validated();

        if (! Hash::check($data['current_password'], $admin->password)) {
            return sendError('Current password is incorrect', [], 422);
        }

        $admin->update([
            'password' => $data['new_password'],
        ]);

        return sendResponse([], 'Password updated');
    }

    public function dashboardStats()
    {
        $usersCount = User::query()->count();
        $activeAdsCount = Ad::query()->where('status', 'published')->count();
        $inactiveAdsCount = Ad::query()->where('status', '!=', 'published')->count();
        $contactMessagesCount = ContactUsMessage::query()->count();
        $revenue = (float) (Ad::query()
            ->where('status', 'published')
            ->sum('price') ?? 0);

        $startDate = Carbon::today()->subDays(9);
        $endDate = Carbon::today();

        $adsByDay = Ad::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as ads_count')
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('ads_count', 'day');

        $revenueByDay = Ad::query()
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(price), 0) as revenue_sum')
            ->where('status', 'published')
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('revenue_sum', 'day');

        $timeline = [];
        for ($i = 0; $i < 10; $i++) {
            $day = $startDate->copy()->addDays($i);
            $key = $day->toDateString();

            $timeline[] = [
                'date' => $key,
                'label' => $day->format('j M'),
                'ads_count' => (int) ($adsByDay[$key] ?? 0),
                'revenue' => (float) ($revenueByDay[$key] ?? 0),
            ];
        }

        return sendResponse([
            'users_count' => $usersCount,
            'active_ads_count' => $activeAdsCount,
            'inactive_ads_count' => $inactiveAdsCount,
            'contact_messages_count' => $contactMessagesCount,
            'revenue' => round($revenue, 2),
            'last_10_days' => $timeline,
        ], 'Dashboard stats fetched');
    }
}
