<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\LimitCorrection;
use App\Modules\Tenancy\Data\LimitUsage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * What the plan lets a company have of people and work centers (the contracted seats and centers). A company with no plan has no
 * limits. Whoever adds one reserves it in the same transaction as the insert, with one conditional statement; whoever removes one
 * releases it. Billing says what a plan contracts (sync); Tenancy counts and refuses.
 */
interface CompanyLimits
{
    public const string SEATS = 'seats';

    public const string CENTERS = 'centers';

    /**
     * Takes places from the current company, all or none. Call it inside the transaction that adds what it counts.
     *
     * @param  string  $field  the form field the refusal is shown under.
     *
     * @throws ValidationException when they do not fit: it says what the plan covers, how many are left and what to do.
     */
    public function reserve(string $resource, int $quantity, string $field): void;

    /** Gives places back to the current company; never below zero. */
    public function release(string $resource, int $quantity): void;

    /**
     * Refuses as reserve() would, without taking anything: to warn before a big change is confirmed.
     *
     * @throws ValidationException
     */
    public function ensureRoom(string $resource, int $quantity, string $field): void;

    /**
     * What the company has used of each limit, seats first; nothing when it has no plan.
     *
     * @return list<LimitUsage>
     */
    public function usage(string $tenantId): array;

    /**
     * Sets what the plan contracts. A resource the company had no limit on starts counting from what it already has; one it had keeps its count.
     *
     * @param  array<string, int>  $amounts  by resource.
     *
     * @throws InvalidArgumentException for something that is not a limited resource or a negative amount.
     */
    public function sync(string $tenantId, array $amounts): void;

    /**
     * Recounts what every limited company really has and fixes the counters that drifted.
     *
     * @return list<LimitCorrection>
     */
    public function reconcile(): array;
}
