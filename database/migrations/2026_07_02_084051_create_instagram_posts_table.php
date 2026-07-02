<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('instagram_connection_id')
                ->constrained('instagram_connections')
                ->cascadeOnDelete();
            $table->string('instagram_media_id');
            $table->unsignedBigInteger('media_library_item_id')->nullable();
            $table->text('caption')->nullable();
            $table->string('permalink');
            $table->string('media_type');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique(['instagram_connection_id', 'instagram_media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_posts');
    }
};
