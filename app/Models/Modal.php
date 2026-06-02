<?php

namespace App\Models;

use Database\Factories\ModalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modal extends Model
{
    /** @use HasFactory<ModalFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'content',
        'trigger',
        'trigger_seconds',
        'frequency',
        'visible',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'trigger_seconds' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
