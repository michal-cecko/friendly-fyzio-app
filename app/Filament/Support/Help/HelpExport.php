<?php

namespace App\Filament\Support\Help;

use Illuminate\Support\Str;

/**
 * The whole manual concatenated into a single markdown document.
 *
 * Written to be pasted into an AI assistant: one file, an explicit heading
 * hierarchy (document → section → article → the article's own headings) and a
 * table of contents, so the model can tell where one article ends and the next
 * begins instead of guessing from a wall of text.
 *
 * Given a {@see HelpVersion} the export describes that archived snapshot instead
 * of the live manual — same shape, but stamped with the version's date and commit
 * so a downloaded file can always be traced back to the code it documented.
 */
class HelpExport
{
    public function __construct(
        protected HelpRepository $repository,
        protected ?HelpVersion $version = null,
    ) {}

    public function filename(): string
    {
        $stamp = $this->version?->id ?? now()->format('Y-m-d');

        return 'napoveda-'.Str::slug(config('app.name')).'-'.$stamp.'.md';
    }

    public function markdown(): string
    {
        $sections = $this->repository->sections();
        $topics = $this->repository->topics();

        $lines = [
            '# Nápověda — '.config('app.name'),
            '',
            $this->provenance().' '
                .$this->count($topics->count()).' v '.$this->sectionCount($sections->count()).'.',
            '',
        ];

        if ($topics->isNotEmpty()) {
            $lines[] = '## Obsah';
            $lines[] = '';

            foreach ($sections as $section) {
                $lines[] = '- **'.$section->title.'**';

                foreach ($section->topics as $topic) {
                    $lines[] = '    - '.$topic->title;
                }
            }

            $lines[] = '';
        }

        foreach ($sections as $section) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = '## '.$section->title;
            $lines[] = '';

            foreach ($section->topics as $topic) {
                $lines[] = '### '.$topic->title;
                $lines[] = '';

                if ($topic->keywords !== []) {
                    $lines[] = '*Klíčová slova: '.implode(', ', $topic->keywords).'*';
                    $lines[] = '';
                }

                $body = trim($this->demoteHeadings($topic->markdown));

                if ($body !== '') {
                    $lines[] = $body;
                    $lines[] = '';
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Where this file came from. An archived version names its date and commit;
     * the live manual can only name the day it was exported.
     */
    protected function provenance(): string
    {
        if ($this->version === null) {
            return 'Kompletní příručka administrace, vyexportovaná '.now()->format('j. n. Y').'.';
        }

        return 'Archivní verze příručky z '.$this->version->label()
            .($this->version->commit !== null ? ' (commit '.$this->version->commit.')' : '').'.';
    }

    /**
     * Push every heading in an article one level down, so its own `##` sits under
     * the `###` article title this export wraps it in. Headings inside fenced code
     * blocks are shell comments, not structure, and are left alone.
     */
    protected function demoteHeadings(string $markdown): string
    {
        $lines = preg_split('/\R/u', $markdown) ?: [];
        $isFenced = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*(```|~~~)/', $line)) {
                $isFenced = ! $isFenced;

                continue;
            }

            // `#{1,5}` and not `#{1,6}`: markdown has no h7, so the deepest level
            // stays where it is rather than turning into literal hashes.
            if (! $isFenced && preg_match('/^#{1,5}\s/', $line)) {
                $lines[$index] = '#'.$line;
            }
        }

        return implode("\n", $lines);
    }

    protected function count(int $topics): string
    {
        return $topics.' '.match (true) {
            $topics === 1 => 'článek',
            $topics >= 2 && $topics <= 4 => 'články',
            default => 'článků',
        };
    }

    /**
     * Locative: "v 1 sekci", "ve 3 sekcích" — the plural form does not vary further.
     */
    protected function sectionCount(int $sections): string
    {
        return $sections.' '.($sections === 1 ? 'sekci' : 'sekcích');
    }
}
