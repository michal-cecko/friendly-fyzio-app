<?php

namespace App\Enums;

/**
 * The kinds of file a client (or staff member) may attach to a reservation.
 * Currently only the doctor's note that suspends a late-cancellation fee, but
 * the type column keeps the table open for further document kinds.
 */
enum ReservationDocumentType: string
{
    case DoctorNote = 'doctor_note';

    public function label(): string
    {
        return match ($this) {
            self::DoctorNote => 'Potvrzení od lékaře',
        };
    }

    /**
     * Extensions offered in the file picker and accepted by validation. Scans and
     * phone photos both count — HEIC is what an iPhone produces by default.
     *
     * @return array<int, string>
     */
    public function allowedExtensions(): array
    {
        return match ($this) {
            self::DoctorNote => ['pdf', 'jpg', 'jpeg', 'png', 'heic', 'heif', 'webp'],
        };
    }

    /**
     * Per-file size cap. Stays under Livewire's own 12 MB default so an oversized
     * file fails our (translated) rule rather than the framework's.
     */
    public function maxKilobytes(): int
    {
        return match ($this) {
            self::DoctorNote => 10240,
        };
    }

    /**
     * `accept` attribute for the file input, e.g. ".pdf,.jpg,…".
     */
    public function acceptAttribute(): string
    {
        return collect($this->allowedExtensions())
            ->map(fn (string $extension): string => '.'.$extension)
            ->implode(',');
    }

    /**
     * Human list of the accepted formats for helper text ("PDF, JPG, …").
     */
    public function formatsLabel(): string
    {
        return collect($this->allowedExtensions())
            ->map(fn (string $extension): string => strtoupper($extension))
            ->implode(', ');
    }

    /**
     * Validation rules for one uploaded file of this type.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        return [
            'file',
            'mimes:'.implode(',', $this->allowedExtensions()),
            'max:'.$this->maxKilobytes(),
        ];
    }
}
