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
            $table->timestamp('device_token_updated_at')->nullable()->after('device_token')->index();
        });

        DB::table('users')->whereNotNull('device_token')->update([
            'device_token_updated_at' => DB::raw('updated_at'),
        ]);

        Schema::table('user_moderation_actions', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('ends_at')->index();
        });

        DB::table('user_moderation_actions')
            ->whereIn('action', ['warning', 'reactivated', 'suspension_expired'])
            ->update(['resolved_at' => DB::raw('COALESCE(updated_at, created_at)')]);
        DB::table('user_moderation_actions')
            ->where('action', 'temporary_suspension')
            ->whereNotNull('ends_at')
            ->update(['resolved_at' => DB::raw('ends_at')]);

        Schema::table('user_feature_restrictions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('contact_us_messages', function (Blueprint $table) {
            $table->enum('status', ['open', 'closed'])->default('open')->after('message')->index();
            $table->timestamp('closed_at')->nullable()->after('status')->index();
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('admins')->nullOnDelete();
        });

        foreach (['ad_reports', 'chat_reports'] as $reportTable) {
            Schema::table($reportTable, function (Blueprint $table) {
                $table->text('decision_reason')->nullable()->after('status');
                $table->foreignId('resolved_by')->nullable()->after('decision_reason')->constrained('admins')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable()->after('resolved_by')->index();
            });
        }

        DB::table('ad_reports')->whereIn('status', ['reviewed', 'dismissed'])
            ->update(['resolved_at' => DB::raw('updated_at')]);
        DB::table('chat_reports')->whereIn('status', ['reviewed', 'dismissed'])
            ->update(['resolved_at' => DB::raw('updated_at')]);

        Schema::create('content_moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('content_type', 40);
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('action', 50);
            $table->text('reason');
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->index(['content_type', 'content_id']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_moderation_actions');

        foreach (['ad_reports', 'chat_reports'] as $reportTable) {
            Schema::table($reportTable, function (Blueprint $table) {
                $table->dropForeign(['resolved_by']);
                $table->dropColumn(['decision_reason', 'resolved_by', 'resolved_at']);
            });
        }

        Schema::table('contact_us_messages', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropColumn(['status', 'closed_at', 'closed_by']);
        });

        DB::table('user_feature_restrictions')->whereNull('user_id')->delete();
        Schema::table('user_feature_restrictions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('user_moderation_actions', function (Blueprint $table) {
            $table->dropColumn('resolved_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('device_token_updated_at');
        });
    }
};
