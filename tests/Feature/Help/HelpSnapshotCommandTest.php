<?php

namespace Tests\Feature\Help;

use App\Filament\Support\Help\HelpRepository;
use App\Filament\Support\Help\HelpVersions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HelpSnapshotCommandTest extends TestCase
{
    protected string $archive;

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot the fixture tree into a scratch archive: the command copies
        // real directories, and the repository's own resources are not ours to
        // write into from a test.
        $this->archive = storage_path('framework/testing/help-versions-'.getmypid());

        File::deleteDirectory($this->archive);

        $this->app->bind(HelpRepository::class, fn (): HelpRepository => new HelpRepository(
            base_path('tests/Fixtures/help'),
        ));

        $this->app->bind(HelpVersions::class, fn (): HelpVersions => new HelpVersions($this->archive));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->archive);

        parent::tearDown();
    }

    public function test_it_copies_the_manual_and_writes_a_manifest(): void
    {
        $this->artisan('help:snapshot', ['--id' => '2026-01-31'])->assertSuccessful();

        $this->assertFileExists($this->archive.'/2026-01-31/02-druha/01-alfa.md');

        $manifest = json_decode(File::get($this->archive.'/2026-01-31/_version.json'), true);

        $this->assertSame('2026-01-31', $manifest['id']);
        $this->assertSame(2, $manifest['sections']);
        $this->assertSame(3, $manifest['topics']);
    }

    public function test_the_snapshot_becomes_a_readable_version(): void
    {
        $this->artisan('help:snapshot', ['--id' => '2026-01-31'])->assertSuccessful();

        $versions = app(HelpVersions::class);
        $version = $versions->find('2026-01-31');

        $this->assertNotNull($version);
        $this->assertSame('31. 1. 2026', $version->label());
        $this->assertSame('Alfa článek', $versions->repository('2026-01-31')->find('druha/alfa')?->title);
    }

    /**
     * Two snapshots a day apart is normal; two under one id would silently
     * replace history, so it takes an explicit --force.
     */
    public function test_an_existing_version_is_not_overwritten_by_accident(): void
    {
        $this->artisan('help:snapshot', ['--id' => '2026-01-31'])->assertSuccessful();

        $this->artisan('help:snapshot', ['--id' => '2026-01-31'])->assertFailed();

        $this->artisan('help:snapshot', ['--id' => '2026-01-31', '--force' => true])->assertSuccessful();
    }

    public function test_versions_are_listed_newest_first(): void
    {
        $this->artisan('help:snapshot', ['--id' => '2026-01-31'])->assertSuccessful();
        $this->artisan('help:snapshot', ['--id' => '2026-03-15'])->assertSuccessful();

        $this->assertSame(
            ['2026-03-15', '2026-01-31'],
            app(HelpVersions::class)->all()->pluck('id')->all(),
        );
    }

    /**
     * A directory without a manifest is a half-written snapshot, not a version.
     */
    public function test_a_directory_without_a_manifest_is_ignored(): void
    {
        File::ensureDirectoryExists($this->archive.'/2026-02-02/01-sekce');

        $this->assertTrue(app(HelpVersions::class)->all()->isEmpty());
        $this->assertNull(app(HelpVersions::class)->find('2026-02-02'));
    }
}
