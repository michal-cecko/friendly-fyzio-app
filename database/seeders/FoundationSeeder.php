<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The configuration and CMS content the application cannot run without, shared
 * by the production and development seeding paths so the two can never drift
 * apart. Contains no demo records and no user accounts.
 *
 * Every step is idempotent, so this also pulls in new settings, email templates
 * or CMS pages on an existing installation.
 */
class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        // Roles and permissions first: saving any user syncs a matching Shield
        // role, so the roles have to exist before accounts are created.
        $this->call(RolePermissionSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(InvoiceSeriesSeeder::class);
        $this->call(EmailTemplateSeeder::class);
        // Event categories seed before pages: the /workshopy page attaches to
        // the Workshopy category as its custom page.
        $this->call(EventCategorySeeder::class);
        $this->call(PageSeeder::class);
        $this->call(BannerSeeder::class);
        $this->call(ServiceCategorySeeder::class);
        // Navigation references categories (category:{id} link refs), so it runs after them.
        $this->call(NavigationSeeder::class);
    }
}
