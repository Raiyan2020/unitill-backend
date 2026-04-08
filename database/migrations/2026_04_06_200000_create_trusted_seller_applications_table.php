<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_seller_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('seller_type', ['business', 'service_provider', 'organization'])
                ->comment('عمل | مقدم خدمة | منظمة/مؤسسة');

            $table->boolean('is_non_student_confirmed')->default(false)->comment('تأكيد غير الطلاب');
            $table->string('operations_city')->nullable()->comment('مدينة العمليات');
            $table->string('primary_contact_name')->nullable()->comment('اسم جهة الاتصال الرئيسية');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()->comment('فئة الإعلان / القسم');
            $table->text('offers_summary')->nullable()->comment('وصف موجز للعروض');

            $table->enum('estimated_ads_volume', ['single', 'multiple'])
                ->comment('العدد التقديري للإعلانات: واحد | متعدد');

            $table->enum('preferred_student_contact_method', ['phone', 'email', 'website'])
                ->comment('طريقة الاتصال المفضلة للطالب');

            $table->boolean('ack_review_discretion')->default(false)
                ->comment('UniTill تُراجع وقد توافق أو ترفض');
            $table->boolean('ack_unitill_manages_directory')->default(false)
                ->comment('UniTill تنشئ وتدير قوائم البائعين الموثوقين');
            $table->boolean('ack_no_app_access')->default(false)
                ->comment('البائعون الموثوقون لا يصلون لتطبيق UniTill');

            $table->enum('status', ['pending', 'approved', 'rejected', 'draft'])->default('pending');

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_seller_applications');
    }
};
