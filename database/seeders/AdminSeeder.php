<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@admin.net'],
            [
                'name' => 'Super Admin',
                'password' => '123456',
                'roles_name' => json_encode(['super-admin']),
            ]
        );

        $admin = Admin::where('email', 'admin@admin.net')->first();
        if ($admin) {
            $admin->syncRoles(['super-admin']);
        }
    }
}
