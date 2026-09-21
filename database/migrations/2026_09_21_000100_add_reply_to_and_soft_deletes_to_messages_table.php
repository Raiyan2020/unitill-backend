<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained('messages')
                ->nullOnDelete();

            // No delete-message endpoint exists yet, but a quoted original must
            // still resolve (with is_deleted: true) once one does — groundwork
            // for that, not itself a delete feature.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_message_id');
            $table->dropSoftDeletes();
        });
    }
};
