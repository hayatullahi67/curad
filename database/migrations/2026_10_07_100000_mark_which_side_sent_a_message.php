<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records which side of a conversation sent each message. Until now that
 * was worked out from who the sender was, which breaks when one account is
 * on both sides: a pharmacy owner who also orders, as a customer, from
 * their own branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('messages', 'from_pharmacy')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->boolean('from_pharmacy')->default(false)->after('sender_id');
            });
        }

        // Existing messages: anything not written by the conversation's customer came from the pharmacy.
        DB::statement('UPDATE messages m JOIN conversations c ON c.id = m.conversation_id SET m.from_pharmacy = (m.sender_id <> c.user_id)');
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn('from_pharmacy'));
    }
};
