<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Client-generated id used to make a retried send idempotent. Nullable
            // because it is optional: MySQL allows repeated NULLs in a unique
            // index, so clients that omit it are unaffected.
            $table->string('client_message_id', 64)->nullable()->after('type');

            $table->unique(
                ['conversation_id', 'sender_id', 'client_message_id'],
                'messages_client_message_id_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_client_message_id_unique');
            $table->dropColumn('client_message_id');
        });
    }
};
