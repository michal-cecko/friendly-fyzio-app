<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use App\Support\Clients\PlaceholderEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceholderEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $digits
     */
    protected function generatorWithDigits(array $digits): PlaceholderEmail
    {
        return new class($digits) extends PlaceholderEmail
        {
            /** @param list<string> $sequence */
            public function __construct(protected array $sequence) {}

            protected function digits(): string
            {
                return array_shift($this->sequence);
            }
        };
    }

    public function test_generates_slugged_placeholder_email(): void
    {
        $email = (new PlaceholderEmail)->generate('Anna', 'Nováková');

        $this->assertMatchesRegularExpression('/^anna\.novakova\d{4}@friendlyfyzio\.cz$/', $email);
    }

    public function test_retries_until_email_is_unique(): void
    {
        User::factory()->customer()->create(['email' => 'anna.novakova1111@friendlyfyzio.cz']);

        $email = $this->generatorWithDigits(['1111', '2222'])->generate('Anna', 'Nováková');

        $this->assertSame('anna.novakova2222@friendlyfyzio.cz', $email);
    }

    public function test_collision_check_includes_soft_deleted_users(): void
    {
        User::factory()->customer()->create(['email' => 'anna.novakova1111@friendlyfyzio.cz'])->delete();

        $email = $this->generatorWithDigits(['1111', '2222'])->generate('Anna', 'Nováková');

        $this->assertSame('anna.novakova2222@friendlyfyzio.cz', $email);
    }

    public function test_falls_back_when_names_slug_to_nothing(): void
    {
        $email = (new PlaceholderEmail)->generate('...', '—');

        $this->assertMatchesRegularExpression('/^klient\d{4}@friendlyfyzio\.cz$/', $email);
    }
}
