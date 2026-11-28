<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * The console's narrowing of its companies beyond the search and the view (tablero Empresas, chips): who answers for them
 * (an id, or nobody), seats almost used up, and the companies of one plan as Billing named them (null: any plan).
 */
final readonly class TenantNarrowing
{
    public const string NOBODY = 'none';

    /** @param  list<string>|null  $companies */
    public function __construct(public ?string $manager = null, public bool $highUsage = false, public ?array $companies = null) {}
}
