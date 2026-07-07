<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rating')->nullable()->after('reviewable_id');
            $table->string('author_role')->nullable()->after('author_name');
            $table->string('photo')->nullable()->after('author_role');
        });

        // Relax previously-required columns so staff can author general reviews
        // that aren't tied to a specific course/workshop and have no user account.
        // Each ->change() lives in its own closure so the SQLite test driver can
        // rebuild the table cleanly.
        Schema::table('reviews', function (Blueprint $table): void {
            $table->string('reviewable_type')->nullable()->change();
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->uuid('reviewable_id')->nullable()->change();
        });

        // Make client_id nullable and switch cascade → set-null on delete, so an
        // admin-authored/general review survives if its client is ever deleted.
        // On SQLite (tests) foreign keys live in the table definition; a plain
        // ->change() rebuild handles nullability, so we skip the explicit FK swap.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->uuid('client_id')->nullable()->change();
            });

            return;
        }

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->uuid('client_id')->nullable()->change();
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // The nullability relaxation is a forward-only fix: down() only removes the
        // added columns (and, outside SQLite, restores the original cascade FK).
        // Re-adding NOT NULL would fail if null rows already exist.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->dropColumn(['rating', 'author_role', 'photo']);
            });

            return;
        }

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
            $table->dropColumn(['rating', 'author_role', 'photo']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreign('client_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
