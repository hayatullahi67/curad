<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A notification is written either for someone as a customer or for
 * someone as a pharmacy owner. One account can be both, so each
 * notification now says which, and each dashboard shows only its own kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('notifications', 'audience')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('audience', 20)->default('customer')->after('user_id');
                $table->index(['user_id', 'audience', 'read_at']);
            });
        }

        // Existing notifications: these are the ones that were written for the pharmacy.
        DB::table('notifications')
            ->where(function ($query) {
                $query->whereIn('title', ['New online order', 'Order cancelled', 'Low Stock Alert', 'Critical Stock Level'])
                    ->orWhere(fn ($delivered) => $delivered->where('title', 'Order delivered')->where('message', 'like', '%was delivered to the customer%'));
            })
            ->update(['audience' => 'pharmacy']);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'audience', 'read_at']);
            $table->dropColumn('audience');
        });
    }
};
