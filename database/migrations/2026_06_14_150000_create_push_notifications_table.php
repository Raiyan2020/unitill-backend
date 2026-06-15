<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('audience', 20)->comment('all = topic broadcast, user = single user');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic')->nullable();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('status', 20)->default('sent');
            $table->string('fcm_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('recipients_count')->nullable();
            $table->timestamps();

            $table->index(['audience', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
