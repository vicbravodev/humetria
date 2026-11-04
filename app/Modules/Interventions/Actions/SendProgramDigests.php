<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Notifications\ProgramDigest;
use App\Modules\Interventions\Support\ProgramClock;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\CompanySummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The Monday email (criterio C-36) to each company's owner (every action overdue or due within the next days, rows without
 * actions, Programas without responsible and follow-up evaluations overdue) and to each responsible member (their own actions
 * overdue or about to be due). Counts only; nothing is sent when everything is in order; a blocked company, a company with
 * no owner or one that fails never keeps the others' emails from going out.
 */
final readonly class SendProgramDigests
{
    public function __construct(private Companies $companies, private Members $members) {}

    /**
     * @return int how many emails were sent.
     */
    public function handle(): int
    {
        $sent = ['owner' => 0, 'responsible' => 0];

        foreach (array_filter(Program::query()->withoutGlobalScopes()->distinct()->orderBy('tenant_id')->pluck('tenant_id')->all(), is_string(...)) as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, &$sent): void {
                    $company = $this->companies->enter($tenantId);

                    if (! $company->blocked) {
                        $counts = $this->company($company);
                        $sent['owner'] += $counts['owner'];
                        $sent['responsible'] += $counts['responsible'];
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' emails from going out.
                report($failure);
            }
        }

        Log::info('intervention.digests_sent', $sent);

        return $sent['owner'] + $sent['responsible'];
    }

    /**
     * @return array{owner: int, responsible: int}
     */
    private function company(CompanySummary $company): array
    {
        $today = ProgramClock::today();
        $until = CarbonImmutable::parse($today)->addDays(config()->integer('interventions.due_soon_days'))->toDateString();
        $programs = Program::query()->get(['id', 'campaign_id', 'responsible_name']);
        $items = ProgramItem::query()->get(['id', 'program_id', 'follow_up_due_on', 'follow_up_done_on']);
        $open = ProgramAction::query()->whereIn('status', [ActionStatus::Planned, ActionStatus::InProgress])->get(['id', 'program_id', 'item_id', 'responsible_user_id', 'due_on']);
        $withActions = ProgramAction::query()->where('status', '!=', ActionStatus::Cancelled)->distinct()->pluck('item_id')->all();
        $index = route('tenant.interventions.index', $company->slug);
        $owner = $this->companies->contact();
        $sent = ['owner' => 0, 'responsible' => 0];

        $all = [
            'overdue' => $open->filter(fn (ProgramAction $action): bool => $action->due_on->toDateString() < $today)->count(),
            'soon' => $open->filter(fn (ProgramAction $action): bool => $action->due_on->toDateString() >= $today && $action->due_on->toDateString() <= $until)->count(),
            'rows' => $items->filter(fn (ProgramItem $item): bool => ! in_array($item->id, $withActions, true))->count(),
            'no_responsible' => $programs->filter(fn (Program $program): bool => $program->responsible_name === null)->count(),
            'follow_ups' => $items->filter(fn (ProgramItem $item): bool => $item->follow_up_due_on !== null && $item->follow_up_done_on === null && $item->follow_up_due_on->toDateString() < $today)->count(),
        ];

        if ($owner !== null) {
            $sent['owner'] = $this->deliver($owner, $company->tradeName, null, $all, $index);
        }

        foreach ($open->whereNotNull('responsible_user_id')->groupBy('responsible_user_id') as $userId => $mine) {
            $member = $this->members->find((int) $userId);

            if ($member === null || $member->email === $owner) {
                continue;
            }

            $own = [
                'overdue' => $mine->filter(fn (ProgramAction $action): bool => $action->due_on->toDateString() < $today)->count(),
                'soon' => $mine->filter(fn (ProgramAction $action): bool => $action->due_on->toDateString() >= $today && $action->due_on->toDateString() <= $until)->count(),
            ];
            $campaigns = array_values(array_unique($mine->map(fn (ProgramAction $action): string => $programs->firstWhere('id', $action->program_id)->campaign_id ?? '')->all()));
            $url = count($campaigns) === 1 ? route('tenant.campaigns.program', [$company->slug, $campaigns[0]]) : $index;
            $sent['responsible'] += $this->deliver($member->email, $company->tradeName, $member->name, $own, $url);
        }

        return $sent;
    }

    /**
     * @param  array<string, int>  $counts
     * @return int 1 when the email went, 0 when there was nothing to say.
     */
    private function deliver(string $email, string $company, ?string $name, array $counts, string $url): int
    {
        $lines = [];
        $texts = [
            'overdue' => '{1} :count acción vencida.|[2,*] :count acciones vencidas.',
            'soon' => '{1} :count acción vence en los próximos :days días.|[2,*] :count acciones vencen en los próximos :days días.',
            'rows' => '{1} :count fila sin acciones.|[2,*] :count filas sin acciones.',
            'no_responsible' => '{1} :count Programa sin responsable.|[2,*] :count Programas sin responsable.',
            'follow_ups' => '{1} :count evaluación posterior vencida.|[2,*] :count evaluaciones posteriores vencidas.',
        ];

        foreach ($texts as $key => $text) {
            if (($counts[$key] ?? 0) > 0) {
                $lines[] = trans_choice($text, $counts[$key], ['days' => config()->integer('interventions.due_soon_days')]);
            }
        }

        if ($lines === []) {
            return 0;
        }

        Notification::route('mail', $email)->notify(new ProgramDigest($company, $name, $lines, $url));

        return 1;
    }
}
