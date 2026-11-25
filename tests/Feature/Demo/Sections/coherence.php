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
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Participation;
use App\Modules\Engagement\Support\Results\ManagerFeedback;
use App\Modules\Engagement\Support\Results\SurveyResults;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Enums\DocumentKind as TeleworkDocumentKind;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @param  Collection<array-key, mixed>  $ids
 * @return list<mixed>
 */
function demoSorted(Collection $ids): array
{
    return $ids->sort()->values()->all();
}

/**
 * The company panel's GET pages the demo's members open, keyed by route name (and by the value of a parameter that takes
 * several). Every route parameter needs a value in `$values` (or in `$byRoute` for that route), or the route in `$left`
 * with its reason: a new page cannot slip out of the crawl unnoticed.
 *
 * @param  array<string, list<string>>  $values  route parameter → the ids it takes
 * @param  array<string, array<string, list<string>>>  $byRoute  route name → the parameters that differ there
 * @param  array<string, string>  $left  route name → why the crawl does not open it
 * @return array{array<string, string>, list<string>} the pages (key → url), and the routes with a parameter nobody gave
 */
function demoPanelPages(Tenant $tenant, array $values, array $byRoute, array $left): array
{
    $pages = [];
    $unsupplied = [];

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $name = (string) $route->getName();

        if (! str_starts_with($name, 'tenant.') || ! in_array('GET', $route->methods(), true) || isset($left[$name])) {
            continue;
        }

        $ids = ($byRoute[$name] ?? []) + $values;
        $combinations = [[]];

        foreach (array_values(array_diff($route->parameterNames(), ['tenant'])) as $parameter) {
            if (! isset($ids[$parameter])) {
                $unsupplied[] = "{$name} {$parameter}";

                continue 2;
            }

            $combinations = array_merge(...array_map(fn (array $given): array => array_map(fn (string $id): array => [...$given, $parameter => $id], $ids[$parameter]), $combinations));
        }

        foreach ($combinations as $given) {
            $several = array_filter($given, fn (string $parameter): bool => count($ids[$parameter]) > 1, ARRAY_FILTER_USE_KEY);
            $pages[trim($name.' '.implode(' ', $several))] = route($name, ['tenant' => $tenant, ...$given]);
        }
    }

    ksort($pages);

    return [$pages, $unsupplied];
}

/*
 * The coherence groups: the numbers of one module agree with another's, every panel page opens, no page shows an answer.
 * DemoStoriesTest includes this file inside its one test, on the prepared demo, so `$this` is that test.
 */
