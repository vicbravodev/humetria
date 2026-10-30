<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Narrows to rows whose uuid column is one of many ids, bound as a single Postgres array: a plain whereIn binds one
 * parameter per id, and Postgres refuses more than 65,535. What is not a uuid is never any row's id, so it is left out.
 */
trait MatchesManyIds
{
    /**
     * @param  Builder<static>  $query
     * @param  literal-string  $column  written in the code, qualified when the query joins
     * @param  array<array-key, string>  $ids
     */
    public function scopeAmong(Builder $query, string $column, array $ids): void
    {
        $uuids = array_filter($ids, Str::isUuid(...));

        $query->whereRaw($column.' = any(?::uuid[])', ['{'.implode(',', $uuids).'}']);
    }
}
