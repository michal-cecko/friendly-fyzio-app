<?php

namespace App\Jobs;

use App\Contracts\Emailable;
use App\Enums\EmailTemplateKey;
use App\Models\User;
use App\Notifications\CustomEmailNotification;
use App\Support\Emails\CopyRecipients;
use App\Support\Emails\SentEmailReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Fans a single admin-composed message out to an offer's participants. The send
 * runs on the queue because a full course série is dozens of rendered e-mails,
 * far more than a web request should carry; the admin who started it gets the
 * final count as a database notification when the last one is away.
 *
 * Each recipient record is itself {@see Emailable}, so both modes reuse the
 * per-record machinery and every send lands on that record's activity log. Works
 * for any Emailable model — course enrollments, event bookings, or waitlist
 * entries — so the enrolled list and the waitlist share one fan-out job.
 */
class SendBulkParticipantEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  class-string<Model&Emailable>  $signupClass
     * @param  list<string>  $signupIds
     */
    public function __construct(
        public string $signupClass,
        public array $signupIds,
        public ?string $templateKey,
        public ?string $subject,
        public ?string $bodyHtml,
        public ?string $senderId,
        public ?CopyRecipients $copies = null,
    ) {}

    public function handle(): void
    {
        $sender = $this->senderId === null ? null : User::query()->find($this->senderId);
        $sent = 0;

        foreach ($this->signups() as $signup) {
            if (blank($signup->emailRecipientAddress())) {
                continue;
            }

            // A CC/BCC is the sender's single archive of this broadcast, not a
            // per-recipient copy — attach it to the first delivered message only,
            // so accounting or the archive gets one message instead of dozens.
            $this->deliver($signup, $sender, $sent === 0 ? $this->copies : null);

            $sent++;
        }

        SentEmailReceipt::recordForSender($sender, 'E-mail účastníkům', $sent);
    }

    /**
     * @return Collection<int, Model&Emailable>
     */
    private function signups(): Collection
    {
        return $this->signupClass::query()
            ->whereKey($this->signupIds)
            ->with('client')
            ->get();
    }

    private function deliver(Model&Emailable $signup, ?User $sender, ?CopyRecipients $copies): void
    {
        if ($this->templateKey !== null) {
            $signup->sendTemplateEmail(EmailTemplateKey::from($this->templateKey), $copies);

            return;
        }

        Notification::route('mail', $signup->emailRecipientAddress())
            ->notify(new CustomEmailNotification(
                record: $signup,
                emailSubject: (string) $this->subject,
                bodyHtml: (string) $this->bodyHtml,
                copies: $copies,
                replyToAddress: $sender?->email,
                replyToName: $sender?->name,
            ));
    }
}
