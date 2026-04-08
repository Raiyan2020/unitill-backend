<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        return sendResponse(
            Permission::query()->orderBy('id')->get(['id', 'name', 'guard_name']),
            'Permissions fetched'
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $permission = Permission::create([
            'name' => $validator->validated()['name'],
            'guard_name' => 'web',
        ]);

        return sendResponse($permission, 'Permission created');
    }

    public function update(Request $request, int $id)
    {
        $permission = Permission::find($id);

        if (! $permission) {
            return sendError('Permission not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($id)],
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $permission->update($validator->validated());

        return sendResponse($permission->fresh(), 'Permission updated');
    }

    public function destroy(int $id)
    {
        $permission = Permission::find($id);

        if (! $permission) {
            return sendError('Permission not found', [], 404);
        }

        $permission->delete();

        return sendResponse([], 'Permission deleted');
    }
}
