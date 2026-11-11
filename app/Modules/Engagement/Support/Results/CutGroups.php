<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

/**
 * The groups of one cut that may be shown, with their responses. Whether something was left out is a yes or no: how many
 * groups or people were is never told, because the difference with the total would give them away.
 */
final readonly class CutGroups
{
    /** @param  array<string, list<ResponseRow>>  $visible  by the value of the attribute */
    public function __construct(
        public array $visible,
        public bool $hasHidden,
    ) {}
}
