<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\Benefit;
use Illuminate\Support\Facades\Log;

final readonly class RestoreBenefit
{
    public function handle(Benefit $benefit): Benefit
    {
        $benefit->update(['archived_at' => null]);
        Log::info('compensation.benefit_restored', ['benefit_id' => $benefit->id]);

        return $benefit;
    }
}
