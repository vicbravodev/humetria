<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

/**
 * A link that opened: the company it entered and what it is called, which is all the public page knows of it.
 */
final readonly class OpenedPage
{
    public function __construct(public string $tenantId, public string $company) {}
}
