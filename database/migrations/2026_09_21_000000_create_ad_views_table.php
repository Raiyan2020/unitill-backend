<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Guests are deduped by an IP+user-agent fingerprint within a
            // rolling day, not permanently — the same guest can be counted
            // again after the bucket rolls over, on purpose (see the request
            // doc: "cannot be deduped permanently, we would rather
            // under-count than double-count").
            $table->string('guest_key', 64)->nullable();
            $table->date('viewed_on')->nullable();
            $table->timestamp('created_at')->nullable();

            // MySQL treats each NULL as distinct in a unique index, so an
            // authenticated row (guest_key/viewed_on null) never collides
            // with a guest row (user_id null) here.
            $table->unique(['ad_id', 'user_id']);
            $table->unique(['ad_id', 'guest_key', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_views');
    }
};
