<?php

namespace App\Models;

use Database\Factories\PageBlockFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageBlock extends Model
{
    /** @use HasFactory<PageBlockFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'page_id',
        'type',
        'display_order',
        'visible',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
