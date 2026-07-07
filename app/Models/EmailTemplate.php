<?php

namespace App\Models;

use App\Enums\EmailTemplateKey;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A CMS-authored transactional email. The body is built from email Mason bricks
 * (see App\Mason\EmailBrickRegistry) and wrapped in the fixed emails.layout chrome
 * at render time. Records are a fixed, seeded set keyed by EmailTemplateKey.
 */
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'key',
        'name',
        'subject',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public static function forKey(EmailTemplateKey|string $key): ?self
    {
        return static::query()
            ->where('key', $key instanceof EmailTemplateKey ? $key->value : $key)
            ->first();
    }

    public function templateKey(): ?EmailTemplateKey
    {
        return EmailTemplateKey::tryFrom($this->key);
    }
}
