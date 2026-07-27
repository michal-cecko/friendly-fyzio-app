<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;
use Tests\TestCase;

class MediaLibraryOwnershipScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function itemUploadedBy(?User $user): MediaLibraryItem
    {
        $item = MediaLibraryItem::query()->create([]);

        if ($user) {
            $item->uploader()->associate($user)->save();
        }

        return $item;
    }

    /**
     * The has_media scope hides item rows with no attached media file; drop it
     * so these tests can assert ownership filtering without staging real files.
     *
     * @return Collection<int, int>
     */
    private function browsableIds(): Collection
    {
        return MediaLibraryItem::query()
            ->withoutGlobalScope('has_media')
            ->pluck('id');
    }

    public function test_therapist_only_browses_their_own_uploads(): void
    {
        $therapist = User::factory()->therapist()->create();
        $other = User::factory()->admin()->create();

        $mine = $this->itemUploadedBy($therapist);
        $theirs = $this->itemUploadedBy($other);
        $orphan = $this->itemUploadedBy(null);

        $this->actingAs($therapist);

        $ids = $this->browsableIds();

        $this->assertTrue($ids->contains($mine->getKey()));
        $this->assertFalse($ids->contains($theirs->getKey()));
        $this->assertFalse($ids->contains($orphan->getKey()));
    }

    public function test_admin_browses_every_upload(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();

        $adminItem = $this->itemUploadedBy($admin);
        $therapistItem = $this->itemUploadedBy($therapist);

        $this->actingAs($admin);

        $ids = $this->browsableIds();

        $this->assertTrue($ids->contains($adminItem->getKey()));
        $this->assertTrue($ids->contains($therapistItem->getKey()));
    }

    public function test_therapist_can_still_resolve_another_users_item_by_id(): void
    {
        $therapist = User::factory()->therapist()->create();
        $other = User::factory()->admin()->create();

        $theirs = $this->itemUploadedBy($other);

        $this->actingAs($therapist);

        // The exception: an already-saved value (resolved by key) keeps rendering
        // even though the therapist could not browse to it.
        $this->assertNotNull(
            MediaLibraryItem::query()
                ->withoutGlobalScope('has_media')
                ->find($theirs->getKey()),
        );
    }

    public function test_scope_does_not_apply_outside_the_admin_panel(): void
    {
        $therapist = User::factory()->therapist()->create();
        $other = User::factory()->admin()->create();

        $theirs = $this->itemUploadedBy($other);

        Filament::setCurrentPanel(null);
        $this->actingAs($therapist);

        $this->assertTrue($this->browsableIds()->contains($theirs->getKey()));
    }
}
