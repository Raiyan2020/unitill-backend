<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // What the payment is for: a new listing, or extending an expired one.
            $table->string('type')->default('listing');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3);
            // requires_payment | paid | free | waived | coupon | payment_setup_failed
            $table->string('status');
            $table->string('payment_method_type')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('refund_status')->nullable();
            $table->string('refund_reference')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamp('refund_requested_at')->nullable();
            $table->text('refund_request_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('refund_declined_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
