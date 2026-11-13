<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

/** A demo request as another module sees it: who asked and for which company; `tenantId` is the company it became, if any. */
final readonly class DemoLead
{
    public function __construct(
        public string $id,
        public string $company,
        public string $contactName,
        public string $email,
        public ?string $tenantId,
    ) {}
}
