<?php

namespace App\Models;

use App\Enums\ContactInquiryStatus;
use Database\Factories\ContactInquiryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A message submitted through the public Kontakt page contact form. Stored so the
 * clinic never loses an inquiry even if the notification e-mail fails; `status`
 * (nový → řeší se → vyřízeno) drives the handling workflow, and the count of
 * `nový` inquiries feeds the sidebar badge in the admin resource.
 */
class ContactInquiry extends Model
{
    /** @use HasFactory<ContactInquiryFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactInquiryStatus::class,
        ];
    }
}
