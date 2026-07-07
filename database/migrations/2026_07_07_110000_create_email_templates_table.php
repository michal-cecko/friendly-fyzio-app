<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supersedes an unused early scaffold (event_type/body_html) that owned this
        // table name. dropIfExists keeps existing dev databases migratable without
        // touching any other table.
        Schema::dropIfExists('email_templates');

        Schema::create('email_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Stable trigger key (e.g. reservation_pending); how sending code finds a template.
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            // Mason brick JSON for the email body (header/footer come from the layout).
            $table->json('content')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
