<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaLibraryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_staff_can_open_media_library(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/media-library')->assertSuccessful();
    }
}
