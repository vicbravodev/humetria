<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;

/**
 * A search of what was typed inside some text columns, ignoring case and accents: "jose" finds "José". LIKE's own
 * wildcards in what was typed are searched as themselves; nothing typed matches everything.
 */
trait SearchesText
{
    /**
     * @param  Builder<static>  $query
     * @param  non-empty-list<literal-string>  $columns  of the model's own table, written in the code
     */
    public function scopeMatching(Builder $query, array $columns, string $search): void
    {
        if ($search === '') {
            return;
        }

        // Accents are dropped here as unaccent() drops them from the column.
        $like = '%'.addcslashes(Str::ascii($search), '\\%_').'%';

        $query->where(function (Builder $match) use ($columns, $like): void {
            foreach ($columns as $column) {
                $match->orWhere(new Expression('unaccent('.$column.')'), 'ilike', $like);
            }
        });
    }
}
