<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 50)->unique();
            $table->string('title_en');
            $table->string('title_ar')->nullable();
            $table->longText('content_en');
            $table->longText('content_ar')->nullable();
            $table->boolean('is_current')->default(false)->index();
            $table->timestamp('effective_at');
            $table->foreignId('published_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('terms_version_id')->constrained('terms_versions')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('source', 30)->default('app');
            $table->timestamps();

            $table->unique(['user_id', 'terms_version_id']);
            $table->index(['terms_version_id', 'accepted_at']);
        });

        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->index();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending')->index();
            $table->timestamp('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('user_feature_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('feature', 40);
            $table->text('reason');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('lift_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'feature', 'lifted_at'], 'feature_restrictions_active_idx');
        });

        $legacyTermsEn = DB::table('settings')->where('key_id', 'terms_conditions_en')->value('value');
        $legacyTermsAr = DB::table('settings')->where('key_id', 'terms_conditions')->value('value');

        $initialTermsId = DB::table('terms_versions')->insertGetId([
            'version' => '1.0',
            'title_en' => 'Terms and Conditions of Use',
            'title_ar' => 'شروط وأحكام الاستخدام',
            'content_en' => $legacyTermsEn ?: 'The terms and conditions currently published by UniTill apply to use of the service.',
            'content_ar' => $legacyTermsAr ?: 'تسري شروط وأحكام UniTill المنشورة حالياً على استخدام الخدمة.',
            'is_current' => true,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Preserve the meaning of the legacy timestamp: every existing user who
        // had accepted the then-current terms is attached to the initial version.
        DB::table('users')->whereNotNull('terms_accepted_at')->orderBy('id')
            ->chunkById(500, function ($users) use ($initialTermsId) {
                DB::table('terms_acceptances')->insert($users->map(fn ($user) => [
                    'user_id' => $user->id,
                    'terms_version_id' => $initialTermsId,
                    'accepted_at' => $user->terms_accepted_at,
                    'ip_address' => null,
                    'user_agent' => null,
                    'source' => 'legacy_backfill',
                    'created_at' => $user->terms_accepted_at,
                    'updated_at' => $user->terms_accepted_at,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feature_restrictions');
        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('terms_acceptances');
        Schema::dropIfExists('terms_versions');
    }
};
