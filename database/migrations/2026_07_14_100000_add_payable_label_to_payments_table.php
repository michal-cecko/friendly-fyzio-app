<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            // History snapshot of what the payment was for. Captured at creation and
            // kept even after the payable is force-deleted (payable_id is nulled, but
            // payable_type + this label survive for the accounting record).
            $table->string('payable_label')->nullable()->after('payable_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('payable_label');
        });
    }
};
