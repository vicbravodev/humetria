<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Exceptions\ScopeMismatch;
use App\Modules\Tenancy\Contracts\CurrentTenant;

/**
 * The contracts the tools read through answer for the company of the request. A tool built for a scope of another company
 * must not read at all: this makes the two agree instead of trusting that they do.
 */
final readonly class ScopeGuard
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @throws ScopeMismatch
     */
    public function check(AssistantScope $scope): void
    {
        if ($scope->tenantId !== $this->tenant->id()) {
            throw new ScopeMismatch;
        }
    }
}
