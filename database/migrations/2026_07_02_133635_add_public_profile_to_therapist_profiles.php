<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('user_id');
            $table->string('title')->nullable()->after('bio');
            $table->string('badge')->nullable()->after('title');
            $table->string('photo')->nullable()->after('badge');
            $table->json('education')->nullable()->after('photo');
            $table->json('certifications')->nullable()->after('education');
            $table->integer('display_order')->default(0)->after('certifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'title', 'badge', 'photo', 'education', 'certifications', 'display_order']);
        });
    }
};
