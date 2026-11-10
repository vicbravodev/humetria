<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingAccount;

final readonly class SearchAccounts
{
    /**
     * The accounts whose legal name, RFC or contact has what was typed, by name, up to a limit, as the options of a field: the
     * account's id and a label with its RFC and kind.
     *
     * @return list<array{value: string, label: string}>
     */
    public function handle(string $term, int $limit = 20): array
    {
        $options = [];

        foreach (BillingAccount::query()->matching(['legal_name', 'rfc', 'contact_name'], $term)->orderBy('legal_name')->orderBy('id')->limit($limit)->get() as $account) {
            $options[] = ['value' => $account->id, 'label' => "{$account->legal_name} · {$account->rfc} · {$account->kind->label()}"];
        }

        return $options;
    }
}
