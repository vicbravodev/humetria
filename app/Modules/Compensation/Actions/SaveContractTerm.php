<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Data\TermData;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Support\TenureTable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class SaveContractTerm
{
    /**
     * The terms of the benefit from a date on; the ones of the same date are replaced, so a benefit has one per date.
     *
     * @throws ValidationException
     */
    public function handle(Benefit $benefit, TermData $data): ContractTerm
    {
        $errors = [];

        if ($data->value < 0) {
            $errors['value'] = 'El valor no puede ser negativo.';
        }

        $problem = TenureTable::problem($data->tenureTable ?? []);

        if ($problem !== null) {
            $errors['tenure_table'] = $problem;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $term = ContractTerm::query()->updateOrCreate(
            ['benefit_id' => $benefit->id, 'effective_from' => $data->effectiveFrom->toDateString()],
            ['tenant_id' => $benefit->tenant_id, 'value' => $data->value, 'tenure_table' => $data->tenureTable === [] ? null : $data->tenureTable],
        );

        // Ids only: what the terms say is money, which stays out of logs.
        Log::info('compensation.term_saved', ['benefit_id' => $benefit->id, 'term_id' => $term->id]);

        return $term;
    }
}
