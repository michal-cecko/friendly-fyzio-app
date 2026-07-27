<?php

namespace App\Models;

use App\Enums\ReservationDocumentType;
use App\Support\Reservations\ReservationDocuments;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * A file attached to a reservation — today the client's doctor's note backing a
 * late cancellation. The bytes live on the private disk (never `public`, never
 * the media library); this row records where and what.
 *
 * Writes go through {@see ReservationDocuments}, which
 * is shared by the client zone, the signed magic link and the admin panel.
 */
class ReservationDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'reservation_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReservationDocumentType::class,
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The row is the only reference to the file, so the bytes go with it —
        // including when a pruned reservation cascade-deletes its documents.
        static::deleted(function (self $document): void {
            Storage::disk($document->disk)->delete($document->path);
        });
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * The staff member or client who uploaded the file; null for a guest upload
     * through the signed magic link.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Human file size for the client and admin listings ("1,2 MB").
     */
    public function sizeForHumans(): string
    {
        return (string) Number::fileSize($this->size, precision: 1);
    }

    public function downloadUrl(): string
    {
        return route('reservation.document.download', ['document' => $this->getKey()]);
    }
}
