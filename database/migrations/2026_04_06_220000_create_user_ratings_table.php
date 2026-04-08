<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete()->comment('من يقيّم');
            $table->foreignId('rated_user_id')->constrained('users')->cascadeOnDelete()->comment('المقيَّم');
            $table->unsignedTinyInteger('score')->comment('من 1 إلى 5');
            $table->text('comment')->nullable();
            $table->foreignId('ad_id')->nullable()->constrained()->nullOnDelete()->comment('اختياري: مرتبط بإعلان');
            $table->timestamps();

            $table->unique(['rater_id', 'rated_user_id']);
            $table->index('rated_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ratings');
    }
};
