<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('questionnaire_url')->nullable()->after('description');
        });

        Schema::table('workshops', function (Blueprint $table): void {
            $table->string('questionnaire_url')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('questionnaire_url');
        });

        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropColumn('questionnaire_url');
        });
    }
};
