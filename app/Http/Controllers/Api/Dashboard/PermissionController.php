<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

/**
 * Read-only. Permissions are derived from the system's pages and the actions
 * each page supports, and are seeded by RolePermissionSeeder — an admin cannot
 * invent a permission the code does not already check, so create/update/delete
 * were removed along with the dashboard screen that offered them. Roles stay
 * fully editable; this list only feeds their permission picker.
 */
class PermissionController extends Controller
{
    public function index()
    {
        return sendResponse(
            Permission::query()->orderBy('id')->get(['id', 'name', 'guard_name']),
            'Permissions fetched'
        );
    }
}
