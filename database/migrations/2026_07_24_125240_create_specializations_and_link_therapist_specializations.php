<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // The shared specialization catalog, defined under a service (grouping).
        // A null service_id means the entry is not (yet) grouped under a service.
        Schema::create('specializations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // A therapist's specialization becomes a thin link to the catalog entry.
        Schema::table('therapist_specializations', function (Blueprint $table) {
            $table->foreignUuid('specialization_id')->nullable()->after('therapist_id')
                ->constrained('specializations')->cascadeOnDelete();
        });

        // Backfill: fold existing per-therapist rows into a deduped catalog
        // (case-insensitive by name, keeping the first icon/description seen),
        // then link every row to its catalog entry.
        $catalog = [];

        foreach (DB::table('therapist_specializations')->get() as $row) {
            $key = Str::lower(trim($row->name));

            if (! isset($catalog[$key])) {
                $id = (string) Str::uuid();

                DB::table('specializations')->insert([
                    'id' => $id,
                    'service_id' => null,
                    'name' => $row->name,
                    'icon' => $row->icon ?? null,
                    'description' => $row->description ?? null,
                    'display_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $catalog[$key] = $id;
            }

            DB::table('therapist_specializations')
                ->where('id', $row->id)
                ->update(['specialization_id' => $catalog[$key]]);
        }

        // The definition now lives on the catalog entry.
        Schema::table('therapist_specializations', function (Blueprint $table) {
            $table->dropColumn(['name', 'icon', 'description']);
        });
    }

    public function down(): void
    {
        Schema::table('therapist_specializations', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
        });

        // Restore the denormalized values from the catalog before unlinking.
        foreach (DB::table('therapist_specializations')->whereNotNull('specialization_id')->get() as $row) {
            $specialization = DB::table('specializations')->where('id', $row->specialization_id)->first();

            if ($specialization !== null) {
                DB::table('therapist_specializations')->where('id', $row->id)->update([
                    'name' => $specialization->name,
                    'icon' => $specialization->icon,
                    'description' => $specialization->description,
                ]);
            }
        }

        Schema::table('therapist_specializations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialization_id');
        });

        Schema::dropIfExists('specializations');
    }
};
