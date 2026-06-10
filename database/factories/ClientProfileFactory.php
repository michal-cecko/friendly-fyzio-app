<?php

namespace Database\Factories;

use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientProfile>
 */
class ClientProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'address_city' => fake('cs_CZ')->city(),
            'occupation' => fake()->randomElement(['Učitel', 'Programátor', 'Lékař', 'Účetní', 'Prodavač', 'Manažer', 'Student', 'Důchodce', 'Řidič', 'Kuchař']),
            'weight' => fake()->randomFloat(1, 50, 110),
            'height' => fake()->randomFloat(1, 150, 200),
            'company_ico' => null,
            'company_dic' => null,
            'billing_address' => null,
            'billing_name' => null,
            'anamnesis' => fake()->paragraph(),
        ];
    }
}
