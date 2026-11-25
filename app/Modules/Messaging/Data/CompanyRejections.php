<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/** A company and how many of its messages were rejected in a range: never which ones, nor to whom. */
final readonly class CompanyRejections
{
    public function __construct(public string $tenantId, public int $rejected) {}
}
