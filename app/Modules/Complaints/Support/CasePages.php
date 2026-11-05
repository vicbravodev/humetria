<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Enums\IdentityMode;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintEntry;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The page of one case, for a handler assigned to it: the only place where the words of a complaint reach a screen. An
 * anonymous complaint has no field of identity at all, not even an empty one.
 */
final readonly class CasePages
{
    private const int ENTRIES_PER_PAGE = 20;

    public function __construct(private Members $members, private WorkCenters $workCenters, private HandlerCoverage $coverage, private EntryText $texts) {}

    /**
     * @return array<string, mixed>
     */
    public function show(Complaint $complaint): array
    {
        $identity = $complaint->identity_mode === IdentityMode::Confidential
            ? ['reporterName' => $complaint->reporter_name, 'reporterContact' => $complaint->reporter_contact]
            : [];

        return [
            'case' => [
                'id' => $complaint->id,
                'reference' => $complaint->reference,
                'type' => $complaint->type->value,
                'typeLabel' => $complaint->type->label(),
                'categoryLabel' => $complaint->category?->label(),
                'center' => $complaint->work_center_id === null ? null : $this->workCenters->profile($complaint->work_center_id)->name ?? null,
                'kindLabel' => $complaint->respondent_kind->shortLabel(),
                'identity' => $complaint->identity_mode->value,
                ...$identity,
                'description' => $complaint->description,
                'respondentText' => $complaint->respondent_text,
                'involvedText' => $complaint->involved_text,
                'receivedOn' => $complaint->received_on->toDateString(),
                'occurredOn' => $complaint->occurred_on?->toDateString(),
                'status' => $complaint->status->value,
                'statusLabel' => $complaint->status->label(),
                'onHold' => $complaint->on_hold,
                'resolution' => $complaint->resolution?->label(),
            ],
            'access' => $this->access($complaint),
            ...$this->thread($complaint),
            ...$this->deadlines($complaint),
            'can' => [
                'acknowledge' => $complaint->acknowledged_at === null && $complaint->status === ComplaintStatus::New,
                'review' => $complaint->status === ComplaintStatus::New,
                'write' => $complaint->status !== ComplaintStatus::Closed,
                'close' => $complaint->status !== ComplaintStatus::Closed,
                'reopen' => $complaint->status === ComplaintStatus::Closed,
            ],
            'candidates' => $this->candidates($complaint),
            'resolutions' => array_map(fn (ComplaintResolution $resolution): array => ['value' => $resolution->value, 'label' => $resolution->label()], ComplaintResolution::handlerChoices()),
        ];
    }

    /**
     * The thread of the case in its three parts (board V4-Quejas-Caso): the conversation the person reads, the notes only the
     * handlers read and the log of what happened to the case. Each one the newest entry first, a page at a time.
     *
     * @return array{conversation: LengthAwarePaginator<int, array<string, mixed>>, notes: LengthAwarePaginator<int, array<string, mixed>>, log: LengthAwarePaginator<int, array<string, mixed>>}
     */
    private function thread(Complaint $complaint): array
    {
        $names = $this->names();
        $part = fn (array $kinds, string $page): LengthAwarePaginator => ComplaintEntry::query()->where('complaint_id', $complaint->id)
            ->whereIn('kind', $kinds)->orderByDesc('position')
            ->paginate(self::ENTRIES_PER_PAGE, ['*'], $page)->withQueryString()
            ->through(fn (ComplaintEntry $entry): array => $this->row($entry, $names));

        return [
            'conversation' => $part([EntryKind::ReporterMessage->value, EntryKind::HandlerMessage->value], 'conversation'),
            'notes' => $part([EntryKind::InternalNote->value], 'notes'),
            'log' => $part([EntryKind::StatusChange->value, EntryKind::AssignmentChange->value, EntryKind::Recusal->value], 'log'),
        ];
    }

    /**
     * The deadlines the company set itself (criterio C-49), counted from the day the case came, and the draft of the
     * acknowledgement that promises the answer by its day. Days only: an anonymous case never shows an hour.
     *
     * @return array{acknowledgement: string, today: string, deadlines: array{acknowledgeBy: string, respondBy: string, acknowledgedOn: string|null, closedOn: string|null}}
     */
    private function deadlines(Complaint $complaint): array
    {
        $channel = ComplaintChannel::query()->find($complaint->channel_id);
        $respondBy = $complaint->received_on->addDays($channel->resolve_within_days ?? config()->integer('complaints.deadlines.resolve_days'));
        $zone = config()->string('app.display_timezone');

        return [
            'acknowledgement' => "{$complaint->type->acknowledgementOpening()} una persona designada que no está involucrada. Le responderemos aquí a más tardar el {$respondBy->isoFormat('D [de] MMMM')}. Puede escribirnos si quiere agregar algo.",
            'today' => Dates::today()->toDateString(),
            'deadlines' => [
                'acknowledgeBy' => $complaint->received_on->addDays($channel->acknowledge_within_days ?? config()->integer('complaints.deadlines.acknowledge_days'))->toDateString(),
                'respondBy' => $respondBy->toDateString(),
                'acknowledgedOn' => $complaint->acknowledged_at?->setTimezone($zone)->toDateString(),
                'closedOn' => $complaint->closed_on?->toDateString(),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function row(ComplaintEntry $entry, array $names): array
    {
        $zone = config()->string('app.display_timezone');

        return [
            'id' => $entry->id,
            'kind' => $entry->kind,
            'author' => $entry->kind === EntryKind::ReporterMessage->value ? 'La persona' : ($entry->author_id === null ? null : ($names[$entry->author_id] ?? 'Una persona')),
            'when' => $entry->occurred_on?->toDateString() ?? $entry->occurred_at?->setTimezone($zone)->toDateString(),
            'time' => $entry->occurred_at?->setTimezone($zone)->format('H:i'),
            'text' => $this->texts->of($entry, $names),
        ];
    }

    /**
     * The handlers who could be given the case: those the conflict leaves and who do not have it yet, by name.
     *
     * @return list<array{value: string, label: string}>
     */
    private function candidates(Complaint $complaint): array
    {
        $has = ComplaintAssignment::query()->where('complaint_id', $complaint->id)->whereNull('ended_on')->get()->map(fn (ComplaintAssignment $assignment): int => $assignment->user_id)->all();
        $names = $this->names();
        $candidates = [];

        foreach ($this->coverage->handlerIds($complaint->respondent_kind, $complaint->work_center_id) as $userId) {
            if (! in_array($userId, $has, true)) {
                $candidates[] = ['value' => (string) $userId, 'label' => $names[$userId] ?? 'Una persona'];
            }
        }

        return array_values(collect($candidates)->sortBy('label')->all());
    }

    /**
     * @return array<int, string>
     */
    private function names(): array
    {
        $names = [];

        foreach ($this->members->all() as $member) {
            $names[$member->userId] = $member->name;
        }

        return $names;
    }

    /**
     * Who has the case, by name: the handlers assigned to it now.
     *
     * @return list<string>
     */
    private function access(Complaint $complaint): array
    {
        $ids = ComplaintAssignment::query()->where('complaint_id', $complaint->id)->whereNull('ended_on')->get()->map(fn (ComplaintAssignment $assignment): int => $assignment->user_id)->all();
        $names = [];

        foreach ($this->members->all() as $member) {
            if (in_array($member->userId, $ids, true)) {
                $names[] = $member->name;
            }
        }

        sort($names);

        return $names;
    }
}
