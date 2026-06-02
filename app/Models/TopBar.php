<?php

namespace App\Models;

use Database\Factories\TopBarFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopBar extends Model
{
    /** @use HasFactory<TopBarFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'content',
        'link_url',
        'background_color',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
        ];
    }
}
