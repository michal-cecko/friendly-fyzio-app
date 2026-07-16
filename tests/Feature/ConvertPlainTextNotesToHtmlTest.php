<?php

namespace Tests\Feature;

use App\Models\ClientNote;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertPlainTextNotesToHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_text_notes_are_converted_to_html_and_existing_html_is_untouched(): void
    {
        $plainNote = ClientNote::factory()->create(['content' => "První odstavec\n\nDruhý odstavec"]);
        $htmlNote = ClientNote::factory()->create(['content' => '<p>Už je HTML</p>']);
        $plainReservation = Reservation::factory()->create(['notes' => "Bolest zad\npřijde dřív"]);
        $emptyReservation = Reservation::factory()->create(['notes' => null]);

        $migration = require database_path('migrations/2026_07_14_090100_convert_plain_text_notes_to_html.php');
        $migration->up();

        $this->assertSame('<p>První odstavec</p><p>Druhý odstavec</p>', $plainNote->fresh()->content);
        $this->assertSame('<p>Už je HTML</p>', $htmlNote->fresh()->content);
        $this->assertSame("<p>Bolest zad<br>\npřijde dřív</p>", $plainReservation->fresh()->notes);
        $this->assertNull($emptyReservation->fresh()->notes);
    }
}
