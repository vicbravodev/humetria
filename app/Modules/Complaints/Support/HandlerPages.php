<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ChannelStatus;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Tenancy\Contracts\WorkCenters;

/**
 * What the page of who handles the complaints shows: the handlers and who could be one, with their role. Who receives each kind
 * of complaint is on the channel's page (board V4-Quejas-Canal).
 */
final readonly class HandlerPages
{
    public function __construct(private ActiveHandlers $active, private HandlerCandidates $candidates, private WorkCenters $workCenters) {}

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $centers = [];

        foreach ($this->workCenters->all() as $center) {
            $centers[$center->id] = $center->name;
        }

        return [
            'channelStatus' => (ComplaintChannel::query()->first()?->status() ?? ChannelStatus::Inactive)->value,
            'handlers' => array_map(fn (array $row): array => [
                'id' => $row['handler']->id,
                'name' => $row['member']->name,
                'roleLabel' => $row['member']->role->label,
                'scope' => $row['handler']->work_center_id === null ? 'Toda la empresa' : ($centers[$row['handler']->work_center_id] ?? 'Un centro'),
                'designatedOn' => $row['handler']->designated_at->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            ], $this->active->all()),
            'candidates' => $this->candidates->all(),
        ];
    }
}
