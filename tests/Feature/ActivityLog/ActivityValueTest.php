<?php

namespace Tests\Feature\ActivityLog;

use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Models\Page;
use App\Models\User;
use App\Support\ActivityLog\ActivityPresenter;
use App\Support\ActivityLog\ActivityValue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The activity log stores raw model state, so anything structured (page content,
 * billing snapshots, settings config) or rich-text must be rendered as prose —
 * never as a JSON blob or an HTML fragment.
 */
class ActivityValueTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array<string, mixed>> */
    private function masonContent(): array
    {
        return [
            ['type' => 'masonBrick', 'attrs' => ['id' => 'team', 'config' => ['title' => 'Seznamte se s týmem', 'columns' => 4]]],
            ['type' => 'masonBrick', 'attrs' => ['id' => 'photo-text', 'config' => ['title' => 'Mgr. Jakub Trepáč']]],
        ];
    }

    public function test_page_content_is_summarised_as_named_blocks(): void
    {
        $summary = ActivityValue::inline($this->masonContent(), 'content');

        $this->assertStringNotContainsString('{', $summary);
        $this->assertStringContainsString('2 bloky', $summary);
        $this->assertStringContainsString('Náš tým', $summary);
    }

    public function test_page_content_stored_as_a_json_string_is_decoded_first(): void
    {
        $summary = ActivityValue::inline(json_encode($this->masonContent()), 'content');

        $this->assertStringNotContainsString('masonBrick', $summary);
        $this->assertStringContainsString('2 bloky', $summary);
    }

    public function test_page_content_detail_lists_one_row_per_block(): void
    {
        $rows = ActivityValue::rows($this->masonContent(), 'content');

        $this->assertCount(2, $rows);
        $this->assertSame('1. Náš tým', $rows[0]['label']);
        $this->assertStringContainsString('Seznamte se s týmem', $rows[0]['value']);
        $this->assertStringNotContainsString('{', $rows[0]['value']);
    }

    public function test_brick_config_keys_are_named_by_the_bricks_own_form(): void
    {
        $rows = ActivityValue::rows($this->masonContent(), 'content');

        // TeamBrick labels these "Nadpis" and "Počet sloupců" — not the generic
        // "Název"/"Sloupce" a hand-written dictionary would guess.
        $this->assertStringContainsString('Nadpis: Seznamte se s týmem', $rows[0]['value']);
        $this->assertStringContainsString('Počet sloupců: 4', $rows[0]['value']);
    }

    public function test_repeaters_nested_inside_a_brick_are_named_too(): void
    {
        $content = [[
            'type' => 'masonBrick',
            'attrs' => ['id' => 'steps', 'config' => [
                'title' => 'Jak to probíhá',
                'steps' => [['icon' => 'phone', 'title' => 'Objednání', 'description' => '<p>Zavolejte</p>']],
            ]],
        ]];

        $value = ActivityValue::rows($content, 'content')[0]['value'];

        $this->assertStringContainsString('Kroky:', $value);
        $this->assertStringContainsString('Ikona: phone', $value);
        $this->assertStringContainsString('Popis: Zavolejte', $value);
    }

    public function test_model_repeater_rows_are_named_by_the_resource_schema(): void
    {
        $rows = ActivityValue::rows(
            [['degree' => 'Mgr. fyzioterapie', 'institution' => 'UP Olomouc', 'period' => '2010 – 2013']],
            'education',
        );

        $this->assertStringContainsString('Titul / obor: Mgr. fyzioterapie', $rows[0]['value']);
        $this->assertStringContainsString('Instituce: UP Olomouc', $rows[0]['value']);
        $this->assertStringContainsString('Období: 2010 – 2013', $rows[0]['value']);
    }

    public function test_a_column_is_named_by_its_own_resource_form(): void
    {
        Filament::setCurrentPanel('admin');

        $page = Page::factory()->create();

        $activity = Activity::query()
            ->where('subject_id', $page->getKey())
            ->latest('id')
            ->firstOrFail();

        // PageResource calls the column "Obsah stránky"; the generic fallback is "Obsah".
        $this->assertSame('Obsah stránky', ActivityPresenter::attributeLabel('content', ActivityPresenter::attributeScope($activity)));
        $this->assertSame('Obsah', ActivityPresenter::attributeLabel('content'));
    }

    public function test_every_attribute_key_resolves_to_a_czech_label(): void
    {
        Filament::setCurrentPanel('admin');

        $keys = ['settled_at', 'doctor_note_requested_at', 'waitlistable_type', 'variable_symbol', 'title_before', 'week_type'];

        foreach ($keys as $key) {
            $label = ActivityPresenter::attributeLabel($key);

            // The untranslated fallback is the key with underscores swapped for spaces.
            $this->assertNotSame(ucfirst(str_replace('_', ' ', $key)), $label, "[{$key}] fell through to the raw fallback");
        }
    }

    public function test_associative_structures_render_as_labelled_pairs(): void
    {
        $snapshot = ['name' => 'Fit Office s.r.o.', 'ico' => '19283746', 'dic' => 'CZ19283746'];

        $this->assertSame(
            'Název: Fit Office s.r.o. · IČO: 19283746 · DIČ: CZ19283746',
            ActivityValue::inline($snapshot, 'client_snapshot'),
        );

        $rows = ActivityValue::rows($snapshot, 'client_snapshot');

        $this->assertSame('Název', $rows[0]['label']);
        $this->assertSame('Fit Office s.r.o.', $rows[0]['value']);
    }

    public function test_rich_text_is_reduced_to_plain_text(): void
    {
        $this->assertSame(
            'Ahoj světe Druhý odstavec & konec',
            ActivityValue::inline('<p>Ahoj <strong>světe</strong></p><p>Druhý odstavec &amp; konec</p>', 'bio'),
        );
    }

    public function test_scalars_and_empties_keep_their_plain_rendering(): void
    {
        $this->assertSame('prázdné', ActivityValue::inline(null));
        $this->assertSame('prázdné', ActivityValue::inline([]));
        $this->assertSame('Ano', ActivityValue::inline(true));
        $this->assertSame('Ne', ActivityValue::inline(false));
        $this->assertSame('a, b, c', ActivityValue::inline(['a', 'b', 'c']));
        // Braces that are not JSON (an invoice number format) stay untouched.
        $this->assertSame('{PREFIX}-{YEAR}-{SEQ}', ActivityValue::inline('{PREFIX}-{YEAR}-{SEQ}', 'format'));
    }

    public function test_long_structures_name_the_first_entries_and_count_the_rest(): void
    {
        $summary = ActivityValue::inline(array_map(
            fn (int $i): array => ['type' => 'masonBrick', 'attrs' => ['id' => 'rich-text', 'config' => []]],
            range(1, 9),
        ), 'content');

        $this->assertStringContainsString('9 bloků', $summary);
        $this->assertStringContainsString('+5 dalších', $summary);
    }

    public function test_summary_of_a_content_change_points_at_the_diff_when_both_sides_read_alike(): void
    {
        $page = Page::factory()->create(['title' => 'O nás']);

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'O nás',
            'subject_type' => $page::class,
            'subject_id' => $page->getKey(),
            'event' => 'updated',
            'attribute_changes' => [
                'attributes' => ['content' => $this->masonContent()],
                'old' => ['content' => $this->masonContent()],
            ],
        ]);

        $this->assertStringEndsWith('Obsah stránky – změněno', ActivityPresenter::summary($activity));
    }

    public function test_detail_page_renders_block_names_instead_of_the_raw_json(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $page = Page::factory()->create(['title' => 'O nás']);
        $page->update(['content' => $this->masonContent()]);

        $activity = Activity::query()
            ->where('subject_id', $page->getKey())
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->get(ActivityLogResource::getUrl('view', ['record' => $activity]))
            ->assertSuccessful()
            ->assertSee('1. Náš tým')
            ->assertSee('Seznamte se s týmem')
            ->assertDontSee('masonBrick');
    }

    public function test_user_interface_preferences_are_not_audited(): void
    {
        $user = User::factory()->create();
        $before = Activity::query()->count();

        $user->setPreference('reservations.show_stats', false);

        $this->assertSame($before, Activity::query()->count());
    }
}
