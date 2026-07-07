<?php

namespace App\Notifications;

use App\Models\CourseSeries;
use App\Models\OneTimeLesson;
use App\Models\Reservation;
use App\Models\Workshop;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Model $reviewable,
        public string $questionnaireUrl,
        public ?string $customIntro = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = Settings::get('reviews.email_subject', 'Jak jste byli spokojeni?');
        $intro = filled($this->customIntro)
            ? $this->customIntro
            : Settings::get('reviews.email_intro', 'Budeme moc rádi, když nám zanecháte krátkou recenzi.');
        $label = $this->reviewableLabel();

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Dobrý den,')
            ->when($label, fn (MailMessage $mail): MailMessage => $mail->line("děkujeme, že jste byli součástí „{$label}“."))
            ->line($intro)
            ->action('Vyplnit dotazník', $this->questionnaireUrl)
            ->line('Děkujeme, že jste si našli čas.');
    }

    /**
     * Human-readable name of the reviewed event, used in the e-mail body.
     */
    private function reviewableLabel(): ?string
    {
        return match (true) {
            $this->reviewable instanceof Workshop => $this->reviewable->name,
            $this->reviewable instanceof CourseSeries => $this->reviewable->course?->name,
            $this->reviewable instanceof OneTimeLesson => $this->reviewable->course?->name,
            $this->reviewable instanceof Reservation => $this->reviewable->service?->name,
            default => null,
        };
    }
}
