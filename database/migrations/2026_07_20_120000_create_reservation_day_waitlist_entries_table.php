<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_day_waitlist_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->nullable()->constrained('users')->nullOnDelete();
            // null therapist = "any therapist" that day.
            $table->foreignUuid('therapist_id')->nullable()->constrained('therapist_profiles')->nullOnDelete();
            // The service the customer was browsing — for the deep-link prefill only,
            // never part of the match: the waitlist is a therapist's day, not a service.
            $table->foreignUuid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->date('reservation_date');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_date', 'therapist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_day_waitlist_entries');
    }
};
