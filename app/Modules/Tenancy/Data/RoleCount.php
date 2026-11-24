<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/** A role of the company by the label the panel gives it, and how many people hold it. */
final readonly class RoleCount
{
    public function __construct(public string $label, public int $people) {}
}
