<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ListCourseSeries;
use App\Filament\Support\Tables\OccupancyColumn;
use App\Models\CourseSeries;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The occupancy ring reads as free spots out of the total, and its colour tracks
 * how full the offer is: comfortable up to 40 % taken, filling up to 80 %, tight
 * beyond that.
 */
class OccupancyColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function tones(): array
    {
        return [
            'empty offer is comfortable' => [20, 0, 'comfortable'],
            'just under the comfortable edge' => [20, 7, 'comfortable'],
            'exactly 40 % taken is still comfortable' => [20, 8, 'comfortable'],
            'past 40 % taken starts filling' => [20, 9, 'filling'],
            'exactly 80 % taken is still filling' => [20, 16, 'filling'],
            'past 80 % taken is tight' => [20, 17, 'tight'],
            'full is tight' => [20, 20, 'tight'],
        ];
    }

    #[DataProvider('tones')]
    public function test_tone_tracks_how_full_the_offer_is(int $capacity, int $taken, string $expected): void
    {
        $series = CourseSeries::factory()->make(['capacity' => $capacity]);
        $series->setAttribute('active_takers_count', $taken);

        $state = OccupancyColumn::state($series);

        $this->assertSame($expected, $state['tone']);
        $this->assertSame($capacity - $taken, $state['free']);
        $this->assertSame($capacity, $state['capacity']);
    }

    public function test_state_survives_a_capacity_of_zero(): void
    {
        $series = CourseSeries::factory()->make(['capacity' => 0]);
        $series->setAttribute('active_takers_count', 0);

        $state = OccupancyColumn::state($series);

        $this->assertSame(0, $state['percent']);
        $this->assertSame('empty', $state['tone']);
    }

    public function test_column_renders_free_spots_out_of_total(): void
    {
        $series = CourseSeries::factory()->create(['capacity' => 20]);

        Livewire::test(ListCourseSeries::class)
            ->assertCanSeeTableRecords([$series])
            ->assertSee('20/20');
    }
}
