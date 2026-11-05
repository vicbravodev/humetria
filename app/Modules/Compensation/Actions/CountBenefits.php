<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Modules\Compensation\Models\Benefit;

final readonly class CountBenefits
{
    /**
     * @return array{active: int, archived: int} of the whole catalog, whatever the search
     */
    public function handle(): array
    {
        return [
            'active' => Benefit::query()->whereNull('archived_at')->count(),
            'archived' => Benefit::query()->whereNotNull('archived_at')->count(),
        ];
    }
}
