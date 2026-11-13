<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;

/**
 * A search of what was typed inside some text columns, ignoring case and accents: "jose" finds "José". The words typed
 * may come in any order ("perez juan" finds "Juan Pérez"): each one has to be in some column. LIKE's own wildcards in
 * what was typed are searched as themselves; nothing typed (or only spaces) matches everything.
 */
trait SearchesText
{
    /** A search past this many words is read up to here: more would only slow the query down. */
    private const int MAX_WORDS = 8;

    /** Fewer digits than these are not a phone number: «12» is not looked for among mobiles. */
    private const int MIN_PHONE_DIGITS = 4;

    /**
     * @param  Builder<static>  $query
     * @param  non-empty-list<literal-string>  $columns  of the model's own table, written in the code
     * @param  list<literal-string>  $phoneColumns  columns of digits: found by the digits typed, spaces and dashes aside
     */
    public function scopeMatching(Builder $query, array $columns, string $search, array $phoneColumns = []): void
    {
        $words = array_slice(preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAX_WORDS);

        if ($words === []) {
            return;
        }

        $digits = (string) preg_replace('/\D/', '', $search);

        $query->where(function (Builder $match) use ($columns, $words, $phoneColumns, $digits): void {
            // Every word is somewhere...
            $match->where(function (Builder $all) use ($columns, $words): void {
                foreach ($words as $word) {
                    // Accents are dropped here as unaccent() drops them from the column.
                    $like = '%'.addcslashes(Str::ascii($word), '\\%_').'%';

                    $all->where(function (Builder $any) use ($columns, $like): void {
                        foreach ($columns as $column) {
                            $any->orWhere(new Expression('unaccent('.$column.')'), 'ilike', $like);
                        }
                    });
                }
            });

            // ...or it is a phone number, written however.
            if ($phoneColumns !== [] && strlen($digits) >= self::MIN_PHONE_DIGITS) {
                foreach ($phoneColumns as $column) {
                    $match->orWhere(new Expression($column), 'like', "%{$digits}%");
                }
            }
        });
    }
}
