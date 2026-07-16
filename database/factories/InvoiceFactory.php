<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'series_id' => null,
            'invoice_number' => 'TST-'.fake()->unique()->numerify('#####'),
            'client_id' => User::factory()->customer(),
            'client_snapshot' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'phone' => null,
                'address' => fake()->streetAddress().', '.fake()->city(),
                'ico' => null,
                'dic' => null,
            ],
            'supplier_snapshot' => [
                'name' => 'FriendlyFyzio s.r.o.',
                'address' => 'Zednická 1109/2, Ostrava',
                'ico' => '06816967',
                'dic' => null,
                'vat_payer' => false,
                'email' => 'info@friendlyfyzio.cz',
                'phone' => '+420 604 793 255',
                'iban' => 'CZ6508000000192000145399',
                'bank_account' => '19-2000145399/0800',
                'registration' => null,
            ],
            'amount' => 0,
            'status' => InvoiceStatus::New,
            'payment_method' => PaymentMethod::Qr,
            'issued_at' => today(),
            'due_at' => today()->addDays(14),
            'variable_symbol' => (string) fake()->unique()->numberBetween(1000, 999999),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Sent,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Overdue,
            'issued_at' => today()->subDays(30),
            'due_at' => today()->subDays(16),
        ]);
    }
}
