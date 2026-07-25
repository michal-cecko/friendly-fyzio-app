<?php

namespace Tests\Feature;

use App\Filament\Livewire\DatabaseNotifications;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Without websockets the bell's own poll is what announces new notifications.
 * Each one pops as a short toast exactly once, then stays in the list — and
 * dismissing a toast must never throw the notification away.
 */
class NotificationToastsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    private function fileNotification(?User $recipient = null, string $title = 'Nová zpráva'): void
    {
        Notification::make()
            ->title($title)
            ->body('Tělo zprávy.')
            ->success()
            ->sendToDatabase($recipient ?? $this->admin);
    }

    private function latestRow(?User $of = null): object
    {
        return ($of ?? $this->admin)->notifications()->latest()->first();
    }

    public function test_a_new_notification_is_announced_and_marked_announced(): void
    {
        $this->fileNotification();

        $this->assertNull($this->latestRow()->toasted_at);

        Livewire::test(DatabaseNotifications::class)
            ->assertNotified('Nová zpráva');

        $this->assertNotNull($this->latestRow()->toasted_at);
    }

    public function test_a_notification_is_announced_only_once(): void
    {
        $this->fileNotification();

        Livewire::test(DatabaseNotifications::class)->assertNotified('Nová zpráva');

        // A second poll finds nothing left to say.
        Livewire::test(DatabaseNotifications::class)->assertNotNotified('Nová zpráva');
    }

    /**
     * Receipts filed by an action that already toasted are stamped on creation,
     * so the bell stays quiet about them.
     */
    public function test_an_already_announced_notification_is_skipped(): void
    {
        $this->fileNotification();
        $this->admin->notifications()->update(['toasted_at' => now()]);

        Livewire::test(DatabaseNotifications::class)
            ->assertNotNotified('Nová zpráva');
    }

    public function test_another_users_notifications_are_left_alone(): void
    {
        $other = User::factory()->admin()->create();
        $this->fileNotification($other, 'Cizí zpráva');

        Livewire::test(DatabaseNotifications::class)
            ->assertNotNotified('Cizí zpráva');

        $this->assertNull($this->latestRow($other)->toasted_at);
    }

    /**
     * The toast keeps the row id so its mark-as-read button can find the row,
     * and must fade rather than inherit the stored "persistent" duration.
     */
    public function test_the_toast_keeps_the_row_id_and_fades(): void
    {
        $this->fileNotification();
        $row = $this->latestRow();

        Livewire::test(DatabaseNotifications::class);

        $toast = collect(session()->get('filament.notifications', []))
            ->first(fn (array $item): bool => ($item['title'] ?? null) === 'Nová zpráva');

        $this->assertNotNull($toast, 'The notification was not pushed as a toast.');
        $this->assertSame($row->getKey(), $toast['id']);
        $this->assertSame(5000, $toast['duration']);
        $this->assertContains('markAsRead', array_column($toast['actions'] ?? [], 'name'));
    }

    /**
     * Dismissing a toast — including the automatic fade — must not discard the
     * notification. Filament's default deletes the row here.
     */
    public function test_dismissing_a_toast_keeps_the_notification(): void
    {
        $this->fileNotification();
        $row = $this->latestRow();

        Livewire::test(DatabaseNotifications::class)
            ->dispatch('notificationClosed', id: $row->getKey());

        $this->assertDatabaseHas('notifications', ['id' => $row->getKey()]);
    }

    public function test_marking_as_read_keeps_the_notification_in_the_list(): void
    {
        $this->fileNotification();
        $row = $this->latestRow();

        Livewire::test(DatabaseNotifications::class)
            ->dispatch('markedNotificationAsRead', id: $row->getKey());

        $this->assertDatabaseHas('notifications', ['id' => $row->getKey()]);
        $this->assertNotNull($this->latestRow()->read_at);
    }

    public function test_clearing_is_what_removes_notifications(): void
    {
        $this->fileNotification();

        Livewire::test(DatabaseNotifications::class)
            ->call('clearNotifications');

        $this->assertSame(0, $this->admin->notifications()->count());
    }
}
