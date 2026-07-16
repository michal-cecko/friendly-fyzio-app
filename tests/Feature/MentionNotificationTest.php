<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\ServiceCategoryResource;
use App\Models\ClientNote;
use App\Models\Page;
use App\Models\Reservation;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Support\Mentions\StaffMentions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function mention(User $user): string
    {
        return '<span data-type="mention" data-id="'.$user->id.'" data-label="'.e($user->name).'" data-char="@">@'.e($user->name).'</span>';
    }

    public function test_mention_search_is_case_and_diacritics_insensitive_and_excludes_customers(): void
    {
        $lucie = User::factory()->therapist()->create(['name' => 'Mgr. Lucie Fičkerová']);
        $michal = User::factory()->admin()->create(['name' => 'Michal Čečko']);
        User::factory()->customer()->create(['name' => 'Lucie Zákaznická']);

        $this->assertSame([$lucie->id => 'Mgr. Lucie Fičkerová'], StaffMentions::searchUsers('lucie'));
        $this->assertSame([$michal->id => 'Michal Čečko'], StaffMentions::searchUsers('cec'));
        $this->assertSame([$michal->id => 'Michal Čečko'], StaffMentions::searchUsers('ČEČ'));
        $this->assertSame([], StaffMentions::searchUsers('zákaznická'));

        $this->assertSame(
            [$lucie->id => 'Mgr. Lucie Fičkerová', $michal->id => 'Michal Čečko'],
            StaffMentions::searchUsers(''),
        );
    }

    public function test_newly_mentioned_therapist_gets_a_database_notification(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Adminová']);
        $therapist = User::factory()->therapist()->create(['name' => 'Jana Terapeutka']);
        $client = User::factory()->customer()->create(['name' => 'Petr Klient']);

        $this->actingAs($admin);

        ClientNote::create([
            'client_id' => $client->id,
            'author_id' => $admin->id,
            'content' => '<p>Prosím '.$this->mention($therapist).' mrkni na cvik.</p>',
        ]);

        $this->assertSame(1, $therapist->notifications()->count());

        $data = $therapist->notifications()->first()->data;

        $this->assertSame('Admin Adminová vás zmínil/a v poznámce u klienta Petr Klient', $data['title']);
        $this->assertSame('Prosím @Jana Terapeutka mrkni na cvik.', $data['body']);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_note_added_from_a_reservation_links_to_the_reservation(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        $reservation = Reservation::factory()->create(['notes' => null]);

        $this->actingAs($admin);

        ClientNote::create([
            'client_id' => $reservation->client_id,
            'author_id' => $admin->id,
            'reservation_id' => $reservation->id,
            'content' => '<p>'.$this->mention($therapist).' viz terapie.</p>',
        ]);

        $actions = $therapist->notifications()->first()->data['actions'];

        $this->assertSame(
            ReservationResource::getUrl('view', ['record' => $reservation]),
            $actions[0]['url'],
        );
    }

    public function test_self_mention_and_customer_mention_do_not_notify(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->customer()->create();

        $this->actingAs($admin);

        ClientNote::create([
            'client_id' => $client->id,
            'author_id' => $admin->id,
            'content' => '<p>'.$this->mention($admin).' a '.$this->mention($client).'</p>',
        ]);

        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(0, $client->notifications()->count());
    }

    public function test_resaving_unchanged_content_does_not_notify_again_and_edits_notify_only_new_mentions(): void
    {
        $admin = User::factory()->admin()->create();
        $therapistA = User::factory()->therapist()->create();
        $therapistB = User::factory()->therapist()->create();
        $client = User::factory()->customer()->create();

        $this->actingAs($admin);

        $note = ClientNote::create([
            'client_id' => $client->id,
            'author_id' => $admin->id,
            'content' => '<p>'.$this->mention($therapistA).'</p>',
        ]);

        $note->save();
        $note->touch();

        $this->assertSame(1, $therapistA->notifications()->count());

        $note->update([
            'content' => '<p>'.$this->mention($therapistA).' a '.$this->mention($therapistB).'</p>',
        ]);

        $this->assertSame(1, $therapistA->notifications()->count());
        $this->assertSame(1, $therapistB->notifications()->count());
    }

    public function test_mention_in_reservation_notes_notifies_with_reservation_link(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Adminová']);
        $therapist = User::factory()->therapist()->create();
        $reservation = Reservation::factory()->create(['notes' => null]);

        $this->actingAs($admin);

        $reservation->update(['notes' => '<p>Domluvit s '.$this->mention($therapist).'</p>']);

        $this->assertSame(1, $therapist->notifications()->count());

        $data = $therapist->notifications()->first()->data;

        $this->assertStringContainsString('v poznámce k rezervaci klienta', $data['title']);
        $this->assertSame(
            ReservationResource::getUrl('view', ['record' => $reservation]),
            $data['actions'][0]['url'],
        );
    }

    public function test_plain_text_reservation_note_does_not_notify(): void
    {
        $therapist = User::factory()->therapist()->create();

        Reservation::factory()->create(['notes' => 'Bolest zad, přijde dřív. Kontakt: info@example.com']);

        $this->assertSame(0, $therapist->notifications()->count());
    }

    public function test_mention_in_page_brick_content_notifies_and_links_to_the_owner_edit_page(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        $category = ServiceCategory::factory()->create();

        $this->actingAs($admin);

        Page::factory()->create([
            'pageable_type' => $category->getMorphClass(),
            'pageable_id' => $category->id,
            'content' => [
                [
                    'type' => 'masonBrick',
                    'attrs' => [
                        'id' => 'callout',
                        'config' => ['title' => '<p>Náš tým: '.$this->mention($therapist).'</p>'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(1, $therapist->notifications()->count());

        $data = $therapist->notifications()->first()->data;

        $this->assertStringContainsString('v obsahu stránky', $data['title']);
        $this->assertSame(
            ServiceCategoryResource::getUrl('edit', ['record' => $category]),
            $data['actions'][0]['url'],
        );
    }

    public function test_moving_a_mention_between_bricks_is_not_a_new_mention(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();

        $this->actingAs($admin);

        $brick = fn (string $title, string $subtitle): array => [
            'type' => 'masonBrick',
            'attrs' => ['id' => 'callout', 'config' => ['title' => $title, 'subtitle' => $subtitle]],
        ];

        $page = Page::factory()->create([
            'content' => [$brick('<p>'.$this->mention($therapist).'</p>', '<p>text</p>')],
        ]);

        $this->assertSame(1, $therapist->notifications()->count());

        $page->update([
            'content' => [$brick('<p>text</p>', '<p>'.$this->mention($therapist).'</p>')],
        ]);

        $this->assertSame(1, $therapist->notifications()->count());
    }
}
