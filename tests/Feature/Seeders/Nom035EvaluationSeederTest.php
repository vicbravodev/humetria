<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Seeders\Nom035EvaluationSeeder;

/*
 * The seeder builds a company that exercises NOM-035 end to end. These tests judge it against the DOF text, not
 * against the code: the ranges of II.3 c and III.3 c and the rule GR.I are written out here by hand
 * (docs/normas/nom-035/texto-de-referencia.md), so a scoring bug cannot hide behind the data it was seeded with.
 */

beforeEach(function (): void {
    $this->seed(Nom035EvaluationSeeder::class);
});

/** The risk level, 0 (Nulo) to 4 (Muy alto), of a final score given the lower bounds of Bajo, Medio, Alto and Muy alto. */
function levelOf(int $total, array $lowerBounds): int
{
    return count(array_filter($lowerBounds, fn (int $bound): bool => $total >= $bound));
}

/** @return list<int> how many fall in Nulo, Bajo, Medio, Alto and Muy alto. */
function levelCounts(array $totals, array $lowerBounds): array
{
    $counts = [0, 0, 0, 0, 0];

    foreach ($totals as $total) {
        $counts[levelOf($total, $lowerBounds)]++;
    }

    return $counts;
}

/** GR.I: after an event in section I, a clinical assessment with one "yes" in II, three in III or two in IV. */
function needsAssessment(array $yes): bool
{
    $in = fn (int $from, int $to): int => count(array_filter($yes, fn (int $number): bool => $number >= $from && $number <= $to));

    return $in(1, 6) >= 1 && ($in(7, 8) >= 1 || $in(9, 15) >= 3 || $in(16, 20) >= 2);
}

function demoCenter(string $name): WorkCenter
{
    return WorkCenter::query()->withoutGlobalScopes()->where('name', $name)->sole();
}

function demoCampaign(string $centerName): Campaign
{
    return Campaign::query()->withoutGlobalScopes()->where('work_center_id', demoCenter($centerName)->id)->sole();
}

function demoOwner(): User
{
    return User::query()->where('email', Nom035EvaluationSeeder::OWNER_EMAIL)->sole();
}

function resultsOf(Campaign $campaign): Illuminate\Testing\TestResponse
{
    $slug = Tenant::query()->findOrFail($campaign->tenant_id)->slug;

    return test()->actingAs(demoOwner())->get(route('tenant.campaigns.results', [$slug, $campaign->id]));
}

it('gives each work center the guides its headcount asks for (num. 2): 15, 16, 30, 51 and 100 people', function (): void {
    $guides = fn (string $name): array => demoCampaign($name)->instruments;
    $headcount = fn (string $name): int => demoCampaign($name)->workers;

    expect($guides(Nom035EvaluationSeeder::CENTER_15))->toBe(['nom035.guide-i'])
        ->and($headcount(Nom035EvaluationSeeder::CENTER_15))->toBe(15)
        ->and($guides(Nom035EvaluationSeeder::CENTER_16))->toBe(['nom035.guide-i', 'nom035.guide-ii'])
        ->and($headcount(Nom035EvaluationSeeder::CENTER_16))->toBe(16)
        ->and($guides(Nom035EvaluationSeeder::CENTER_30))->toBe(['nom035.guide-i', 'nom035.guide-ii'])
        ->and($headcount(Nom035EvaluationSeeder::CENTER_30))->toBe(30)
        ->and($guides(Nom035EvaluationSeeder::CENTER_51))->toBe(['nom035.guide-i', 'nom035.guide-iii'])
        ->and($headcount(Nom035EvaluationSeeder::CENTER_51))->toBe(51)
        ->and($guides(Nom035EvaluationSeeder::CENTER_100))->toBe(['nom035.guide-i', 'nom035.guide-iii'])
        ->and($headcount(Nom035EvaluationSeeder::CENTER_100))->toBe(100);
});

it('leaves one campaign in each state: draft, active (two) and closed (two)', function (): void {
    expect(demoCampaign(Nom035EvaluationSeeder::CENTER_16)->status)->toBe(CampaignStatus::Draft)
        ->and(demoCampaign(Nom035EvaluationSeeder::CENTER_15)->status)->toBe(CampaignStatus::Active)
        ->and(demoCampaign(Nom035EvaluationSeeder::CENTER_51)->status)->toBe(CampaignStatus::Active)
        ->and(demoCampaign(Nom035EvaluationSeeder::CENTER_30)->status)->toBe(CampaignStatus::Closed)
        ->and(demoCampaign(Nom035EvaluationSeeder::CENTER_100)->status)->toBe(CampaignStatus::Closed);
});

it('covers every limit of the Guía II ranges (II.3 c): 19/20, 44/45, 69/70 and 89/90, and every level', function (): void {
    $totals = Nom035EvaluationSeeder::guideTwoTotals();

    expect($totals)->toContain(19, 20, 44, 45, 69, 70, 89, 90)
        ->and(count($totals))->toBe(30)
        ->and(levelCounts($totals, [20, 45, 70, 90]))->toBe([6, 6, 6, 5, 7]);
});

it('covers every limit of the Guía III ranges (III.3 c): 49/50, 74/75, 98/99 and 139/140, and every level', function (): void {
    $totals = Nom035EvaluationSeeder::guideThreeTotals();

    expect($totals)->toContain(49, 50, 74, 75, 98, 99, 139, 140)
        ->and(count($totals))->toBe(80)
        ->and(array_filter(levelCounts($totals, [50, 75, 99, 140]), fn (int $count): bool => $count === 0))->toBe([]);
});

