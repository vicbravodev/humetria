<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Models\Complaint;

/**
 * The summary of the channel for who manages it (criterio C-47): totals by type and by status, every cell under the minimum of
 * people hidden with the complementary suppression, and never a breakdown by center, category, who it is against or day: the
 * numbers say that the channel operates, not who used it. The cases already purged count, through their skeleton.
 */
final readonly class ComplaintStatistics
{
    /**
     * @return array{minimum: int, total: int|null, byType: list<array{value: string, label: string, count: int|null}>, byStatus: list<array{value: string, label: string, count: int|null}>}
     */
    public function summary(): array
    {
        $minimum = config()->integer('complaints.min_group');
        $types = [];
        $statuses = [];

        foreach (ComplaintType::cases() as $type) {
            $types[$type->value] = Complaint::query()->where('type', $type->value)->count();
        }

        foreach (ComplaintStatus::cases() as $status) {
            $statuses[$status->value] = Complaint::query()->where('status', $status->value)->count();
        }

        $byType = MinimumGroup::apply($types, $minimum);
        $byStatus = MinimumGroup::apply($statuses, $minimum);

        return [
            'minimum' => $minimum,
            'total' => MinimumGroup::total(array_sum($types), $minimum),
            'byType' => array_map(fn (ComplaintType $type): array => ['value' => $type->value, 'label' => $type->label(), 'count' => $byType[$type->value]], ComplaintType::cases()),
            'byStatus' => array_map(fn (ComplaintStatus $status): array => ['value' => $status->value, 'label' => $status->pluralLabel(), 'count' => $byStatus[$status->value]], ComplaintStatus::cases()),
        ];
    }
}
