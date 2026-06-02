<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('inviteable_type');
            $table->uuid('inviteable_id');
            $table->foreignUuid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('token')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['inviteable_type', 'inviteable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
