<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->timestamp('refund_requested_at')->nullable()->after('payment_status');
            $table->text('refund_request_reason')->nullable()->after('refund_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn(['refund_requested_at', 'refund_request_reason']);
        });
    }
};