it('shows the Guía II results of the closed 30-person center exactly as the DOF ranges classify them', function (): void {
    $expected = levelCounts(Nom035EvaluationSeeder::guideTwoTotals(), [20, 45, 70, 90]);

    resultsOf(demoCampaign(Nom035EvaluationSeeder::CENTER_30))->assertOk()->assertInertia(fn ($page) => $page
        ->component('results/show')
        ->where('guide.key', 'nom035.guide-ii')
        ->where('participation', ['invited' => 30, 'answered' => 30])
        ->where('results.visible', true)
        ->where('results.answered', 30)
        ->where('results.final', $expected));
});

it('shows the Guía III results of the closed 100-person center exactly as the DOF ranges classify them', function (): void {
    $expected = levelCounts(Nom035EvaluationSeeder::guideThreeTotals(), [50, 75, 99, 140]);

    resultsOf(demoCampaign(Nom035EvaluationSeeder::CENTER_100))->assertOk()->assertInertia(fn ($page) => $page
        ->component('results/show')
        ->where('guide.key', 'nom035.guide-iii')
        ->where('results.visible', true)
        ->where('results.answered', 80)
        ->where('results.final', $expected));
});

it('applies Guía III to a sample of 80 for N = 100 (III.1), drawn from the whole center', function (): void {
    $campaign = demoCampaign(Nom035EvaluationSeeder::CENTER_100);

    expect($campaign->population)->toBe(Population::Sample)
        ->and($campaign->sample_size)->toBe(80)
        ->and(array_sum($campaign->quotas))->toBe(80)
        ->and(Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('in_sample', true)->count())->toBe(80)
        ->and(AnonymousResponse::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('instrument', 'nom035.guide-iii')->count())->toBe(80);
});

it('hides areas under the minimum of 5 and their complement (C-23): of four areas, only the two large ones show', function (): void {
    resultsOf(demoCampaign(Nom035EvaluationSeeder::CENTER_30))->assertInertia(fn ($page) => $page
        ->where('areas.hidden', 2)
        ->has('areas.visible', 2)
        ->where('areas.visible.0.answered', 12)
        ->where('areas.visible.1.answered', 10));
});

it('never puts an individual answer in what HR receives (C-22)', function (): void {
    $json = resultsOf(demoCampaign(Nom035EvaluationSeeder::CENTER_30))->assertOk()->baseResponse->getContent();

    expect($json)->not->toContain('"answers"')
        ->and($json)->not->toContain('employee_id')
        ->and($json)->not->toContain('participant_id');
});

it('flags for clinical assessment exactly who GR.I says, with a person on each side of every threshold', function (): void {
    $scenarios = Nom035EvaluationSeeder::GUIDE_ONE_YES;
    $campaign = demoCampaign(Nom035EvaluationSeeder::CENTER_30);
    $screenings = TraumaScreening::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->get();
    $perScenario = intdiv(30, count($scenarios));
    $remainder = 30 % count($scenarios);
    $expected = 0;

    foreach ($scenarios as $index => $yes) {
        $expected += needsAssessment($yes) ? $perScenario + ($index < $remainder ? 1 : 0) : 0;
    }

    expect($screenings)->toHaveCount(30)
        ->and($screenings->where('requires_clinical_assessment', true))->toHaveCount($expected)
        ->and(array_filter($scenarios, fn (array $yes): bool => needsAssessment($yes)))->not->toBe([])
        ->and(array_filter($scenarios, fn (array $yes): bool => ! needsAssessment($yes)))->not->toBe([]);

    resultsOf($campaign)->assertInertia(fn ($page) => $page->where('trauma', ['answered' => 30, 'requiring' => $expected]));
});

it('leaves the active campaigns half answered, and their survey links open', function (): void {
    $active = demoCampaign(Nom035EvaluationSeeder::CENTER_51);
    $participant = Participant::query()->withoutGlobalScopes()->where('campaign_id', $active->id)->firstOrFail();

    resultsOf($active)->assertInertia(fn ($page) => $page
        ->where('participation', ['invited' => 51, 'answered' => Nom035EvaluationSeeder::ANSWERED_AT_CENTER_51])
        ->where('results', null));

    $this->post('/logout');
    $this->get(route('survey.show', $participant->surveyToken()))->assertOk()->assertInertia(fn ($page) => $page->component('campaigns/survey'));
});

it('leaves people in every state at the 51-person center: both guides done, only one, and none', function (): void {
    $campaign = demoCampaign(Nom035EvaluationSeeder::CENTER_51);
    $done = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->withCount(['instruments as done' => fn ($guides) => $guides->where('status', 'completed')])->get()->countBy('done');

    expect($done->sortKeys()->all())->toBe([0 => 5, 1 => 41, 2 => 5]);
});

it('can be run twice without duplicating the company', function (): void {
    $this->seed(Nom035EvaluationSeeder::class);

    expect(Tenant::query()->where('slug', Nom035EvaluationSeeder::SLUG)->count())->toBe(1)
        ->and(Campaign::query()->withoutGlobalScopes()->count())->toBe(5);
});

it('lets owner and HR see the Guía I channeling list, and hides it from the viewer with a 404 (C-28)', function (): void {
    $campaign = demoCampaign(Nom035EvaluationSeeder::CENTER_30);
    $slug = Nom035EvaluationSeeder::SLUG;
    $visit = fn (string $email) => $this->actingAs(User::query()->where('email', $email)->sole())->get(route('tenant.campaigns.screenings', [$slug, $campaign->id]));

    $visit(Nom035EvaluationSeeder::MEMBER_EMAILS[0])->assertOk();
    $visit(Nom035EvaluationSeeder::MEMBER_EMAILS[1])->assertOk();
    $visit(Nom035EvaluationSeeder::MEMBER_EMAILS[2])->assertNotFound();
});
