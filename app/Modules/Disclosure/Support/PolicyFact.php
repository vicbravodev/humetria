<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use Carbon\CarbonImmutable;

/**
 * The version of the policy in force in a work center: which one, what it declares and when it was published.
 */
final readonly class PolicyFact
{
    /**
     * @param  list<string>  $covers
     */
    public function __construct(public string $documentId, public int $version, public array $covers, public CarbonImmutable $publishedAt) {}
}
