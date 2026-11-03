<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ChannelStatus;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;

/** What the page of who handles the complaints shows: the handlers, who could be one and who would receive what. */
final readonly class HandlerPages
{
    public function __construct(private ActiveHandlers $active, private Members $members, private WorkCenters $workCenters, private HandlerCoverage $coverage) {}

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $centers = [];

        foreach ($this->workCenters->all() as $center) {
            $centers[$center->id] = $center->name;
        }

        $counts = $this->coverage->counts();

        return [
            'channelStatus' => (ComplaintChannel::query()->first()?->status() ?? ChannelStatus::Inactive)->value,
            'handlers' => array_map(fn (array $row): array => [
                'id' => $row['handler']->id,
                'name' => $row['member']->name,
                'roleLabel' => $row['member']->role->label(),
                'scope' => $row['handler']->work_center_id === null ? 'Toda la empresa' : ($centers[$row['handler']->work_center_id] ?? 'Un centro'),
                'designatedOn' => $row['handler']->designated_at->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            ], $this->active->all()),
            'candidates' => array_map(fn ($member): array => ['value' => (string) $member->userId, 'label' => "{$member->name} · {$member->role->label()}"], $this->members->complaintHandlers()),
            'coverage' => array_map(fn (RespondentKind $kind): array => ['kind' => $kind->value, 'label' => $kind->shortLabel(), 'count' => $counts[$kind->value]], RespondentKind::cases()),
        ];
    }
}
