<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When an owner deletes their account, the organization is marked closed; its records are kept. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'closed_at')) {
            Schema::table('organizations', fn (Blueprint $table) => $table->timestamp('closed_at')->nullable()->after('email'));
        }
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('closed_at'));
    }
};
