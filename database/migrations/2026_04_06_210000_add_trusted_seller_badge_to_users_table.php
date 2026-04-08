<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_trusted_seller')->default(false)->comment('بائع موثق بعد موافقة الطلب');
            $table->timestamp('trusted_seller_verified_at')->nullable();
        });

        if (Schema::hasTable('trusted_seller_applications')) {
            $userIds = DB::table('trusted_seller_applications')
                ->where('status', 'approved')
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $latest = DB::table('trusted_seller_applications')
                    ->where('user_id', $userId)
                    ->where('status', 'approved')
                    ->orderByDesc('updated_at')
                    ->first();
                if ($latest) {
                    DB::table('users')->where('id', $userId)->update([
                        'is_trusted_seller' => true,
                        'trusted_seller_verified_at' => $latest->updated_at,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_trusted_seller', 'trusted_seller_verified_at']);
        });
    }
};
