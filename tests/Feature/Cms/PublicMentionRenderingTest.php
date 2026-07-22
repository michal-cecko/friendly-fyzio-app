<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMentionRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function mention(string $id, string $label): string
    {
        return '<span data-type="mention" data-id="'.$id.'" data-label="'.e($label).'" data-char="@">@'.e($label).'</span>';
    }

    private function createPageMentioning(string $id, string $label): Page
    {
        return Page::factory()->create([
            'slug' => 'testovaci-stranka',
            'content' => [
                [
                    'type' => 'masonBrick',
                    'attrs' => [
                        'id' => 'rich-text',
                        'config' => ['content' => '<p>O vás pečuje '.$this->mention($id, $label).' osobně.</p>'],
                    ],
                ],
            ],
        ]);
    }

    public function test_mention_of_user_with_published_profile_links_to_the_public_profile(): void
    {
        $therapist = User::factory()->create(['name' => 'Jana Terapeutka']);
        StaffProfile::factory()->published()->create([
            'user_id' => $therapist->id,
            'slug' => 'jana-terapeutka',
        ]);

        $this->createPageMentioning($therapist->id, 'Jana Terapeutka');

        $this->get('/testovaci-stranka')
            ->assertOk()
            ->assertSee('<a href="'.route('therapist.show', 'jana-terapeutka').'">Jana Terapeutka</a>', false);
    }

    public function test_mention_of_user_without_published_profile_renders_a_plain_name(): void
    {
        $therapist = User::factory()->create(['name' => 'Jana Terapeutka']);
        StaffProfile::factory()->unpublished()->create([
            'user_id' => $therapist->id,
            'slug' => 'jana-terapeutka',
        ]);

        $this->createPageMentioning($therapist->id, 'Jana Terapeutka');

        $this->get('/testovaci-stranka')
            ->assertOk()
            ->assertSee('O vás pečuje Jana Terapeutka osobně.', false)
            ->assertDontSee('/o-nas/jana-terapeutka')
            ->assertDontSee('data-type="mention"', false);
    }

    public function test_mention_of_deleted_user_falls_back_to_the_stored_label(): void
    {
        $this->createPageMentioning('01900000-0000-0000-0000-000000000000', 'Bývalá Kolegyně');

        $this->get('/testovaci-stranka')
            ->assertOk()
            ->assertSee('O vás pečuje Bývalá Kolegyně osobně.', false)
            ->assertDontSee('/o-nas/');
    }
}
