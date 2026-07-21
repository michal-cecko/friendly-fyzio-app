<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\StaffProfile;
use App\Support\MediaLibrary\MediaUsageScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;
use Tests\TestCase;

class MediaLibraryDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(): MediaLibraryItem
    {
        return MediaLibraryItem::query()->create([]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function brick(array $config): array
    {
        return ['type' => 'masonBrick', 'attrs' => ['id' => 'hero', 'config' => $config]];
    }

    public function test_item_used_as_a_brick_image_cannot_be_deleted(): void
    {
        $item = $this->makeItem();

        Page::factory()->create([
            'title' => 'Domů',
            'content' => [$this->brick(['title' => 'Vítejte', 'image' => $item->getKey()])],
        ]);

        $this->assertFalse($item->delete());
        $this->assertDatabaseHas('filament_media_library', ['id' => $item->getKey()]);
        $this->assertSame(['Stránka „Domů“'], MediaUsageScanner::usagesOf((int) $item->getKey()));
    }

    public function test_item_used_inside_wysiwyg_html_cannot_be_deleted(): void
    {
        $item = $this->makeItem();

        Page::factory()->create([
            'content' => [$this->brick([
                'body' => '<p>text</p><img data-id="'.$item->getKey().':800" src="" alt="">',
            ])],
        ]);

        $this->assertFalse($item->delete());
        $this->assertDatabaseHas('filament_media_library', ['id' => $item->getKey()]);
    }

    public function test_item_used_as_a_therapist_photo_cannot_be_deleted(): void
    {
        $item = $this->makeItem();

        StaffProfile::factory()->create(['photo' => (string) $item->getKey()]);

        $this->assertFalse($item->delete());
        $this->assertDatabaseHas('filament_media_library', ['id' => $item->getKey()]);
    }

    public function test_unreferenced_item_can_be_deleted_and_numbers_elsewhere_do_not_collide(): void
    {
        $item = $this->makeItem();

        // The same number under a non-image key or inside plain text is not a usage.
        Page::factory()->create([
            'content' => [$this->brick([
                'columns' => $item->getKey(),
                'title' => '<p>Otevřeno od '.$item->getKey().' hodin</p>',
            ])],
        ]);

        $this->assertSame([], MediaUsageScanner::usagesOf((int) $item->getKey()));
        $this->assertTrue($item->delete());
        $this->assertDatabaseMissing('filament_media_library', ['id' => $item->getKey()]);
    }
}
