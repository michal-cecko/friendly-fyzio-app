<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseArchiveBrickConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_kurzy_page_enables_the_type_switch_scoped_to_lekce(): void
    {
        $this->seed(PageSeeder::class);

        $config = $this->courseArchiveConfig();

        $this->assertTrue($config['show_type_switch']);
        $this->assertSame(['jednorazove-lekce'], $config['event_categories']);
        $this->assertSame('Naše kurzy a lekce', $config['title']);

        foreach (['cross_sell', 'cross_sell_title', 'cross_sell_text', 'cross_sell_category'] as $key) {
            $this->assertArrayNotHasKey($key, $config);
        }
    }

    public function test_the_data_migration_upgrades_a_pre_existing_kurzy_page(): void
    {
        $this->seed(PageSeeder::class);

        // Rewind the page to the shape production is on before this deploy.
        $page = Page::query()->where('system_key', 'kurzy')->firstOrFail();
        $content = $page->content;

        foreach ($content as $index => $node) {
            if (($node['attrs']['id'] ?? null) !== 'course-archive') {
                continue;
            }

            $content[$index]['attrs']['config'] = [
                'eyebrow' => 'Aktuální nabídka',
                'title' => 'Naše kurzy',
                'show_filters' => true,
                'show_search' => true,
                'cross_sell' => true,
                'cross_sell_title' => 'Chcete si to nejdřív vyzkoušet?',
                'cross_sell_text' => 'Přijďte na jednorázovou lekci bez závazku celého kurzu.',
                'cross_sell_category' => 'jednorazove-lekce',
            ];
        }

        DB::table('pages')->where('id', $page->getKey())->update([
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
        ]);

        $this->runMigration();

        $config = $this->courseArchiveConfig();

        $this->assertTrue($config['show_type_switch']);
        $this->assertSame(['jednorazove-lekce'], $config['event_categories']);
        $this->assertSame('Naše kurzy a lekce', $config['title']);
        $this->assertArrayNotHasKey('cross_sell', $config);
        $this->assertArrayNotHasKey('cross_sell_category', $config);
    }

    public function test_the_data_migration_leaves_authored_copy_alone_and_is_idempotent(): void
    {
        $this->seed(PageSeeder::class);

        $page = Page::query()->where('system_key', 'kurzy')->firstOrFail();
        $content = $page->content;

        foreach ($content as $index => $node) {
            if (($node['attrs']['id'] ?? null) !== 'course-archive') {
                continue;
            }

            $content[$index]['attrs']['config']['title'] = 'Nabídka na podzim';
            $content[$index]['attrs']['config']['event_categories'] = ['workshopy'];
        }

        DB::table('pages')->where('id', $page->getKey())->update([
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
        ]);

        $this->runMigration();
        $this->runMigration();

        $config = $this->courseArchiveConfig();

        $this->assertSame('Nabídka na podzim', $config['title']);
        $this->assertSame(['workshopy'], $config['event_categories']);
    }

    private function runMigration(): void
    {
        (require database_path('migrations/2026_07_30_090000_restore_course_archive_type_switch.php'))->up();
    }

    /**
     * @return array<string, mixed>
     */
    private function courseArchiveConfig(): array
    {
        $page = Page::query()->where('system_key', 'kurzy')->firstOrFail();

        foreach ($page->content as $node) {
            if (($node['attrs']['id'] ?? null) === 'course-archive') {
                return $node['attrs']['config'];
            }
        }

        $this->fail('The kurzy page has no course-archive brick.');
    }
}
