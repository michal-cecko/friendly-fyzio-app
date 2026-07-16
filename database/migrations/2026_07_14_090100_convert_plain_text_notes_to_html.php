<?php

use App\Support\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Client-note and reservation-note columns are now edited by a RichEditor,
     * so convert the existing plain Textarea text to equivalent HTML. Query
     * builder updates keep model observers (mention notifications) out of it.
     */
    public function up(): void
    {
        foreach ([['client_notes', 'content'], ['reservations', 'notes']] as [$table, $column]) {
            DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->where($column, 'not like', '<%')
                ->lazyById(200)
                ->each(function (object $row) use ($table, $column): void {
                    DB::table($table)->where('id', $row->id)->update([
                        $column => RichText::fromPlainText($row->{$column}),
                    ]);
                });
        }
    }

    /**
     * Data conversion is not reversed.
     */
    public function down(): void
    {
        //
    }
};
