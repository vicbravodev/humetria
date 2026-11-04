<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

/**
 * The channel for complaints as the public page repeats it (num. 5.7 d): where to go, whether the channel accepts anonymous
 * ones and what it takes. Disclosure promises nothing of its own: it says what the channel says.
 */
final readonly class PublicChannel
{
    /**
     * @param  list<string>  $categories
     */
    public function __construct(public string $url, public bool $anonymousAllowed, public array $categories) {}
}
