<?php

namespace App\Filament\Livewire;

use Filament\Actions\Action;
use Filament\Livewire\DatabaseNotifications as BaseComponent;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

/**
 * The notification bell, taught to announce what arrives in it.
 *
 * Filament only shows a toast for notifications raised in the current request
 * (or over websockets, which this app does not run). Anything filed by a queue
 * worker or by another admin therefore appeared in the bell in silence. This
 * component reuses the bell's own poll to pop those as short-lived toasts, and
 * keeps them in the list afterwards.
 */
class DatabaseNotifications extends BaseComponent
{
    /**
     * How long an announced notification stays on screen.
     */
    private const TOAST_SECONDS = 5;

    /**
     * How many to announce in one poll, so a long backlog (someone returning to
     * an open tab) does not bury the screen in toasts at once.
     */
    private const TOAST_BATCH = 3;

    public function render(): View
    {
        $this->toastNewNotifications();

        return parent::render();
    }

    /**
     * Filament deletes the underlying row when a notification is closed. Our
     * toasts carry the real row id (the mark-as-read button needs it to find
     * the row), so a toast fading out after five seconds would take the
     * notification with it. Dismissing is not discarding — only "Vymazat"
     * deletes, via the inherited clearNotifications().
     *
     * The listener is re-declared rather than dropped: PHP does not inherit the
     * parent's attribute onto an override, and an explicitly empty handler says
     * "we mean nothing to happen" where a missing one would look like a slip.
     */
    #[On('notificationClosed')]
    public function removeNotification(string $id): void {}

    /**
     * Announce whatever has arrived since the last poll, then mark it announced
     * so the next poll stays quiet.
     */
    protected function toastNewNotifications(): void
    {
        $pending = $this->pendingNotifications();

        if ($pending->isEmpty()) {
            return;
        }

        $pending->each(fn (DatabaseNotification $row) => $this->toast($row));

        // Stamped by key rather than by re-running the query: the rows are
        // already in hand, and the set must not drift between the two.
        DatabaseNotification::query()
            ->whereKey($pending->modelKeys())
            ->update(['toasted_at' => now()]);
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    protected function pendingNotifications(): Collection
    {
        if (! $this->getUser()) {
            return new Collection;
        }

        return $this->getNotificationsQuery()
            ->whereNull('toasted_at')
            ->oldest()
            ->limit(self::TOAST_BATCH)
            ->get();
    }

    protected function toast(DatabaseNotification $row): void
    {
        $notification = Notification::fromDatabase($row);

        $notification
            // Stored notifications are persisted as "persistent"; as a toast it
            // should behave like any other and fade on its own.
            ->seconds(self::TOAST_SECONDS)
            ->actions([
                ...$notification->getActions(),
                Action::make('markAsRead')
                    ->label('Označit jako přečtené')
                    ->markAsRead(),
            ])
            ->send();
    }
}
