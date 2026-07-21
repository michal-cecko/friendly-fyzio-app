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
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('date_of_birth');
            // Rodné číslo — sensitive identifier, stored via the encrypted cast,
            // hence a text column (ciphertext exceeds the plain value's length).
            $table->text('birth_number')->nullable()->after('gender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropColumn(['gender', 'birth_number']);
        });
    }
};
