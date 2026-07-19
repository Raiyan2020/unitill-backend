<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'users.view',
            'users.update',
            'users.delete',
            'admins.view',
            'admins.create',
            'admins.update',
            'admins.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            'countries.view',
            'countries.create',
            'countries.update',
            'countries.delete',
            'cities.view',
            'cities.create',
            'cities.update',
            'cities.delete',
            'universities.view',
            'universities.create',
            'universities.update',
            'universities.delete',
            'payment_methods.view',
            'payment_methods.create',
            'payment_methods.update',
            'payment_methods.delete',
            'languages.view',
            'languages.create',
            'languages.update',
            'languages.delete',
            'legal_affairs.view',
            'legal_affairs.create',
            'legal_affairs.update',
            'legal_affairs.delete',
            'contact_reasons.view',
            'contact_reasons.create',
            'contact_reasons.update',
            'contact_reasons.delete',
            'contact_us.view',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'subcategories.view',
            'subcategories.create',
            'subcategories.update',
            'subcategories.delete',
            'notifications.view',
            'notifications.send',
            'ad_reports.view',
            'ad_reports.update',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $superAdminRole = Role::findOrCreate('super-admin', 'web');
        $superAdminRole->syncPermissions(Permission::all());
    }
}
