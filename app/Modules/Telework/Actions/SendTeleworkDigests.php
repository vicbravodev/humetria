<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Enums\TrainingStanding;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\DigestDelivery;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use App\Modules\Telework\Notifications\TeleworkDigest;
use App\Modules\Telework\Support\CommitteeGate;
use App\Modules\Telework\Support\TrainingDue;
use App\Modules\Telework\Support\WorkplaceStanding;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * The daily NOM-037 email to the owner (RH) and to each Comisión member: counts of what waits for them, in sections that
 * appear only when they are not empty. Owner: workplaces that expired since their last digest, people whose yearly
 * training is due or overdue, unreviewed notices and lists. Comisión member, for the centers they sit on: expired
 * workplaces, lists, notices and overdue action plans. Each recipient gets at most one a day (`telework_digest_deliveries`,
 * written in the same transaction as the dispatch) and none when everything is empty. Blocked companies get nothing.
 */
final readonly class SendTeleworkDigests
{
    public function __construct(
        private Companies $companies,
        private Members $members,
        private CommitteeGate $gate,
        private WorkplaceStanding $standing,
        private TrainingDue $training,
    ) {}

    /**
     * @return int how many digests were sent.
     */
    public function handle(): int
    {
        $sent = ['owner' => 0, 'committee' => 0];

        foreach (Teleworker::query()->withoutGlobalScopes()->distinct()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (Teleworker $teleworker): string => $teleworker->tenant_id) as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, &$sent): void {
                    $company = $this->companies->enter($tenantId);

                    if (! $company->blocked) {
                        $counts = $this->digest($company->tradeName, $company->slug);
                        $sent['owner'] += $counts['owner'];
                        $sent['committee'] += $counts['committee'];
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' digests from going out.
                report($failure);
            }
        }

        Log::info('telework.digests_sent', $sent);

        return $sent['owner'] + $sent['committee'];
    }

    /**
     * @return array{owner: int, committee: int}
     */
    private function digest(string $company, string $slug): array
    {
        $sent = ['owner' => 0, 'committee' => 0];
        $owner = $this->companies->contact();

        if ($owner !== null) {
            $sent['owner'] = $this->deliver('owner', $owner, $company, null, ['expired', 'training', 'notices', 'lists'], null, $slug);
        }

        foreach ($this->members->reviewers() as $member) {
            $centers = $this->gate->workCenterIds($member->userId);

            if ($centers !== []) {
                $sent['committee'] += $this->deliver("user:{$member->userId}", $member->email, $company, $member->name, ['expired', 'lists', 'notices', 'plans'], $centers, $slug);
            }
        }

        return $sent;
    }

    /**
     * @param  list<'expired'|'training'|'notices'|'lists'|'plans'>  $sections
     * @param  list<string>|null  $centers  null: every center of the company.
     * @return int 1 when the digest went, 0 when there was nothing to say or it already went today.
     */
    private function deliver(string $recipient, string $email, string $company, ?string $name, array $sections, ?array $centers, string $slug): int
    {
        $now = CarbonImmutable::now();
        $today = $now->setTimezone(config()->string('app.display_timezone'))->startOfDay();
        $since = DigestDelivery::query()->where('recipient', $recipient)->where('day', '<', $today->toDateString())->orderByDesc('delivered_at')->first(['delivered_at'])->delivered_at ?? $now->subDay();
        $lines = $this->lines($sections, $centers, $since, $now, $today);

        if ($lines === []) {
            return 0;
        }

        return DB::transaction(function () use ($recipient, $email, $company, $name, $lines, $centers, $slug, $today, $now): int {
            // The unique key settles two runs racing: only the one that wrote the row sends.
            $written = DB::table('telework_digest_deliveries')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->companies->current()->id,
                'recipient' => $recipient,
                'day' => $today->toDateString(),
                'delivered_at' => $now,
            ]);

            if ($written === 0) {
                return 0;
            }

            // ponytail: the owner has no center dashboard yet; Task 15 replaces this link with it.
            $center = $centers[0] ?? Teleworker::query()->orderBy('created_at')->orderBy('id')->first(['work_center_id'])?->work_center_id;
            Notification::route('mail', $email)->notify(new TeleworkDigest($company, $name, $lines, $center === null ? route('tenant.telework.documents.index', $slug) : route('tenant.telework.reviews.index', [$slug, $center])));

            return 1;
        });
    }

    /**
     * @param  list<'expired'|'training'|'notices'|'lists'|'plans'>  $sections
     * @param  list<string>|null  $centers
     * @return list<string>
     */
    private function lines(array $sections, ?array $centers, CarbonImmutable $since, CarbonImmutable $now, CarbonImmutable $today): array
    {
        $counts = [
            'expired' => fn (): int => $this->expired($centers, $since, $now),
            'training' => fn (): int => $this->trainingDue($centers, $today),
            'notices' => fn (): int => WorkerNotice::query()->whereNull('reviewed_at')->whereIn('teleworker_id', $this->teleworkers($centers)->select('id'))->count(),
            'lists' => fn (): int => $this->listsToReview($centers),
            'plans' => fn (): int => $this->overduePlans($centers, $today),
        ];
        $texts = [
            'expired' => '{1} :count lugar de trabajo venció y quedó pendiente de validar.|[2,*] :count lugares de trabajo vencieron y quedaron pendientes de validar.',
            'training' => '{1} :count persona tiene su capacitación por vencer o vencida.|[2,*] :count personas tienen su capacitación por vencer o vencida.',
            'notices' => '{1} :count aviso por revisar.|[2,*] :count avisos por revisar.',
            'lists' => '{1} :count lista por revisar.|[2,*] :count listas por revisar.',
            'plans' => '{1} :count plan de acción vencido.|[2,*] :count planes de acción vencidos.',
        ];
        $lines = [];

        foreach ($sections as $section) {
            $count = $counts[$section]();

            if ($count > 0) {
                $lines[] = trans_choice($texts[$section], $count);
            }
        }

        return $lines;
    }

    /**
     * @param  list<string>|null  $centers
     * @return Builder<Teleworker>
     */
    private function teleworkers(?array $centers): Builder
    {
        return Teleworker::query()->when($centers !== null, fn (Builder $query) => $query->whereIn('work_center_id', $centers));
    }

    /**
     * Workplaces still in use whose validation lapsed after the previous digest.
     *
     * @param  list<string>|null  $centers
     */
    private function expired(?array $centers, CarbonImmutable $since, CarbonImmutable $now): int
    {
        $workplaces = Workplace::query()->whereNull('retired_at')->whereIn('teleworker_id', $this->teleworkers($centers)->where('status', TeleworkerStatus::Active)->select('id'))->get();

        $reviews = $this->standing->latestReviews(array_values($workplaces->map(fn (Workplace $workplace): string => $workplace->id)->all()));

        return count(array_filter($reviews, function (WorkplaceReview $review) use ($since, $now): bool {
            if (! $review->decision->validates()) {
                return false;
            }

            $expires = $this->standing->expiry($review);

            return $expires->greaterThan($since) && $expires->lessThanOrEqualTo($now);
        }));
    }

    /**
     * Active people whose yearly training is within its warning month or past due.
     *
     * @param  list<string>|null  $centers
     */
    private function trainingDue(?array $centers, CarbonImmutable $today): int
    {
        $people = array_values($this->teleworkers($centers)->where('status', TeleworkerStatus::Active)->get()->all());

        return count(array_filter($this->training->schedule($people), fn (array $row): bool => $this->training->standing($row['due'], $today) !== TrainingStanding::Ok));
    }

    /**
     * Sent checklists no review answers yet, of workplaces still in use.
     *
     * @param  list<string>|null  $centers
     */
    private function listsToReview(?array $centers): int
    {
        return ChecklistApplication::query()->whereNotNull('submitted_at')->whereNull('cancelled_at')
            ->whereNotIn('id', WorkplaceReview::query()->whereNotNull('application_id')->select('application_id'))
            ->whereIn('workplace_id', Workplace::query()->whereNull('retired_at')->whereIn('teleworker_id', $this->teleworkers($centers)->select('id'))->select('id'))
            ->count();
    }

    /**
     * Workplaces whose latest review is a validation with a plan due before today: nobody reviewed them again.
     *
     * @param  list<string>|null  $centers
     */
    private function overduePlans(?array $centers, CarbonImmutable $today): int
    {
        return WorkplaceReview::query()->where('decision', ReviewDecision::ValidWithPlan)->where('plan_due_on', '<', $today->toDateString())
            ->whereRaw('not exists (select 1 from workplace_reviews later where later.workplace_id = workplace_reviews.workplace_id and (later.decided_at, later.id) > (workplace_reviews.decided_at, workplace_reviews.id))')
            ->whereIn('workplace_id', Workplace::query()->whereNull('retired_at')->whereIn('teleworker_id', $this->teleworkers($centers)->select('id'))->select('id'))
            ->count();
    }
}
