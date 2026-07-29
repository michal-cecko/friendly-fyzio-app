<?php

namespace App\Filament\Support\Help;

use Illuminate\Support\Carbon;

/**
 * One archived snapshot of the manual — the article tree exactly as it stood at
 * a given commit, plus the commit that produced it.
 *
 * The id doubles as the URL segment (`/admin/napoveda/2026-07-29`), so it is the
 * commit's date rather than its hash: staff reach for "the version from July" and
 * never for a SHA. Everything staff-facing is named after the id, so the address
 * bar, the picker and the heading can never disagree; `date` is the commit's own
 * date, which matches the id unless somebody passed `--id` by hand.
 */
final readonly class HelpVersion
{
    public function __construct(
        public string $id,
        public Carbon $date,
        public ?string $commit,
        public ?string $subject,
        public int $sections,
        public int $topics,
        public string $root,
    ) {}

    public function label(): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->id) === 1
            ? Carbon::parse($this->id)->format('j. n. Y')
            : $this->id;
    }

    /**
     * The commit behind the snapshot, short enough for one line. The commit's own
     * date is named only when it differs from the version's — otherwise the label
     * beside it already said it.
     */
    public function commitLabel(): ?string
    {
        if ($this->commit === null) {
            return null;
        }

        $parts = [$this->commit];

        if ($this->date->toDateString() !== $this->id) {
            $parts[] = $this->date->format('j. n. Y');
        }

        if ($this->subject !== null) {
            $parts[] = $this->subject;
        }

        return implode(' · ', $parts);
    }
}
