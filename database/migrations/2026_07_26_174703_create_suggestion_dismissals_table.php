<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestion_dismissals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('type')->index();
            // The facts the dismissal is bound to. '' = stable, so the card stays
            // hidden until the snooze runs out; anything else brings the card back
            // the moment the situation changes.
            $table->string('fingerprint')->default('');
            $table->timestamp('snoozed_until')->nullable();
            $table->foreignUuid('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_dismissals');
    }
};
