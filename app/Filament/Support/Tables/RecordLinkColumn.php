<?php

namespace App\Filament\Support\Tables;

use App\Support\ActivityLog\ActivityLink;
use Closure;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

/**
 * A table cell that points at another record's own admin page. Wherever the
 * target resolves the cell reads as a link — primary and bold — and wherever it
 * does not (no related record, or no resource owns it) it stays plain text
 * rather than offering a dead link.
 *
 * Which page a record belongs to is answered by {@see ActivityLink}, so no table
 * has to know which resource owns which model — including polymorphic columns,
 * where that differs row by row.
 */
final class RecordLinkColumn
{
    /** @var WeakMap<Model, array{0: ?string}>|null */
    private static ?WeakMap $urls = null;

    /**
     * @param  Closure(Model): ?Model  $target  The record this cell links to.
     */
    public static function make(string $name, Closure $target): TextColumn
    {
        return TextColumn::make($name)
            ->url(fn (Model $record): ?string => self::url($target($record)))
            ->color(fn (Model $record): ?string => self::url($target($record)) !== null ? 'primary' : null)
            ->weight(fn (Model $record): ?FontWeight => self::url($target($record)) !== null ? FontWeight::Bold : null)
            ->placeholder('—');
    }

    /**
     * The target's own human-readable title, for cells that have no attribute of
     * their own to show — a polymorphic relation, most of all.
     */
    public static function label(?Model $record): ?string
    {
        return $record === null ? null : ActivityLink::label($record);
    }

    /**
     * Filament asks each of the three closures above separately, and resolving a
     * page can cost a query (a User is owned by both Uživatelé and Klienti), so
     * the answer is memoised per record. The map is weak and keyed on the record
     * object itself, so entries die with the request that loaded them — nothing
     * survives into the next one under Octane. The single-element array keeps a
     * resolved "no page for this one" from being looked up again.
     */
    private static function url(?Model $record): ?string
    {
        if ($record === null) {
            return null;
        }

        self::$urls ??= new WeakMap;

        return (self::$urls[$record] ??= [ActivityLink::url($record)])[0];
    }
}
