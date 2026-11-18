<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

/**
 * The fewest anonymous answers a group of the current company needs before its result is shown (criterio C-23). The
 * platform proposes 5 so nobody can tell whose answers a small group's result shows; the company may lower it, down to 1
 * («sin mínimo»), or raise it.
 */
interface GroupMinimums
{
    public const int LOWEST = 1;

    public const int HIGHEST = 50;

    /** What the company applies now: its own choice, or the platform's default. */
    public function current(): int;

    /** The company's own choice, null while it follows the platform's default, and outside any company. */
    public function own(): ?int;

    /** What a company that has not chosen applies (`results.min_group`). */
    public function platformDefault(): int;
}
