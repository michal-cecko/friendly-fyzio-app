<?php

namespace Database\Factories;

use App\Enums\EmailTemplateKey;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->randomElement(EmailTemplateKey::cases());

        return [
            'key' => $key->value,
            'name' => $key->label(),
            'subject' => $key->defaultSubject(),
            'content' => [],
        ];
    }

    public function forKey(EmailTemplateKey $key): static
    {
        return $this->state(fn (): array => [
            'key' => $key->value,
            'name' => $key->label(),
            'subject' => $key->defaultSubject(),
        ]);
    }
}
