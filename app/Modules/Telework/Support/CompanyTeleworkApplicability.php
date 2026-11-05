<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Actions\DeclareApplicability;
use App\Modules\Telework\Contracts\TeleworkApplicability;
use App\Modules\Telework\Models\TeleworkProfile;

final readonly class CompanyTeleworkApplicability implements TeleworkApplicability
{
    public function __construct(private DeclareApplicability $declare) {}

    public function declarations(array $workCenterIds): array
    {
        $declarations = [];

        foreach (TeleworkProfile::query()->whereIn('work_center_id', $workCenterIds)->get(['work_center_id', 'has_teleworkers']) as $profile) {
            $declarations[$profile->work_center_id] = $profile->has_teleworkers;
        }

        return $declarations;
    }

    public function declare(string $workCenterId, bool $hasTeleworkers, int $userId): void
    {
        $this->declare->handle($workCenterId, $hasTeleworkers, $userId);
    }
}
