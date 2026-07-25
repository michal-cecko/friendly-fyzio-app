<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Emails\SentEmailReceipt;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every e-mail the panel sends leaves the admin who sent it a durable receipt in
 * the bell menu, so the confirmation outlives the page they were on. Sends with
 * nobody behind them — cron, queue workers — report to nobody.
 */
class SentEmailReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);

        config(['mail.suppress_non_admin' => false]);
    }

    private function receiptCount(): int
    {
        return $this->admin->notifications()->count();
    }

    /**
     * Title and body of the newest receipt, as one searchable string.
     */
    private function latestReceiptText(): string
    {
        $data = $this->admin->notifications()->first()?->data ?? [];

        return trim(($data['title'] ?? '').' '.($data['body'] ?? ''));
    }

    /**
     * Queued sends file their receipt from a worker, so the bell is the only
     * place it shows up — it must not sit on Filament's 30s default.
     */
    public function test_the_bell_polls_often_enough_for_queued_receipts(): void
    {
        $this->assertSame('10s', Filament::getPanel('admin')->getDatabaseNotificationsPollingInterval());
    }

    public function test_a_send_leaves_the_acting_admin_a_receipt(): void
    {
        SentEmailReceipt::forCurrentUser('Faktura 2026-001');

        $this->assertSame(1, $this->receiptCount());
        $this->assertStringContainsString('Faktura 2026-001', $this->latestReceiptText());
    }

    public function test_the_receipt_counts_the_recipients(): void
    {
        SentEmailReceipt::forCurrentUser('E-mail účastníkům', 7);

        $this->assertStringContainsString('7 příjemců', $this->latestReceiptText());
    }

    /**
     * Before launch nothing actually reaches clients, so the receipt must say so
     * rather than claiming a delivery.
     */
    public function test_a_suppressed_send_reports_that_nothing_went_out(): void
    {
        config(['mail.suppress_non_admin' => true]);

        SentEmailReceipt::forCurrentUser('Pozvánka');

        $this->assertStringContainsString('pozastaveno', $this->latestReceiptText());
    }

    /**
     * The action that called this has already toasted, so the bell must not
     * announce the same send again on its next poll.
     */
    public function test_an_in_request_receipt_is_marked_already_announced(): void
    {
        SentEmailReceipt::forCurrentUser('Faktura 2026-001');

        $this->assertNotNull($this->admin->notifications()->latest()->first()->toasted_at);
    }

    /**
     * The queue has no session to toast into, so its receipt must still be
     * announced when the bell next polls.
     */
    public function test_a_queued_receipt_is_left_for_the_bell_to_announce(): void
    {
        SentEmailReceipt::recordForSender($this->admin, 'E-mail účastníkům', 12);

        $this->assertNull($this->admin->notifications()->latest()->first()->toasted_at);
    }

    public function test_a_send_with_nobody_behind_it_reports_to_nobody(): void
    {
        Auth::logout();

        SentEmailReceipt::forCurrentUser('Automatická připomínka');

        $this->assertSame(0, $this->receiptCount());
    }

    /**
     * End to end through a real action rather than the helper alone.
     */
    public function test_sending_an_email_from_a_record_files_a_receipt(): void
    {
        $reservation = Reservation::factory()->create();

        Livewire::test(ViewReservation::class, ['record' => $reservation->getKey()])
            ->callAction('sendEmail', [
                'mode' => 'custom',
                'recipient' => 'klient@example.com',
                'subject' => 'Změna termínu',
                'body' => '<p>Dobrý den,</p>',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $this->receiptCount());
    }
}
