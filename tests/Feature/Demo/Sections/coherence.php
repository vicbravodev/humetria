<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Participation;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * @param  Collection<array-key, mixed>  $ids
 * @return list<mixed>
 */
function demoSorted(Collection $ids): array
{
    return $ids->sort()->values()->all();
}

/**
 * The company panel's GET pages the demo's members open, by name: every one that needs only the company, and one page
 * of each kind that needs a record (the offices, the plant's evaluations, the climate survey and the last pulse, the first
 * proposal). Left out, because the panel never opens them bare: the ones that answer a query the page sends (the places
 * of an address, the attendance report's export, the comparison of chosen proposals); the certificates of a center with
 * nobody standing or teleworking (the offices have both); and the downloads of a file the demo never uploads (the act of
 * the Comisión, the internal regulation).
 *
 * @param  array<string, string>  $records  route parameter → id; `pulse` is the survey of the pulse pages
 * @return array<string, string> route name → url
 */
function demoPanelPages(Tenant $tenant, array $records): array
{
    $left = [
        'tenant.attendance.places', 'tenant.attendance.report.export', 'tenant.compensation.scenarios.compare',
        'tenant.seating.certificate', 'tenant.telework.certificate', 'tenant.telework.committee.act', 'tenant.seating.regulation.file',
    ];
    $pages = [];

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $name = (string) $route->getName();
        $extra = array_values(array_diff($route->parameterNames(), ['tenant']));
        $ids = str_starts_with($name, 'tenant.pulse.') ? ['survey' => $records['pulse']] + $records : $records;

        if (! str_starts_with($name, 'tenant.') || in_array($name, $left, true) || ! in_array('GET', $route->methods(), true) || array_diff($extra, array_keys($ids)) !== []) {
            continue;
        }

        $pages[$name] = route($name, ['tenant' => $tenant, ...array_intersect_key($ids, array_flip($extra))]);
    }

    ksort($pages);

    return $pages;
}

/*
 * The coherence groups: the numbers of one module agree with another's, every panel page opens, no page shows an answer.
 * DemoStoriesTest includes this file inside its one test, on the prepared demo, so `$this` is that test.
 */
