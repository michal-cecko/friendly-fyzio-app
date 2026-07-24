<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // When the account was last reactivated. `deactivated_at` stays the
            // source of truth for whether it is *currently* deactivated; this
            // keeps the audit date of the last reactivation on the record.
            $table->timestamp('reactivated_at')->nullable()->after('deactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('reactivated_at');
        });
    }
};
