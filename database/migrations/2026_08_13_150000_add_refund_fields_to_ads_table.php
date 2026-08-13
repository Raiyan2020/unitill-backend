<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->string('refund_status')->nullable()->after('payment_status');
            $table->string('refund_reference')->nullable()->after('refund_status');
            $table->text('refund_reason')->nullable()->after('refund_reference');
            $table->timestamp('refunded_at')->nullable()->after('refund_reason');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn(['refund_status', 'refund_reference', 'refund_reason', 'refunded_at']);
        });
    }
};
