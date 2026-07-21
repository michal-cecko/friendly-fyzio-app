<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Academic titles that may prefix a stored name ("Bc. Petra Novotná").
     * Ordered longest-first so e.g. "PhDr." wins over "Dr.".
     *
     * @var array<int, string>
     */
    private array $beforeTitles = [
        'PharmDr.', 'PaedDr.', 'ThMgr.', 'MSDr.', 'MDDr.', 'MVDr.', 'MUDr.', 'JUDr.', 'PhDr.', 'RNDr.', 'ThDr.', 'RSDr.',
        'ThLic.', 'prof.', 'doc.', 'BcA.', 'MgA.', 'Ing.', 'Mgr.', 'Bc.', 'Dr.',
    ];

    /**
     * Titles that may trail the name after a comma ("Petra Novotná, DiS.").
     *
     * @var array<int, string>
     */
    private array $afterTitles = [
        'Ph.D.', 'Th.D.', 'DrSc.', 'CSc.', 'DiS.', 'LL.M.', 'MBA', 'MPA', 'dr. h. c.',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('title_before')->nullable()->after('name');
            $table->string('title_after')->nullable()->after('title_before');
        });

        $this->splitTitlesOutOfExistingNames();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['title_before', 'title_after']);
        });
    }

    /**
     * Existing names carry titles inline ("Bc. Petra Novotná"); move any
     * recognised titles into the new columns so `name` stays a clean name.
     */
    private function splitTitlesOutOfExistingNames(): void
    {
        DB::table('users')->select(['id', 'name'])->orderBy('id')->chunk(200, function ($users): void {
            foreach ($users as $user) {
                $name = trim((string) $user->name);
                $before = [];
                $after = [];

                $matched = true;

                while ($matched) {
                    $matched = false;

                    foreach ($this->beforeTitles as $title) {
                        if (Str::startsWith($name, $title.' ')) {
                            $before[] = $title;
                            $name = ltrim(Str::after($name, $title));
                            $matched = true;

                            break;
                        }
                    }
                }

                foreach ($this->afterTitles as $title) {
                    foreach ([', '.$title, ' '.$title] as $suffix) {
                        if (Str::endsWith($name, $suffix)) {
                            array_unshift($after, $title);
                            $name = rtrim(Str::beforeLast($name, $suffix), ', ');

                            break 2;
                        }
                    }
                }

                if ($before === [] && $after === []) {
                    continue;
                }

                DB::table('users')->where('id', $user->id)->update([
                    'name' => $name,
                    'title_before' => $before === [] ? null : implode(' ', $before),
                    'title_after' => $after === [] ? null : implode(', ', $after),
                ]);
            }
        });
    }
};
