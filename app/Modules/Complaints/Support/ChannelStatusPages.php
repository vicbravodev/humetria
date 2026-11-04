<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;

/**
 * What the channel's status page tells who manages it: what is still missing for it to serve, how many acknowledgements are
 * late and the summary with its minimum. Counts only; nothing of any case.
 */
final readonly class ChannelStatusPages
{
    public function __construct(private ActiveHandlers $active, private HandlerCoverage $coverage, private ComplaintStatistics $statistics) {}

    /**
     * @return array<string, mixed>
     */
    public function status(?ComplaintChannel $channel): array
    {
        return [
            'checklist' => $this->checklist($channel),
            'overdueAcknowledgements' => $channel === null ? 0 : $this->overdue($channel),
            'summary' => $this->statistics->summary(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, done: bool}>
     */
    private function checklist(?ComplaintChannel $channel): array
    {
        return [
            ['key' => 'procedure', 'label' => 'Procedimiento por escrito', 'done' => $channel !== null && trim($channel->procedure) !== ''],
            ['key' => 'handlers', 'label' => 'Al menos una persona que atienda', 'done' => $this->active->all() !== []],
            ['key' => 'coverage', 'label' => 'Alguien independiente para cada opción de «contra quién»', 'done' => ! in_array(0, $this->coverage->counts(), true)],
            ['key' => 'settings', 'label' => 'Plazos y conservación definidos', 'done' => $channel !== null],
        ];
    }

    /** New cases nobody acknowledged within the days the company set: a count, never which. */
    private function overdue(ComplaintChannel $channel): int
    {
        $limit = Dates::today()->subDays($channel->acknowledge_within_days)->toDateString();

        return Complaint::query()->where('status', ComplaintStatus::New->value)->whereNull('acknowledged_at')->whereDate('received_on', '<', $limit)->count();
    }
}
