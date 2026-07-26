<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('universities')) {
            Schema::create('universities', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('country_code', 2)->default('GB')->comment('ISO 3166-1 alpha-2');
                $table->string('state')->nullable();
                $table->string('city')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->index('name');
                $table->index('state');
            });
        }

        if (! Schema::hasTable('university_domains')) {
            Schema::create('university_domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
                $table->string('domain')->unique()->comment('email domain / subdomain, e.g. harvard.edu');
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('university_domains');
        Schema::dropIfExists('universities');
    }
};
