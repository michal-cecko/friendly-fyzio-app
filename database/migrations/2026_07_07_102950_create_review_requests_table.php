<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reviewable_type');
            $table->uuid('reviewable_id');
            $table->string('channel')->default('automatic');
            // Magic-link token: the only way to reach the public review form.
            $table->string('token')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            // The review that was ultimately submitted through this request, if any.
            $table->foreignUuid('review_id')->nullable()->constrained('reviews')->nullOnDelete();
            $table->timestamps();

            // Non-unique: manual re-sends are allowed. Automatic dedup is an
            // ->exists() check in the send-requests command, not a DB constraint.
            $table->index(['reviewable_type', 'reviewable_id']);
            $table->index(['user_id', 'reviewable_type', 'reviewable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_requests');
    }
};