return [
    'has the same 180 people in the org chart, the centers, attendance, leave and the campaigns' => function (): void {
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        $employees = Employee::query()->get()->keyBy('id');
        $ids = demoSorted($employees->keys());
        $centerOf = $employees->map(fn (Employee $employee): string => (string) $employee->work_center_id);
        $byCenter = $centerOf->countBy();
        $open = Campaign::query()->where('kind', CampaignKind::Nom035Evaluation)->where('status', CampaignStatus::Active)->get();
        $participants = Participant::query()->whereIn('campaign_id', $open->pluck('id'))->get();
        $pulse = Survey::query()->where('status', SurveyStatus::Active)->sole();
        $policy = Document::query()->where('kind', DocumentKind::Policy)->sole();

        // the org chart and the four centers
        expect($ids)->toHaveCount(180)
            ->and($byCenter->sum())->toBe(180)
            ->and(demoSorted($byCenter->keys()))->toBe(demoSorted(WorkCenter::query()->pluck('id')))
            // attendance: a schedule and marks for each of them, each mark in the person's own center
            ->and(demoSorted(AttendanceSchedule::query()->pluck('employee_id')))->toBe($ids)
            ->and(demoSorted(AttendanceMark::query()->distinct()->pluck('employee_id')))->toBe($ids)
            ->and(AttendanceMark::query()->toBase()->distinct()->get(['employee_id', 'work_center_id'])
                ->reject(fn (object $mark): bool => $centerOf[$mark->employee_id] === $mark->work_center_id)->all())->toBe([])
            // the open evaluations of the four centers: each person once, in their own center's, as many as it says it has
            ->and(demoSorted($participants->pluck('employee_id')))->toBe($ids)
            ->and($open->sum('workers'))->toBe(180)
            // the open pulse and the policy of disclosure: everyone asked
            ->and(demoSorted(Invitation::query()->where('survey_id', $pulse->id)->pluck('employee_id')))->toBe($ids)
            ->and(demoSorted(Acknowledgment::query()->where('document_id', $policy->id)->pluck('employee_id')))->toBe($ids);

        foreach ($open as $campaign) {
            $people = $participants->where('campaign_id', $campaign->id);

            expect($people->map(fn (Participant $participant): string => $centerOf[$participant->employee_id])->unique()->values()->all())->toBe([$campaign->work_center_id])
                ->and($people->count())->toBe($campaign->workers)
                ->and($campaign->workers)->toBe($byCenter[$campaign->work_center_id]);
        }

        // leave: a vacation period for everyone with a year since their hire in the org chart, and for nobody else
        $yearOrMore = $employees->filter(fn (Employee $employee): bool => CarbonImmutable::parse($employee->hired_on->toDateString(), DemoClock::TIMEZONE)->addYearNoOverflow()->lessThanOrEqualTo($today));

        expect(demoSorted(LeaveEntitlement::query()->distinct()->pluck('employee_id')))->toBe(demoSorted($yearOrMore->keys()));
    },

    'has answers plus pending equal to invitations in every campaign and survey' => function (): void {
        $campaigns = Campaign::query()->get();

        expect($campaigns->count())->toBeGreaterThanOrEqual(9);

        foreach ($campaigns as $campaign) {
            // How the panel counts a NOM-035 campaign (a Ley Silla survey has no such record): the same as its guides say.
            $record = in_array($campaign->kind, CampaignKind::nom035(), true) ? resolve(CampaignRecords::class)->find($campaign->id) : null;
            $statuses = ParticipantInstrument::query()->whereIn('participant_id', Participant::query()->where('campaign_id', $campaign->id)->select('id'))->get()->groupBy('instrument');
            $people = Participant::query()->where('campaign_id', $campaign->id)->count();

            expect($record === null ? $campaign->kind : array_keys($record->invited))->toEqual($record === null ? CampaignKind::SeatingSurvey : $campaign->instruments);

            foreach ($campaign->instruments as $instrument) {
                $guides = $statuses->get($instrument, collect());
                $answered = $guides->where('status', InstrumentStatus::Completed)->count();
                $pending = $guides->where('status', InstrumentStatus::Pending)->count();

                expect($guides->where('status', InstrumentStatus::Cancelled))->toBeEmpty()
                    ->and($answered + $pending)->toBe($people)
                    ->and($record === null ? $answered : ($record->answered[$instrument] ?? 0))->toBe($answered)
                    ->and($record === null ? $people : ($record->invited[$instrument] ?? 0))->toBe($people);

                // an anonymous questionnaire leaves one keyless row per answer, and none for the pending
                if ($instrument !== 'nom035.guide-i') {
                    expect(AnonymousResponse::query()->where('campaign_id', $campaign->id)->where('instrument', $instrument)->count())->toBe($answered);
                }
            }
        }

        $surveys = Survey::query()->get();

        expect($surveys->pluck('kind')->countBy(fn (SurveyKind $kind): string => $kind->value)->all())->toBe(['climate' => 1, 'pulse' => 3]);

        foreach ($surveys as $survey) {
            $progress = resolve(Participation::class)->of($survey, true);
            $invitations = Invitation::query()->where('survey_id', $survey->id);

            expect($progress['answered'] + $progress['pending'])->toBe($progress['invited'])
                ->and($progress['invited'])->toBe((clone $invitations)->count())
                ->and((clone $invitations)->where('cancelled', true)->count())->toBe(0)
                ->and(Response::query()->where('survey_id', $survey->id)->count())->toBe($progress['answered']);
        }
    },

    'reaches every page the owner and HR see, with no answer of anyone in its props' => function (): void {
        $tenant = Tenant::query()->findOrFail(resolve(DemoCompanies::class)->id());
        $plant = WorkCenter::query()->where('name', DemoRoster::CENTERS['plant'])->sole();
        $records = [
            'workCenter' => WorkCenter::query()->where('name', DemoRoster::CENTERS['offices'])->sole()->id,
            'campaign' => Campaign::query()->where('work_center_id', $plant->id)->where('kind', CampaignKind::Nom035Evaluation)->where('status', CampaignStatus::Closed)->sole()->id,
            'survey' => Survey::query()->where('kind', SurveyKind::Climate)->sole()->id,
            'pulse' => Survey::query()->where('kind', SurveyKind::Pulse)->where('status', SurveyStatus::Closed)->orderByDesc('closed_at')->firstOrFail()->id,
            'scenario' => Scenario::query()->orderBy('created_at')->firstOrFail()->id,
        ];
        $pages = demoPanelPages($tenant, $records);

        expect(count($pages))->toBeGreaterThan(100);

        // What a page answers when it is not 200 or a redirect, by design: the reviews of NOM-037 are the Comisión's (HR
        // sits on it, the owner does not), and the members and the plan are the owner's.
        $expected = [
            DemoRoster::OWNER.' tenant.telework.reviews.index' => 403,
            DemoRoster::HR.' tenant.members.index' => 403,
            DemoRoster::HR.' tenant.members.invitations.create' => 403,
            DemoRoster::HR.' tenant.plan' => 403,
        ];
        // Every page that breaks, at once: the status it gave, and the exception if one was thrown.
        $broken = [];

        foreach ([DemoRoster::OWNER, DemoRoster::HR] as $member) {
            $this->actingAs(User::query()->where('email', DemoRoster::person($member)->email)->sole());

            foreach ($pages as $name => $url) {
                $response = $this->get($url);
                // A download streams: it carries no exception of its own.
                $exception = property_exists($response->baseResponse, 'exception') ? $response->baseResponse->exception : null;

                $status = $response->getStatusCode();

                if (isset($expected["{$member} {$name}"])) {
                    expect($status)->toBe($expected["{$member} {$name}"], "{$member} {$name}");

                    continue;
                }

                if ($exception !== null || ! in_array($status, [200, 302], true)) {
                    $broken[] = "{$member} {$name}: {$status} ".($exception instanceof Throwable ? $exception::class.' '.$exception->getMessage() : '');

                    continue;
                }

                $view = property_exists($response->baseResponse, 'original') ? $response->baseResponse->original : null;

                if ($view instanceof View && is_array($view->getData()['page'] ?? null)) {
                    $props = $view->getData()['page']['props'];

                    section("{$member} {$name}", function () use ($props): void {
                        expectNoIndividualResponses($props);
                        expectNoEngagementResponses($props);
                    });
                }
            }
        }

        expect($broken)->toBe([]);

        auth()->logout();
    },

    'reads campaign progress and results in a fixed number of queries' => function (): void {
        $tenant = Tenant::query()->findOrFail(resolve(DemoCompanies::class)->id());
        $plant = WorkCenter::query()->where('name', DemoRoster::CENTERS['plant'])->sole();
        $evaluation = fn (CampaignStatus $status): string => Campaign::query()->where('work_center_id', $plant->id)->where('kind', CampaignKind::Nom035Evaluation)->where('status', $status)->sole()->id;
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $count = function (string $url) use (&$queries): int {
            $this->get($url)->assertOk();
            $queries = 0;
            $this->get($url)->assertOk();

            return $queries;
        };
        $this->actingAs(User::query()->where('email', DemoRoster::person(DemoRoster::HR)->email)->sole());

        // Measured on the demo's 84 people of the plant: a fixed count, nothing read per person or per answer (the same pages
        // with a handful of people: tests/Feature/Campaigns/CampaignPagesTest.php, tests/Feature/Results/ResultsPageTest.php).
        expect($count(route('tenant.campaigns.show', [$tenant, $evaluation(CampaignStatus::Active)])))->toBe(28)
            ->and($count(route('tenant.campaigns.results', [$tenant, $evaluation(CampaignStatus::Closed)])))->toBe(24);

        auth()->logout();
    },
];
