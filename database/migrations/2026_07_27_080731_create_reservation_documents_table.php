<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files attached to a reservation by the client — today only the doctor's note
     * that suspends a late-cancellation fee. Deliberately NOT the media library:
     * these are medical documents and must stay on the private disk, out of the
     * admin's browsable media picker.
     */
    public function up(): void
    {
        Schema::create('reservation_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size');
            // Null when a guest uploaded through the signed magic link.
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_documents');
    }
};