return [
    'has nobody evaluated, marking or asked to confirm the policy before the day they were hired' => function (): void {
        $tenantId = resolve(DemoCompanies::class)->id();
        // By the local day of the event against the hire day (a date), as the panel shows both.
        $before = fn (string $sql): array => DB::table('employees as e')->where('e.tenant_id', $tenantId)->whereRaw($sql)->orderBy('e.employee_number')->distinct()->pluck('e.employee_number')->all();

        expect($before("exists (select 1 from campaign_participants p join campaigns c on c.id = p.campaign_id where p.employee_id = e.id and (c.launched_at at time zone 'America/Mexico_City')::date < e.hired_on)"))->toBe([])
            ->and($before("exists (select 1 from attendance_marks m where m.employee_id = e.id and (m.marked_at at time zone 'America/Mexico_City')::date < e.hired_on)"))->toBe([])
            ->and($before("exists (select 1 from disclosure_acknowledgments a where a.employee_id = e.id and (a.requested_at at time zone 'America/Mexico_City')::date < e.hired_on)"))->toBe([])
            ->and($before("exists (select 1 from disclosure_acknowledgments a where a.employee_id = e.id and (a.acknowledged_at at time zone 'America/Mexico_City')::date < e.hired_on)"))->toBe([])
            // whoever had a year with the company still has it: the same people have vacation periods
            ->and(count(array_filter(DemoRoster::people(), fn ($person): bool => $person->tenureDays >= 365)))->toBe(169);
    },

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
        $climate = Survey::query()->where('kind', SurveyKind::Climate)->sole();
        $pulse = Survey::query()->where('kind', SurveyKind::Pulse)->where('status', SurveyStatus::Closed)->orderByDesc('closed_at')->firstOrFail();
        // A manager whose results show: the first group of the survey that the minimum lets through, with answers about them.
        $manager = fn (Survey $survey): ?string => collect(array_keys(SurveyResults::for($survey)->managerGroups()->visible))
            ->first(fn (string $id): bool => ManagerFeedback::for($survey, $id) !== null);
        // Chosen by what tells the records apart on every run (their ids are random): the person's employee number.
        $byPerson = fn (string $table) => Employee::query()->select('employee_number')->whereColumn('employees.id', "{$table}.employee_id");
        $teleworkPolicy = TeleworkDocument::query()->where('kind', TeleworkDocumentKind::Policy)->sole()->id;
        // One record of each kind, the richest where it matters: the offices (NOM-035, Ley Silla and NOM-037 in one center),
        // the plant's closed evaluation, the climate survey and its first manager shown, the director (everyone reports to
        // them), a signed assessment, a teleworker in the roster.
        $values = [
            'workCenter' => [WorkCenter::query()->where('name', DemoRoster::CENTERS['offices'])->sole()->id],
            'campaign' => [Campaign::query()->where('work_center_id', $plant->id)->where('kind', CampaignKind::Nom035Evaluation)->where('status', CampaignStatus::Closed)->sole()->id],
            'survey' => [$climate->id],
            'manager' => [(string) $manager($climate)],
            'scenario' => [Scenario::query()->orderBy('created_at')->orderBy('name')->firstOrFail()->id],
            'complaint' => [Complaint::query()->orderBy('received_on')->orderBy('category')->firstOrFail()->id],
            'employee' => [DemoRoster::employeeId(DemoRoster::OWNER)],
            'assessment' => [RiskAssessment::query()->where('status', AssessmentStatus::Signed)->orderBy($byPerson('risk_assessments'))->orderBy('created_at')->firstOrFail()->id],
            'position' => [StandingPosition::query()->orderBy('name')->firstOrFail()->id],
            'application' => [ChecklistApplication::query()->whereNotNull('submitted_at')->orderBy('submitted_at')->orderBy('section')->firstOrFail()->id],
            'teleworker' => [Teleworker::query()->where('status', TeleworkerStatus::Active)->orderBy($byPerson('teleworkers'))->firstOrFail()->id],
            'policy' => [LeavePolicy::query()->sole()->id],
            'statement' => [LeaveStatement::query()->orderBy($byPerson('leave_statements'))->orderBy('issued_at')->firstOrFail()->id],
            // The person whose extra permissions the page edits: HR (the owner's cannot be edited).
            'member' => [(string) $tenant->memberships()->where('user_id', collect(resolve(Members::class)->all())->firstWhere('email', DemoRoster::person(DemoRoster::HR)->email)->userId)->sole()->id],
            'norm' => ['nom035', 'nom037', 'seating'],
            'field' => ['category', 'level'],
        ];
        // Where a parameter of the same name is another module's record.
        $pulsePages = ['tenant.pulse.commitments.index', 'tenant.pulse.export', 'tenant.pulse.managers', 'tenant.pulse.results', 'tenant.pulse.results.items', 'tenant.pulse.show'];
        $byRoute = [
            ...array_fill_keys($pulsePages, ['survey' => [$pulse->id]]),
            'tenant.disclosure.policy.version' => ['document' => [Document::query()->where('kind', DocumentKind::Policy)->sole()->id]],
            'tenant.disclosure.write' => ['kind' => array_map(fn (DocumentKind $kind): string => $kind->value, DocumentKind::cases())],
            'tenant.telework.documents.acknowledgements.index' => ['document' => [$teleworkPolicy]],
            'tenant.telework.documents.file' => ['document' => [$teleworkPolicy]],
            'tenant.telework.writing.show' => ['kind' => array_map(fn (TeleworkDocumentKind $kind): string => $kind->value, TeleworkDocumentKind::cases())],
        ];
        $noFile = 'downloads a file the demo never uploads';
        $left = [
            'tenant.attendance.places' => 'answers the address the page sends (a 422 bare)',
            'tenant.attendance.report.export' => 'exports the center and dates the page sends (a 422 bare)',
            'tenant.compensation.scenarios.compare' => 'compares the proposals the page sends (a 422 bare)',
            'tenant.compensation.quick.compare' => 'compares the proposals the page sends (a 422 bare)',
            // The demo costs its contract on its roster: it has no scenario of an average payroll, and the roster ones are sent to their own pages.
            'tenant.compensation.quick.edit' => 'the demo has no scenario of an average payroll to open',
            'tenant.compensation.quick.report' => 'the demo has no scenario of an average payroll to open',
            'tenant.seating.certificate' => 'only for a center with nobody standing; the offices have three positions',
            'tenant.telework.certificate' => 'only for a center with nobody teleworking; the offices have twelve',
            'tenant.assistant.conversations.show' => 'the demo has no conversation with Humi',
            'tenant.roles.edit' => 'the demo has no role of its own to edit: its people hold the templates',
            'tenant.sso.show' => 'single sign-on is an Enterprise module: the demo has no plan, so the page is a 404',
            'tenant.telework.notices.show' => 'the demo has no notice of a teleworker',
            // ponytail: the «Por jefe» list of such a pulse still links each manager to this 404 (Engagement, not the demo).
            'tenant.pulse.managers.show' => 'the demo\'s pulses ask nothing about the manager: no manager of theirs has results to open',
            'tenant.telework.committee.act' => $noFile,
            'tenant.seating.regulation.file' => $noFile,
            'tenant.seating.documents.file' => $noFile,
            'tenant.seating.measures.evidence' => $noFile,
            'tenant.seating.pause-agreements.evidence' => $noFile,
            'tenant.seating.seats.photo' => $noFile,
            'tenant.campaigns.program.evidence' => $noFile,
            'tenant.disclosure.dissemination.records.evidence' => $noFile,
            'tenant.telework.evidence-photos.show' => $noFile,
            'tenant.telework.photos.show' => $noFile,
            'tenant.telework.visit-authorizations.document.show' => $noFile,
            'tenant.telework.notices.evidence' => $noFile,
            'tenant.telework.trainings.evidence' => $noFile,
        ];
        [$pages, $unsupplied] = demoPanelPages($tenant, $values, $byRoute, $left);

        expect($unsupplied)->toBe([])
            ->and(count($pages))->toBeGreaterThan(130);

        $offices = $values['workCenter'][0];
        $path = fn (string $name, array $parameters = []): string => str_replace(url('/'), '', route($name, ['tenant' => $tenant, ...$parameters]));
        // What a page answers when it is not 200, by design, and why. A redirect names where it goes, and the crawl follows it
        // and checks that page too.
        $expected = [
            // The reviews of NOM-037 are the Comisión's: HR sits on the offices' one, the owner does not.
            DemoRoster::OWNER.' tenant.telework.reviews.index' => '403',
            DemoRoster::OWNER.' tenant.telework.reviews.show' => '403',
            // The members, their roles and permissions, and the plan are the owner's.
            DemoRoster::HR.' tenant.members.index' => '403',
            DemoRoster::HR.' tenant.members.permissions.edit' => '403',
            DemoRoster::HR.' tenant.roles.index' => '403',
            DemoRoster::HR.' tenant.roles.create' => '403',
            DemoRoster::HR.' tenant.members.invitations.create' => '403',
            DemoRoster::HR.' tenant.plan' => '403',
            DemoRoster::HR.' tenant.privacy.edit' => '403',
            // The channels the company writes through, and the words WhatsApp asks to accept, are the owner's too.
            DemoRoster::HR.' tenant.channels.edit' => '403',
            // The inspection report of NOM-037 is uploaded, not written: it has no template.
            DemoRoster::OWNER.' tenant.telework.writing.show inspection_report' => '404',
            DemoRoster::HR.' tenant.telework.writing.show inspection_report' => '404',
        ];
        foreach ([DemoRoster::OWNER, DemoRoster::HR] as $member) {
            // The data-quality page opens on its first issue; a center's route of a standard is that standard's own page.
            $expected["{$member} tenant.org-chart.quality"] = '302 '.$path('tenant.org-chart.quality', ['issue' => 'many_reports']);
            $expected["{$member} tenant.work-centers.route nom035"] = '302 '.$path('tenant.nom035.route', ['workCenter' => $offices]);
            $expected["{$member} tenant.work-centers.route nom037"] = '302 '.$path('tenant.telework.route', ['workCenter' => $offices]);
            $expected["{$member} tenant.work-centers.route seating"] = '302 '.$path('tenant.seating.route', ['workCenter' => $offices]);
        }
        // Props that hold answers by design, none of them anonymous: what the company answered a writing assistant about its
        // policy or contents, and the checklist of a teleworker that the Comisión reviews by name (NOM-037, criterio C-16).
        $notAnonymous = ['tenant.telework.reviews.show' => ['review.answers']];
        foreach (array_keys($pages) as $page) {
            if (str_starts_with($page, 'tenant.disclosure.write ') || str_starts_with($page, 'tenant.telework.writing.show ')) {
                $notAnonymous[$page] = ['answers'];
            }
        }
        // Every page that breaks, at once: the status it gave, the exception if one was thrown, the answers it showed.
        $broken = [];
        $visited = 0;

        foreach ([DemoRoster::OWNER, DemoRoster::HR] as $member) {
            $this->actingAs(User::query()->where('email', DemoRoster::person($member)->email)->sole());

            foreach ($pages as $name => $url) {
                $response = $this->get($url);
                $visited++;
                $status = $response->getStatusCode();
                $outcome = $status === 302 ? '302 '.str_replace(url('/'), '', (string) $response->headers->get('Location')) : (string) $status;

                if (isset($expected["{$member} {$name}"])) {
                    expect($outcome)->toBe($expected["{$member} {$name}"], "{$member} {$name}");

                    if ($status !== 302) {
                        continue;
                    }

                    $response = $this->get((string) $response->headers->get('Location'));
                    $visited++;
                    $status = $response->getStatusCode();
                    $outcome = (string) $status;
                }

                // A download streams: it carries no exception of its own.
                $exception = property_exists($response->baseResponse, 'exception') ? $response->baseResponse->exception : null;

                if ($exception !== null || $status !== 200) {
                    $broken[] = "{$member} {$name} ({$url}): {$outcome} ".($exception instanceof Throwable ? $exception::class.' '.$exception->getMessage() : '');

                    continue;
                }

                $view = property_exists($response->baseResponse, 'original') ? $response->baseResponse->original : null;

                if ($view instanceof View && is_array($view->getData()['page'] ?? null)) {
                    $props = Arr::except($view->getData()['page']['props'], $notAnonymous[$name] ?? []);

                    // Every page that shows an answer, at once too: the failure is kept and asserted below, never dropped.
                    try {
                        expectNoIndividualResponses($props);
                        expectNoEngagementResponses($props);
                    } catch (ExpectationFailedException $failure) {
                        $broken[] = "{$member} {$name}: ".$failure->getMessage().' '.$failure->getComparisonFailure()?->getActualAsString();
                    }
                }
            }
        }

        expect($visited)->toBe(2 * count($pages) + 8);
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
        expect($count(route('tenant.campaigns.show', [$tenant, $evaluation(CampaignStatus::Active)])))->toBe(31)
            ->and($count(route('tenant.campaigns.results', [$tenant, $evaluation(CampaignStatus::Closed)])))->toBe(24);

        auth()->logout();
    },
];
