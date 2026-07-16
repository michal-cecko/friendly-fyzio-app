<?php

namespace App\Models\Concerns;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Default Payable implementation shared by the billable models. Assumes the
 * model has `payment_status` (+ `paid_at`, except Reservation which overrides).
 */
trait IsPayable
{
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function invoice(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }

    public function hasPaidStatus(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function markPaymentPaid(): void
    {
        if ($this->hasPaidStatus()) {
            return;
        }

        $this->forceFill([
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => $this->paid_at ?? now(),
        ])->save();
    }

    /**
     * @return array{title: string, description: string}
     */
    public function invoiceItemTemplates(): array
    {
        $type = $this->payableType();

        return [
            'title' => (string) Settings::get($type->titleSettingKey(), $type->defaultTitleTemplate()),
            'description' => (string) Settings::get($type->descriptionSettingKey(), $type->defaultDescriptionTemplate()),
        ];
    }

    public function payableTherapist(): ?User
    {
        return null;
    }
}
