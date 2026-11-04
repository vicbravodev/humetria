<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\Benefit;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveBenefit
{
    /**
     * Takes a benefit the company made up out of the lists and the scenarios without deleting it: its terms are kept. A
     * base benefit is never archived.
     *
     * @throws ValidationException
     */
    public function handle(Benefit $benefit): Benefit
    {
        if ($benefit->isBase()) {
            throw ValidationException::withMessages(['benefit' => 'Las prestaciones base no se archivan.']);
        }

        $benefit->update(['archived_at' => now()]);
        Log::info('compensation.benefit_archived', ['benefit_id' => $benefit->id]);

        return $benefit;
    }
}
