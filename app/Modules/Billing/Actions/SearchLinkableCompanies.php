<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Tenancy\Contracts\CompanyRegistry;

final readonly class SearchLinkableCompanies
{
    /** How many companies are read before the ones that already have a payer are dropped. */
    private const int WIDER = 3;

    public function __construct(private CompanyRegistry $companies) {}

    /**
     * The companies with no current payer whose name or RFC has what was typed, by name, up to a limit, as the options of a
     * field: the company's id and a label with its RFC.
     *
     * @return list<array{value: string, label: string}>
     */
    public function handle(string $term, int $limit = 20): array
    {
        $found = $this->companies->search($term, $limit * self::WIDER);
        $paid = AccountCompany::query()->current()
            ->whereIn('tenant_id', array_map(fn ($brief): string => $brief->id, $found))
            ->pluck('tenant_id')
            ->all();

        $options = [];

        foreach ($found as $brief) {
            if (! in_array($brief->id, $paid, true)) {
                $options[] = ['value' => $brief->id, 'label' => "{$brief->tradeName} · {$brief->rfc}"];
            }
        }

        return array_slice($options, 0, $limit);
    }
}
