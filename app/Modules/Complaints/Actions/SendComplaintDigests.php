<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintEntry;
use App\Modules\Complaints\Notifications\ComplaintsDigest;
use App\Modules\Complaints\Support\ActiveHandlers;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Data\MemberSummary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The hourly sweep: tells each handler, in one email, how many of their cases are new to them and how many have a reply of the
 * person, and, once a day, how many are past the deadlines the company set. Counts only. Each assignment remembers up to which
 * entry its handler was told (`notified_position`), so a piece of news goes out once. There is no email per submission: it would
 * say the exact hour an anonymous complaint arrived.
 */
final readonly class SendComplaintDigests
{
    public function __construct(private Companies $companies, private ActiveHandlers $active) {}

    /**
     * @return int how many digests were sent.
     */
    public function handle(): int
    {
        $sent = 0;
        $tenants = ComplaintChannel::query()->withoutGlobalScopes()->whereNotNull('enabled_at')->orderBy('tenant_id')->get(['tenant_id'])->map(fn (ComplaintChannel $channel): string => $channel->tenant_id);

        foreach ($tenants as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, &$sent): void {
                    $company = $this->companies->enter($tenantId);

                    if (! $company->blocked) {
                        $sent += $this->sweep($company);
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' digests from going out.
                report($failure);
            }
        }

        Log::info('complaints.digest_sent', ['count' => $sent]);

        return $sent;
    }

    private function sweep(CompanySummary $company): int
    {
        $channel = ComplaintChannel::query()->firstOrFail();
        $people = [];

        foreach ($this->active->all() as ['member' => $member]) {
            $people[$member->userId] = $member;
        }

        $sent = 0;

        foreach ($people as $member) {
            $sent += $this->digest($company, $channel, $member);
        }

        return $sent;
    }

    private function digest(CompanySummary $company, ComplaintChannel $channel, MemberSummary $member): int
    {
        $lines = DB::transaction(fn (): array => $this->newsFor($channel, $member));

        if ($lines === []) {
            return 0;
        }

        Notification::route('mail', $member->email)->notify(new ComplaintsDigest($company->tradeName, $member->name, $lines, route('tenant.complaints.index', $company->slug)));

        return 1;
    }

    /**
     * The lines of a handler's digest, and the assignments marked as told: what is new to them, what has a reply, and what is
     * past its deadline today.
     *
     * @return list<string>
     */
    private function newsFor(ComplaintChannel $channel, MemberSummary $member): array
    {
        $assignments = ComplaintAssignment::query()->where('user_id', $member->userId)->whereNull('ended_on')->get();
        $cases = Complaint::query()->whereIn('id', $assignments->map(fn (ComplaintAssignment $assignment): string => $assignment->complaint_id)->all())->get()->keyBy('id');
        $entries = ComplaintEntry::query()->whereIn('complaint_id', $cases->keys()->all())->get(['complaint_id', 'kind', 'position']);
        $new = 0;
        $replies = 0;

        foreach ($assignments as $assignment) {
            $case = $cases->get($assignment->complaint_id);

            if (! $case instanceof Complaint) {
                continue;
            }

            $mine = $entries->filter(fn (ComplaintEntry $entry): bool => $entry->complaint_id === $case->id);
            $last = $mine->isEmpty() ? 0 : $mine->max(fn (ComplaintEntry $entry): int => $entry->position);

            if ($case->status !== ComplaintStatus::Closed) {
                $new += $assignment->notified_position === null ? 1 : 0;
                $replies += $assignment->notified_position !== null && $mine->contains(fn (ComplaintEntry $entry): bool => $entry->kind === EntryKind::ReporterMessage->value && $entry->position > $assignment->notified_position) ? 1 : 0;
            }

            if ($assignment->notified_position !== $last) {
                $assignment->update(['notified_position' => $last]);
            }
        }

        return $this->lines($new, $replies, $this->reminders(array_values($cases->all()), $channel, $member));
    }

    /**
     * What is past its deadline, counted, if the handler was not already reminded today.
     *
     * @param  list<Complaint>  $cases
     * @return array{acknowledge: int, resolve: int}
     */
    private function reminders(array $cases, ComplaintChannel $channel, MemberSummary $member): array
    {
        $today = Dates::today()->toDateString();
        $acknowledge = 0;
        $resolve = 0;

        foreach ($cases as $case) {
            if ($case->status === ComplaintStatus::Closed) {
                continue;
            }

            $acknowledge += $case->status === ComplaintStatus::New && $case->acknowledged_at === null && $case->received_on->addDays($channel->acknowledge_within_days)->toDateString() < $today ? 1 : 0;
            $resolve += $case->received_on->addDays($channel->resolve_within_days)->toDateString() < $today ? 1 : 0;
        }

        if (($acknowledge > 0 || $resolve > 0) && ! Cache::add("complaints:reminded:{$channel->id}:{$member->userId}:{$today}", true, now()->addDay())) {
            return ['acknowledge' => 0, 'resolve' => 0];
        }

        return ['acknowledge' => $acknowledge, 'resolve' => $resolve];
    }

    /**
     * @param  array{acknowledge: int, resolve: int}  $reminders
     * @return list<string>
     */
    private function lines(int $new, int $replies, array $reminders): array
    {
        $texts = [
            [$new, '{1} Tienes :count caso nuevo.|[2,*] Tienes :count casos nuevos.'],
            [$replies, '{1} Tienes :count caso con una respuesta de la persona.|[2,*] Tienes :count casos con una respuesta de la persona.'],
            [$reminders['acknowledge'], '{1} Tienes :count caso sin acusar recibo pasado su plazo.|[2,*] Tienes :count casos sin acusar recibo pasado su plazo.'],
            [$reminders['resolve'], '{1} Tienes :count caso sin responder pasado su plazo.|[2,*] Tienes :count casos sin responder pasado su plazo.'],
        ];
        $lines = [];

        foreach ($texts as [$count, $text]) {
            if ($count > 0) {
                $lines[] = trans_choice($text, $count);
            }
        }

        return $lines;
    }
}
