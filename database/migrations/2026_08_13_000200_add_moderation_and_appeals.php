<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_until')->nullable()->after('status');
            $table->unsignedInteger('warning_count')->default(0)->after('suspended_until');
        });

        Schema::table('chat_reports', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->unsignedBigInteger('conversation_id')->nullable()->change();
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
            $table->enum('priority', ['normal', 'urgent', 'critical'])->default('normal')->after('status');
            $table->index(['priority', 'status']);
        });

        Schema::table('ad_reports', function (Blueprint $table) {
            $table->enum('priority', ['normal', 'urgent', 'critical'])->default('normal')->after('status');
            $table->index(['priority', 'status']);
        });

        Schema::create('user_moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->enum('action', [
                'warning', 'temporary_suspension', 'permanent_suspension',
                'reactivated', 'suspension_expired',
            ]);
            $table->text('reason');
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('moderation_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('moderation_action_id')->constrained('user_moderation_actions')->cascadeOnDelete();
            $table->text('message');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'moderation_action_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_appeals');
        Schema::dropIfExists('user_moderation_actions');

        Schema::table('ad_reports', function (Blueprint $table) {
            $table->dropIndex(['priority', 'status']);
            $table->dropColumn('priority');
        });

        Schema::table('chat_reports', function (Blueprint $table) {
            $table->dropIndex(['priority', 'status']);
            $table->dropForeign(['conversation_id']);
            $table->dropColumn('priority');
            $table->unsignedBigInteger('conversation_id')->nullable(false)->change();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_until', 'warning_count']);
        });
    }
};
