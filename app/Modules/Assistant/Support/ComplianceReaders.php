<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Contracts\ComplianceReader;

/** The norms Humi reports on, in the order it tells them. A new norm is added where this is built (AssistantServiceProvider). */
final readonly class ComplianceReaders
{
    /**
     * @param  list<ComplianceReader>  $readers
     * @param  array<class-string<ComplianceReader>, string>  $modules  the module of the company each reader reports on; one not listed is told to every company.
     */
    public function __construct(private array $readers, private array $modules = []) {}

    public function moduleOf(ComplianceReader $reader): ?string
    {
        return $this->modules[$reader::class] ?? null;
    }

    /**
     * @return list<ComplianceReader>
     */
    public function all(): array
    {
        return $this->readers;
    }
}
