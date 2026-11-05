<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ChannelStatus;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;

/**
 * What the channel page of the company shows (board V4-Quejas-Canal): its state and settings, who receives each kind of
 * complaint by name, the people who could be designated, and the link with its QR. Counts and names; nothing of a case.
 */
final readonly class ChannelPages
{
    public function __construct(
        private UnassignedCases $unassigned,
        private ChannelStatusPages $status,
        private ActiveHandlers $active,
        private HandlerCoverage $coverage,
        private Members $members,
        private QrCodeImage $qr,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        $channel = ComplaintChannel::query()->first();

        return [
            'unassigned' => $this->unassigned->count(),
            ...$this->status->status($channel),
            'channel' => [
                'status' => ($channel?->status() ?? ChannelStatus::Inactive)->value,
                'url' => $channel?->enabled_at === null ? null : $channel->publicUrl(),
                'qr' => $channel?->enabled_at === null ? null : $this->qr->dataUri($channel->publicUrl()),
                'publishedOn' => $channel?->published_on?->toDateString(),
                'codeRotatedOn' => $channel?->access_code_rotated_on?->toDateString(),
            ],
            ...$this->handlers(),
            'settings' => [
                'procedure' => $channel->procedure ?? ProcedureModel::text(),
                'acknowledgeWithinDays' => $channel->acknowledge_within_days ?? config()->integer('complaints.deadlines.acknowledge_days'),
                'resolveWithinDays' => $channel->resolve_within_days ?? config()->integer('complaints.deadlines.resolve_days'),
                'retentionMonths' => $channel->retention_months ?? config()->integer('complaints.retention.default_months'),
            ],
            'bounds' => [
                'acknowledgeWithinDays' => ['min' => 1, 'max' => 15],
                'resolveWithinDays' => ['min' => 7, 'max' => 180],
                'retentionMonths' => ['min' => config()->integer('complaints.retention.min_months'), 'max' => config()->integer('complaints.retention.max_months')],
            ],
        ];
    }

    /**
     * Who receives each kind of complaint when the person names no center (the handlers of the whole company), by name, and
     * the people who may be designated with their role: the page tells which of them the conflict leaves for each kind.
     *
     * @return array{coverage: list<array{kind: string, label: string, excludedRole: string|null, handlers: list<array{name: string, roleLabel: string}>}>, handlersCount: int, candidates: list<array{value: string, label: string, role: string, roleLabel: string}>}
     */
    private function handlers(): array
    {
        $active = $this->active->all();
        $people = [];

        foreach ($active as ['member' => $member]) {
            $people[$member->userId] = ['name' => $member->name, 'roleLabel' => $member->role->label()];
        }

        return [
            'coverage' => array_map(fn (RespondentKind $kind): array => [
                'kind' => $kind->value,
                'label' => $kind->shortLabel(),
                'excludedRole' => $kind->excludedRole(),
                'handlers' => array_map(fn (int $id): array => $people[$id], $this->coverage->handlerIds($kind, null)),
            ], RespondentKind::cases()),
            'handlersCount' => count($active),
            'candidates' => array_map(fn (MemberSummary $member): array => [
                'value' => (string) $member->userId,
                'label' => $member->name,
                'role' => $member->role->value,
                'roleLabel' => $member->role->label(),
            ], $this->members->complaintHandlers()),
        ];
    }
}
