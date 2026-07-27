<?php

namespace App\Support\ActivityLog;

use App\Models\SubstituteToken;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a logged value into a link to the record it refers to.
 *
 * Two shapes occur in the log. Model columns store foreign keys (`client_id`,
 * `excused_by_id`) — those resolve through the subject's own relation, so both
 * the old and the new value of an edit find their record. Semantic events store
 * a name or a token instead ({@see LogActivity}); those resolve through the map
 * below, by key when the value is a UUID and by an unambiguous name otherwise.
 */
class ActivityLink
{
    /**
     * Property keys that name a record rather than reference it by id.
     *
     * @var array<string, class-string<Model>>
     */
    private const ENTITY_KEYS = [
        'client' => User::class,
        'client_name' => User::class,
        'substitute_token' => SubstituteToken::class,
    ];

    /** Columns a named entity may be matched on, in order of preference. */
    private const NAME_COLUMNS = ['name', 'title', 'code'];

    /**
     * The record a value points at, with its Czech label and admin URL, or null
     * when nothing resolves.
     *
     * @return array{label: string, url: string}|null
     */
    public static function for(?string $key, mixed $value, ?Model $subject = null): ?array
    {
        $record = self::record($key, $value, $subject);

        if ($record === null) {
            return null;
        }

        $url = self::url($record);

        return $url === null ? null : ['label' => self::label($record), 'url' => $url];
    }

    /**
     * The record a logged value refers to, without resolving a URL for it —
     * used where only the name is wanted, such as the one-line summary that
     * would otherwise print a bare UUID.
     */
    public static function record(?string $key, mixed $value, ?Model $subject = null): ?Model
    {
        if ($key === null || ! is_scalar($value) || is_bool($value) || blank($value)) {
            return null;
        }

        return self::resolve($key, (string) $value, $subject);
    }

    /**
     * The Filament page for a record: its resource's view page, else edit, else
     * the listing. Null when no accessible resource owns the model.
     */
    public static function url(Model $record): ?string
    {
        foreach (self::resourcesFor($record) as $resource) {
            foreach (['view', 'edit'] as $page) {
                try {
                    return $resource::getUrl($page, ['record' => $record]);
                } catch (Throwable) {
                    continue;
                }
            }

            try {
                return $resource::getUrl('index');
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    public static function label(Model $record): string
    {
        return method_exists($record, 'logTitle')
            ? $record->logTitle()
            : class_basename($record).' #'.Str::of((string) $record->getKey())->substr(0, 8);
    }

    protected static function resolve(string $key, string $value, ?Model $subject): ?Model
    {
        if (str_ends_with($key, '_id') && $subject !== null) {
            return self::byRelation($key, $value, $subject);
        }

        $model = self::ENTITY_KEYS[$key] ?? null;

        if ($model === null) {
            return null;
        }

        return Str::isUuid($value)
            ? self::find($model, $value)
            : self::byName($model, $value);
    }

    /**
     * A foreign key resolves through the relation it belongs to, so the record
     * class comes from the subject's own definition rather than a guess.
     */
    protected static function byRelation(string $key, string $value, Model $subject): ?Model
    {
        $relation = Str::camel(Str::beforeLast($key, '_id'));

        if (! method_exists($subject, $relation)) {
            return null;
        }

        try {
            $related = $subject->{$relation}();
        } catch (Throwable) {
            return null;
        }

        return $related instanceof BelongsTo ? self::find($related->getRelated()::class, $value) : null;
    }

    /**
     * Names are only linked when exactly one record carries them — two clients
     * called the same thing must not send staff to the wrong file.
     *
     * @param  class-string<Model>  $model
     */
    protected static function byName(string $model, string $value): ?Model
    {
        $instance = new $model;
        $column = collect(self::NAME_COLUMNS)
            ->first(fn (string $candidate): bool => $instance->getConnection()
                ->getSchemaBuilder()
                ->hasColumn($instance->getTable(), $candidate));

        if ($column === null) {
            return null;
        }

        $matches = self::query($model)->where($column, $value)->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @param  class-string<Model>  $model */
    protected static function find(string $model, string $value): ?Model
    {
        try {
            return self::query($model)->whereKey($value)->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Deleted records still deserve a link — the log outlives them, and their
     * resource page shows the trashed record.
     *
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    protected static function query(string $model): Builder
    {
        $query = $model::query();

        return in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? $query->withTrashed()
            : $query;
    }

    /**
     * The resources that own this model. When more than one does (User is both
     * "Uživatelé" and "Klienti"), the one whose own query contains the record
     * wins, so a customer links to their client file and staff to their profile.
     *
     * @return list<class-string<\Filament\Resources\Resource>>
     */
    protected static function resourcesFor(Model $record): array
    {
        $resources = array_values(array_filter(
            Filament::getResources(),
            fn (string $resource): bool => $resource::getModel() === $record::class,
        ));

        if (count($resources) < 2) {
            return $resources;
        }

        $owning = array_values(array_filter($resources, function (string $resource) use ($record): bool {
            try {
                return $resource::getEloquentQuery()->whereKey($record->getKey())->exists();
            } catch (Throwable) {
                return false;
            }
        }));

        return [...$owning, ...array_diff($resources, $owning)];
    }
}
