<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\Actions\ScheduleChangeNotificationPrompt;
use Filament\Actions\Action;

/**
 * Shared behaviour for the Edit pages whose record has a termín (a course/one-off
 * lesson, a reservation): when staff change its date, time, room or who runs it, the
 * page saves normally and then opens the {@see ScheduleChangeNotificationPrompt} modal
 * asking whether to e-mail the affected people — replacing the old always-visible
 * "notify?" form toggle. Nothing is sent for an unrelated edit, and nothing is sent
 * unless staff confirm.
 *
 * The pre-edit values are snapshotted before the save and handed to the modal as mount
 * arguments (they must survive the extra round-trip), so the change e-mail can show the
 * puvodni_* tokens next to the new ones.
 *
 * The using class must be a Filament EditRecord and implement {@see scheduleAttributes()}
 * and {@see sendScheduleChangeNotification()}.
 */
trait PromptsScheduleChangeNotification
{
    /** @var array<string, string> */
    protected array $scheduleSnapshot = [];

    /**
     * The columns whose change counts as a termín change (date, times, room, who runs it).
     *
     * @return array<int, string>
     */
    abstract protected function scheduleAttributes(): array;

    /**
     * Send the change e-mails and return how many recipients were notified.
     *
     * @param  array<string, string>  $snapshot  The pre-edit puvodni_* tokens.
     */
    abstract protected function sendScheduleChangeNotification(?string $reason, array $snapshot): int;

    /**
     * The pre-edit snapshot passed to the change e-mail as puvodni_* tokens. Captured
     * in {@see beforeSave()} while the stored values are still intact.
     *
     * @return array<string, string>
     */
    protected function captureScheduleSnapshot(): array
    {
        return [];
    }

    /**
     * Who the change e-mail reaches, phrased for the prompt ("informovat … e-mailem?").
     */
    protected function scheduleChangeAudience(): string
    {
        return 'účastníky';
    }

    /**
     * Per-page work to run on every save regardless of the notification (e.g. an
     * activity-log entry). Runs before the prompt is (maybe) mounted.
     */
    protected function afterRecordSaved(): void {}

    /**
     * Where to send the user once the prompt is resolved by sending the e-mail. Null
     * keeps them on the edit page. The redirect is deferred until then because it would
     * otherwise drop the mounted modal.
     */
    protected function scheduleChangeRedirectUrl(): ?string
    {
        return null;
    }

    protected function beforeSave(): void
    {
        $this->scheduleSnapshot = $this->captureScheduleSnapshot();
    }

    protected function afterSave(): void
    {
        $this->afterRecordSaved();

        if ($this->getRecord()->wasChanged($this->scheduleAttributes())) {
            $this->mountAction('scheduleChangeNotification', ['snapshot' => $this->scheduleSnapshot]);
        }
    }

    /**
     * Stay on the page while the notify prompt is pending — a redirect here would drop
     * the modal mounted in {@see afterSave()}. Once the termín is unchanged (or the
     * prompt has redirected itself), fall back to the page's normal destination.
     */
    protected function getRedirectUrl(): ?string
    {
        if ($this->getRecord()->wasChanged($this->scheduleAttributes())) {
            return null;
        }

        return $this->scheduleChangeRedirectUrl();
    }

    public function scheduleChangeNotificationAction(): Action
    {
        $action = ScheduleChangeNotificationPrompt::make(
            'scheduleChangeNotification',
            $this->scheduleChangeAudience(),
            fn (?string $reason, array $arguments): int => $this->sendScheduleChangeNotification(
                $reason,
                is_array($arguments['snapshot'] ?? null) ? $arguments['snapshot'] : [],
            ),
        );

        $redirectUrl = $this->scheduleChangeRedirectUrl();

        if ($redirectUrl !== null) {
            $action->after(fn () => $this->redirect($redirectUrl, navigate: true));
        }

        return $action;
    }
}
