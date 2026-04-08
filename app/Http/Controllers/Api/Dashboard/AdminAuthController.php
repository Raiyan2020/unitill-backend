<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $admin = Admin::with('roles.permissions')->where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return sendError('Invalid admin credentials', [], 401);
        }

        return sendResponse([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => $admin->getRoleNames()->values(),
                'permissions' => $admin->getAllPermissions()->pluck('name')->values(),
            ],
            'token' => $admin->createToken('admin-panel')->plainTextToken,
        ], 'Admin login success');
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return sendResponse([], 'Admin logout success');
    }
}
