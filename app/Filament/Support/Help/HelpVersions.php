<?php

namespace App\Filament\Support\Help;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * The archive of manual snapshots kept under resources/help-versions.
 *
 * A snapshot is a verbatim copy of the article tree plus a `_version.json`
 * manifest, written by `artisan help:snapshot` and committed alongside the code.
 * Nothing is read from git at runtime — the production image ships no git binary,
 * and a manual that only renders on a developer's machine would be no manual.
 *
 * {@see self::LATEST} is not a snapshot: it is the live `resources/help` tree,
 * always the newest thing there is, and the version the page opens on.
 */
class HelpVersions
{
    /**
     * Directory holding the snapshots, relative to resource_path().
     */
    public const PATH = 'help-versions';

    /**
     * Manifest file inside each snapshot. Underscored so {@see HelpRepository}
     * skips it — it walks directories only, but the convention keeps the intent
     * readable next to `_section.md`.
     */
    public const MANIFEST = '_version.json';

    public const LATEST = 'latest';

    /**
     * @param  string|null  $root  Absolute path to the archive; defaults to
     *                             resources/help-versions. Tests point it at a fixture.
     */
    public function __construct(protected ?string $root = null) {}

    public function path(): string
    {
        return $this->root ?? resource_path(self::PATH);
    }

    /**
     * Archived snapshots, newest first. A directory without a readable manifest
     * is skipped rather than guessed at — a half-written snapshot must not turn
     * into a version staff can open.
     *
     * @return Collection<int, HelpVersion>
     */
    public function all(): Collection
    {
        $root = $this->path();

        if (! File::isDirectory($root)) {
            return collect();
        }

        return collect(File::directories($root))
            ->map(fn (string $directory): ?HelpVersion => $this->read($directory))
            ->filter()
            ->sortByDesc(fn (HelpVersion $version): string => $version->id)
            ->values();
    }

    public function find(?string $id): ?HelpVersion
    {
        if (blank($id) || $id === self::LATEST) {
            return null;
        }

        return $this->all()->first(fn (HelpVersion $version): bool => $version->id === $id);
    }

    /**
     * A repository over the given version. `latest` — and any id matching no
     * snapshot — resolves the container's own repository, so whatever the app is
     * configured to read as "the manual" stays the manual. Unknown ids fall back
     * rather than 404 for the same reason the page falls back on an unknown
     * topic: help that errors is worse than help that starts over.
     */
    public function repository(?string $id): HelpRepository
    {
        $version = $this->find($id);

        return $version === null
            ? app(HelpRepository::class)
            : new HelpRepository($version->root);
    }

    protected function read(string $directory): ?HelpVersion
    {
        $manifest = $directory.DIRECTORY_SEPARATOR.self::MANIFEST;

        if (! File::isFile($manifest)) {
            return null;
        }

        $data = json_decode(File::get($manifest), true);

        if (! is_array($data) || blank($data['id'] ?? null)) {
            return null;
        }

        return new HelpVersion(
            id: (string) $data['id'],
            date: Carbon::parse($data['date'] ?? $data['id']),
            commit: $this->string($data, 'commit'),
            subject: $this->string($data, 'subject'),
            sections: (int) ($data['sections'] ?? 0),
            topics: (int) ($data['topics'] ?? 0),
            root: $directory,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && filled($value) ? $value : null;
    }
}
