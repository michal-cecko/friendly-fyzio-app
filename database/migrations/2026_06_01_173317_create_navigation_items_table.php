<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Nullable: top-level items belong to a navigation; nested children
            // belong to a parent item instead (their navigation is the parent's).
            $table->foreignUuid('navigation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('label');
            $table->string('link_type')->default('custom');
            $table->foreignUuid('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('url')->nullable();
            $table->string('target')->default('_self');
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::table('navigation_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('navigation_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
