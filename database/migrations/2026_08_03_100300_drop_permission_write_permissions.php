<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * The Permissions admin screen was removed — permissions are derived from the
 * pages and actions the code implements, not authored by hand. Its write
 * permissions are therefore dead rows that would still appear, selectable and
 * meaningless, in the Roles permission picker.
 */
return new class extends Migration
{
    private const DEAD = [
        'permissions.create',
        'permissions.update',
        'permissions.delete',
    ];

    public function up(): void
    {
        Permission::query()->whereIn('name', self::DEAD)->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::DEAD as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
