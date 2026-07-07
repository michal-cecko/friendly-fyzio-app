<?php

namespace App\Notifications;

use App\Models\ReviewRequest;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ReviewRequest $reviewRequest,
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

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Dobrý den,')
            ->line('rádi bychom vás poprosili o recenzi na '.$this->reviewRequest->targetLabel().'.')
            ->line($intro)
            ->action('Napsat recenzi', $this->reviewRequest->formUrl())
            ->line('Zabere to jen chvilku, děkujeme!');
    }
}
