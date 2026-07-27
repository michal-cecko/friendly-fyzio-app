<?php

namespace App\Filament\Support\Help;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the in-app documentation from markdown files under resources/help.
 *
 * Layout — numeric prefixes order the tree and are stripped from the ids, so
 * `02-kalendar/01-rezervace.md` becomes the topic `kalendar/rezervace`:
 *
 *   resources/help/
 *     02-kalendar/
 *       _section.md      front-matter only: the section's title and icon
 *       01-rezervace.md  front-matter (title, icon, keywords) + markdown body
 *
 * Stateless per call, because the app runs Octane and a repository that memoised
 * the tree on itself would keep serving deleted articles for the life of a worker.
 * The parse is cached instead, keyed on the files' modification times so editing an
 * article invalidates it by itself; debug builds skip the cache entirely.
 */
class HelpRepository
{
    /**
     * Directory holding the articles, relative to resource_path().
     */
    public const PATH = 'help';

    /**
     * @param  string|null  $root  Absolute path to the article tree; defaults to
     *                             resources/help. Tests point it at a fixture.
     */
    public function __construct(protected ?string $root = null) {}

    /**
     * @return Collection<int, HelpSection>
     */
    public function sections(): Collection
    {
        $directories = $this->sectionDirectories();

        if ($directories === []) {
            return collect();
        }

        if (config('app.debug')) {
            return $this->scan($directories);
        }

        return Cache::remember(
            $this->cacheKey($directories),
            now()->addDay(),
            fn (): Collection => $this->scan($directories),
        );
    }

    /**
     * Every topic, in reading order.
     *
     * @return Collection<int, HelpTopic>
     */
    public function topics(): Collection
    {
        return $this->sections()->flatMap(fn (HelpSection $section): Collection => $section->topics);
    }

    public function find(?string $id): ?HelpTopic
    {
        if (blank($id)) {
            return null;
        }

        return $this->topics()->first(fn (HelpTopic $topic): bool => $topic->id === $id);
    }

    /**
     * The topic shown when none was asked for.
     */
    public function first(): ?HelpTopic
    {
        return $this->topics()->first();
    }

    /**
     * Section directories in display order — `sort()` is enough because the numeric
     * prefixes are zero-padded.
     *
     * @return array<int, string>
     */
    protected function sectionDirectories(): array
    {
        $root = $this->root ?? resource_path(self::PATH);

        if (! File::isDirectory($root)) {
            return [];
        }

        $directories = File::directories($root);
        sort($directories);

        return $directories;
    }

    /**
     * @param  array<int, string>  $directories
     * @return Collection<int, HelpSection>
     */
    protected function scan(array $directories): Collection
    {
        return collect($directories)
            ->map(fn (string $directory): ?HelpSection => $this->section($directory))
            ->filter()
            ->values();
    }

    protected function section(string $directory): ?HelpSection
    {
        $id = $this->slug(basename($directory));
        [$meta] = $this->read($directory.DIRECTORY_SEPARATOR.'_section.md');

        $title = $this->string($meta, 'title') ?? Str::ucfirst(str_replace('-', ' ', $id));

        $topics = $this->articles($directory)
            ->map(fn (SplFileInfo $file): HelpTopic => $this->topic($file, $id, $title))
            ->values();

        if ($topics->isEmpty()) {
            return null;
        }

        return new HelpSection(
            id: $id,
            title: $title,
            icon: $this->string($meta, 'icon'),
            topics: $topics,
        );
    }

    /**
     * Markdown files in the directory, in display order. Files starting with an
     * underscore are metadata (`_section.md`), not articles.
     *
     * @return Collection<int, SplFileInfo>
     */
    protected function articles(string $directory): Collection
    {
        return collect(File::files($directory))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'md'
                && ! str_starts_with($file->getFilename(), '_'))
            ->sortBy(fn (SplFileInfo $file): string => $file->getFilename());
    }

    protected function topic(SplFileInfo $file, string $sectionId, string $sectionTitle): HelpTopic
    {
        [$meta, $markdown] = $this->read($file->getPathname());

        $id = $this->slug($file->getFilenameWithoutExtension());

        return new HelpTopic(
            id: $sectionId.'/'.$id,
            title: $this->string($meta, 'title') ?? Str::ucfirst(str_replace('-', ' ', $id)),
            icon: $this->string($meta, 'icon'),
            keywords: $this->keywords($meta),
            sectionId: $sectionId,
            sectionTitle: $sectionTitle,
            markdown: $markdown,
            plainText: $this->plainText($markdown),
        );
    }

    /**
     * Split a file into its YAML front-matter and its markdown body. A file without
     * front-matter is all body — malformed YAML is treated the same way rather than
     * breaking the whole help page.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function read(string $path): array
    {
        if (! File::isFile($path)) {
            return [[], ''];
        }

        $contents = File::get($path);

        if (! preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n?(.*)\z/s', $contents, $matches)) {
            return [[], $contents];
        }

        try {
            $meta = Yaml::parse($matches[1]);
        } catch (ParseException) {
            $meta = null;
        }

        return [is_array($meta) ? $meta : [], $matches[2]];
    }

    /**
     * Strip the numeric ordering prefix: `02-kalendar` → `kalendar`.
     */
    protected function slug(string $name): string
    {
        return (string) preg_replace('/^\d+[-_]/', '', $name);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function string(array $meta, string $key): ?string
    {
        $value = $meta[$key] ?? null;

        return is_string($value) && filled($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<int, string>
     */
    protected function keywords(array $meta): array
    {
        $keywords = $meta['keywords'] ?? [];

        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }

        if (! is_array($keywords)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($keyword): string => trim((string) $keyword), $keywords),
            fn (string $keyword): bool => $keyword !== '',
        ));
    }

    /**
     * Body text without markup, for searching and excerpts.
     */
    protected function plainText(string $markdown): string
    {
        $text = html_entity_decode(strip_tags(Str::markdown($markdown)), ENT_QUOTES);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @param  array<int, string>  $directories
     */
    protected function cacheKey(array $directories): string
    {
        $stamps = [];

        foreach ($directories as $directory) {
            foreach (File::files($directory) as $file) {
                $stamps[] = $file->getPathname().':'.$file->getMTime();
            }
        }

        return 'admin.help.'.md5(implode('|', $stamps));
    }
}
