<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings back the kurzy/lekce switch on the course archive brick, retired by
 * 2026_07_18_200005 when one-off events moved to their own category pages.
 *
 * The cross-sell strip that replaced it is dropped everywhere, and the /kurzy
 * page opts into the switch with its lekce tab pinned to "Jednorázové lekce".
 * Idempotent: existing keys are never overwritten, and authored copy is only
 * touched while it still matches the seeded default.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('pages')
                ->whereNotNull('content')
                ->get(['id', 'system_key', 'content']);

            foreach ($rows as $page) {
                $content = json_decode((string) $page->content, true);

                if (! is_array($content)) {
                    continue;
                }

                $isCoursesPage = $page->system_key === 'kurzy';
                $changed = false;

                foreach ($content as &$node) {
                    if (($node['attrs']['id'] ?? null) !== 'course-archive') {
                        continue;
                    }

                    $config = $node['attrs']['config'] ?? [];

                    foreach (['cross_sell', 'cross_sell_title', 'cross_sell_text', 'cross_sell_category'] as $key) {
                        if (array_key_exists($key, $config)) {
                            unset($config[$key]);
                            $changed = true;
                        }
                    }

                    if ($isCoursesPage) {
                        $defaults = [
                            'show_type_switch' => true,
                            'courses_label' => 'Pohybové kurzy',
                            'courses_subtitle' => 'Pravidelné semestrální série lekcí',
                            'events_label' => 'Jednorázové lekce',
                            'events_subtitle' => 'Jednotlivé lekce bez závazku',
                            'event_categories' => ['jednorazove-lekce'],
                        ];

                        foreach ($defaults as $key => $value) {
                            if (! array_key_exists($key, $config)) {
                                $config[$key] = $value;
                                $changed = true;
                            }
                        }

                        // The heading now covers both tabs — but only rename it
                        // while it is still the seeded copy. `title` is a rich
                        // editor field, so it may have been wrapped in a <p>.
                        $title = trim((string) ($config['title'] ?? ''));

                        if (in_array($title, ['Naše kurzy', '<p>Naše kurzy</p>'], true)) {
                            $config['title'] = str_replace('Naše kurzy', 'Naše kurzy a lekce', $title);
                            $changed = true;
                        }
                    }

                    $node['attrs']['config'] = $config;
                }
                unset($node);

                if ($changed) {
                    DB::table('pages')->where('id', $page->id)->update([
                        'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Not reversible — the cross-sell copy it removes is gone from the brick.
    }
};
