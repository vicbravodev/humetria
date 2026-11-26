<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Data\CompanyRevenue;

/** What the platform earns, for the staff: the total across companies, and each company of a console page. */
interface PlatformRevenue
{
    /** The monthly price, before VAT, of every subscription that pays now (active or cancelling at the end of its term), in cents. */
    public function monthlyContractedCents(): int;

    /**
     * The plan of each of these companies that has one now (not ended), what it pays a month and when it renews: a page of
     * the console at once, in the same queries however many it holds.
     *
     * @param  list<string>  $tenantIds
     * @return array<string, CompanyRevenue> by company id
     */
    public function byCompany(array $tenantIds): array;

    /**
     * What was billed before VAT in each of the last months (issued statements, by the month they bill), the oldest first
     * and the current one last; a month without statements is zero.
     *
     * @return list<array{month: string, cents: int}> month as Y-m
     */
    public function billedByMonth(int $months): array;
}
