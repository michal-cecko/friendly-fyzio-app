<?php

namespace App\Models;

use App\Support\Media;
use Database\Factories\InstagramPostFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;

class InstagramPost extends Model
{
    /** @use HasFactory<InstagramPostFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'instagram_connection_id',
        'instagram_media_id',
        'media_library_item_id',
        'caption',
        'permalink',
        'media_type',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(InstagramConnection::class, 'instagram_connection_id');
    }

    public function mediaLibraryItem(): BelongsTo
    {
        return $this->belongsTo(MediaLibraryItem::class, 'media_library_item_id');
    }

    /**
     * Public URL of the downloaded post image, for the requested conversion.
     */
    public function imageUrl(?string $conversion = '400'): ?string
    {
        return Media::url($this->media_library_item_id, $conversion);
    }
}
