<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

/**
 * What a group list shows after the minimum: the groups that may be shown (largest first, by key on a tie) and how many
 * stay hidden.
 */
final readonly class SuppressionResult
{
    /**
     * @param  list<string>  $visible
     */
    public function __construct(public array $visible, public int $hidden) {}
}
