<?php

namespace App\Models\Scopes;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts the media library browser to the current user's own uploads.
 *
 * Admins see everything; a pure staff member (e.g. a therapist) only browses
 * files they uploaded themselves. The scope is deliberately confined to the
 * admin panel — public / front-end rendering must resolve every referenced
 * item regardless of who uploaded it.
 *
 * Explicit primary-key lookups are exempt: when a form loads an already-saved
 * value, or App\Support\Media resolves an id into a URL, the item is fetched by
 * key and must still return even if someone else uploaded it. That keeps
 * existing records rendering while a therapist merely views (or re-saves
 * without changing) a form that references another person's media.
 */
class MediaLibraryItemOwnershipScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Filament::getCurrentPanel()) {
            return;
        }

        $user = Filament::auth()->user();

        if (! $user instanceof User || $user->isAdmin()) {
            return;
        }

        if ($this->targetsPrimaryKey($builder, $model)) {
            return;
        }

        $builder->where(function (Builder $query) use ($model, $user): void {
            $query
                ->where($model->qualifyColumn('uploader_type'), $user->getMorphClass())
                ->where($model->qualifyColumn('uploader_id'), $user->getKey());
        });
    }

    /**
     * Whether the query already constrains the primary key — i.e. it is a
     * find()/whereKey() lookup for a known item rather than a listing/browse.
     */
    protected function targetsPrimaryKey(Builder $builder, Model $model): bool
    {
        $key = $model->getKeyName();
        $qualifiedKey = $model->getQualifiedKeyName();

        foreach ($builder->getQuery()->wheres as $where) {
            $column = $where['column'] ?? null;

            if ($column === $key || $column === $qualifiedKey) {
                return true;
            }
        }

        return false;
    }
}
