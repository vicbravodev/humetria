<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Contracts\ComplianceReader;

/** The norms Humi reports on, in the order it tells them. A new norm is added where this is built (AssistantServiceProvider). */
final readonly class ComplianceReaders
{
    /**
     * @param  list<ComplianceReader>  $readers
     */
    public function __construct(private array $readers) {}

    /**
     * @return list<ComplianceReader>
     */
    public function all(): array
    {
        return $this->readers;
    }
}
