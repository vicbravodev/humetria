<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;

/** The norms Humi reports on, in the order it tells them. A new norm is added where this is built (AssistantServiceProvider). */
final readonly class ComplianceReaders
{
    /**
     * @param  list<ComplianceReader>  $readers
     * @param  array<class-string<ComplianceReader>, string>  $modules  the module of the company each reader reports on; one not listed is told to every company.
     * @param  array<class-string<ComplianceReader>, string>  $requires  the permission that sees the reader's area in the panel; one not listed is told to everyone (it keeps its own figures from whoever may not see them).
     */
    public function __construct(private array $readers, private array $modules = [], private array $requires = []) {}

    public function moduleOf(ComplianceReader $reader): ?string
    {
        return $this->modules[$reader::class] ?? null;
    }

    /** Whether the person of the scope may be told the reader's area: only whoever sees it in the panel. */
    public function tells(ComplianceReader $reader, AssistantScope $scope): bool
    {
        $permission = $this->requires[$reader::class] ?? null;

        return $permission === null || $scope->canSee($permission);
    }

    /**
     * @return list<ComplianceReader>
     */
    public function all(): array
    {
        return $this->readers;
    }
}
