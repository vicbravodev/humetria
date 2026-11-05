<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Actions\DeclareProfile;
use App\Modules\Seating\Contracts\SeatingApplicability;
use App\Modules\Seating\Data\SeatingDeclaration;
use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;

final readonly class CompanySeatingApplicability implements SeatingApplicability
{
    public function __construct(private DeclareProfile $declare) {}

    public function declarations(array $workCenterIds): array
    {
        $declarations = [];

        foreach (SeatingProfile::query()->whereIn('work_center_id', $workCenterIds)->get(['work_center_id', 'sector', 'no_standing_positions', 'declared_on']) as $profile) {
            $declarations[$profile->work_center_id] = new SeatingDeclaration(! $profile->no_standing_positions, $profile->sector?->value, str_replace('.', '', $profile->declared_on->isoFormat('D MMM YYYY')));
        }

        return $declarations;
    }

    public function declare(string $workCenterId, bool $standing, ?string $sector, int $userId): void
    {
        $before = SeatingProfile::query()->where('work_center_id', $workCenterId)->first(['sector', 'note']);
        $this->declare->handle($workCenterId, Sector::tryFrom($sector ?? '') ?? $before?->sector, ! $standing, $before?->note, $userId);
    }

    public function sectors(): array
    {
        return array_map(fn (Sector $sector): string => $sector->value, Sector::cases());
    }
}
