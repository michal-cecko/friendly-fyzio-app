<?php

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use App\Models\ReviewRequest;
use App\Notifications\Concerns\HasCopyRecipients;
use App\Support\CmsMail;
use App\Support\Emails\CopyRecipients;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Asks a client to leave a review, rendered from the dashboard-editable "review_request"
 * CMS template. The subject stays driven by the reviews.email_subject setting; the intro
 * paragraph is the per-send override (SendReviewRequestAction) or the reviews.email_intro
 * setting, exposed to the template as the {{ intro }} token.
 */
class ReviewRequestNotification extends Notification
{
    use HasCopyRecipients, Queueable;

    public function __construct(
        public ReviewRequest $reviewRequest,
        public ?string $customIntro = null,
        public ?CopyRecipients $copies = null,
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
        $intro = filled($this->customIntro)
            ? $this->customIntro
            : Settings::get('reviews.email_intro', 'Budeme moc rádi, když nám zanecháte krátkou recenzi.');

        $template = EmailTemplate::forKey(EmailTemplateKey::ReviewRequest);

        if ($template === null) {
            return $this->applyCopies((new MailMessage)
                ->subject(Settings::get('reviews.email_subject', 'Jak jste byli spokojeni?'))
                ->greeting('Dobrý den,')
                ->line('rádi bychom vás poprosili o recenzi na '.$this->reviewRequest->targetLabel().'.')
                ->line($intro)
                ->action('Napsat recenzi', $this->reviewRequest->formUrl())
                ->line('Zabere to jen chvilku, děkujeme!'));
        }

        $name = (string) ($notifiable->name ?? '');

        return $this->applyCopies(CmsMail::render($template, [
            'jmeno' => Str::of($name)->before(' ')->toString() ?: $name,
            'cil' => $this->reviewRequest->targetLabel(),
            'intro' => $intro,
            'odkaz' => $this->reviewRequest->formUrl(),
        ])->subject(Settings::get('reviews.email_subject', $template->subject)));
    }
}
