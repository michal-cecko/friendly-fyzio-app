<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('system_key')->nullable()->unique();
            $table->json('content')->nullable();
            $table->string('status')->default('draft');
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('featured_image')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
