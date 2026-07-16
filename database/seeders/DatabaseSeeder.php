<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(InvoiceSeriesSeeder::class);
        $this->call(EmailTemplateSeeder::class);
        $this->call(PageSeeder::class);
        $this->call(BannerSeeder::class);
        $this->call(ServiceCategorySeeder::class);
        // Navigation references categories (category:{id} link refs), so it runs after them.
        $this->call(NavigationSeeder::class);

        // The User "saved" event syncs the matching Shield role from the account type
        // (Administrátor -> super_admin, Terapeut -> therapist), so no manual assignRole needed.
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@friendly-fyzio.test',
        ]);

        User::factory()->therapist()->create([
            'name' => 'Terapeut',
            'email' => 'therapist@friendly-fyzio.test',
        ]);

        // DemoSeeder creates the services; ServicePagesSeeder attaches custom pages to them.
        $this->call(DemoSeeder::class);
        $this->call(ServicePagesSeeder::class);
    }
}
