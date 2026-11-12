<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\FirstSteps\Actions\BuildFirstSteps;
use App\Modules\FirstSteps\Data\FirstStepsData;
use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Contracts\SeatingApplicability;
use App\Modules\Telework\Contracts\TeleworkApplicability;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;

mutates(BuildFirstSteps::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
});

/** @param list<string> $modules */
function givePlan(Tenant $tenant, array $modules): void
{
    resolve(CompanyModules::class)->syncPlan($tenant->id, $modules, CarbonImmutable::now());
}

function stepsOf(Tenant $tenant, User $user): FirstStepsData
{
    workIn($tenant);

    return resolve(BuildFirstSteps::class)->handle($tenant->id, $user->id);
}

function archivedCenter(Tenant $tenant): string
{
    return WorkCenter::factory()->archived()->create(['tenant_id' => $tenant->id])->id;
}

/** @return array<string, bool> done by "key" or "key:tool" */
function doneOf(FirstStepsData $data): array
{
    $done = [];
    foreach ($data->steps as $step) {
        $done[$step->key.($step->tool === null ? '' : ':'.$step->tool)] = $step->done;
    }

    return $done;
}

it('gives the five steps, none done, to a company with every module and nothing loaded', function (): void {
    $data = stepsOf($this->tenant, $this->owner);

    expect(array_map(fn ($step) => $step->key, $data->steps))->toBe(['work_centers', 'people', 'evaluation', 'team', 'humi'])
        ->and($data->steps[2]->tool)->toBe('nom035')
        ->and(array_map(fn ($step) => $step->done, $data->steps))->each->toBeFalse()
        ->and($data->allDone())->toBeFalse()
        ->and($data->welcomed)->toBeFalse()
        ->and($data->hidden)->toBeFalse();
});

it('marks each step done from the data', function (Closure $load, string $step): void {
    $load($this->tenant, $this->owner);

    $done = doneOf(stepsOf($this->tenant, $this->owner));

    expect(array_keys(array_filter($done)))->toBe([$step]);
})->with([
    'an active work center' => [fn (Tenant $t) => WorkCenter::factory()->create(['tenant_id' => $t->id]), 'work_centers'],
    'an active employee' => [fn (Tenant $t) => Employee::factory()->create(['tenant_id' => $t->id, 'work_center_id' => archivedCenter($t)]), 'people'],
    'a campaign in course' => [fn (Tenant $t) => Campaign::factory()->active()->create(['tenant_id' => $t->id, 'work_center_id' => archivedCenter($t)]), 'evaluation:nom035'],
    'a second member' => [fn (Tenant $t) => memberOf($t, TenantRole::Hr), 'team'],
    'an open invitation' => [fn (Tenant $t) => TenantInvitation::factory()->create(['tenant_id' => $t->id]), 'team'],
    'Humi used by the owner' => [function (Tenant $t, User $owner): void {
        $participant = ConversationParticipant::factory()->for($owner)->create(['tenant_id' => $t->id]);
        AiUsage::factory()->create(['tenant_id' => $t->id, 'participant_id' => $participant->id]);
    }, 'humi'],
]);

it('does not count an archived work center nor a deactivated person', function (): void {
    WorkCenter::factory()->archived()->create(['tenant_id' => $this->tenant->id]);
    Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => archivedCenter($this->tenant)]);

    expect(array_filter(doneOf(stepsOf($this->tenant, $this->owner))))->toBe([]);
});

it('takes the first tool the plan has for the third step', function (array $modules, ?string $tool, Closure $use): void {
    givePlan($this->tenant, $modules);
    $use($this->tenant, $this->owner);

    $data = stepsOf($this->tenant, $this->owner);
    $evaluation = array_values(array_filter($data->steps, fn ($step) => $step->key === 'evaluation'));

    expect($evaluation)->toHaveCount($tool === null ? 0 : 1);
    if ($tool !== null) {
        expect($evaluation[0]->tool)->toBe($tool)->and($evaluation[0]->done)->toBeTrue();
    } else {
        expect($data->steps)->toHaveCount(4);
    }
})->with([
    'nom035 first' => [['nom035', 'nom037', 'seating', 'attendance'], 'nom035', fn (Tenant $t) => Campaign::factory()->closed()->create(['tenant_id' => $t->id])],
    'nom037' => [['nom037', 'seating', 'vacations'], 'nom037', function (Tenant $t, User $owner): void {
        $center = WorkCenter::factory()->create(['tenant_id' => $t->id]);
        workIn($t);
        resolve(TeleworkApplicability::class)->declare($center->id, false, $owner->id);
    }],
    'seating' => [['seating', 'attendance'], 'seating', function (Tenant $t, User $owner): void {
        $center = WorkCenter::factory()->create(['tenant_id' => $t->id]);
        workIn($t);
        resolve(SeatingApplicability::class)->declare($center->id, false, null, $owner->id);
    }],
    'attendance' => [['attendance', 'vacations'], 'attendance', fn (Tenant $t) => AttendancePolicy::factory()->create(['tenant_id' => $t->id])],
    'none' => [['vacations', 'org_chart'], null, fn () => null],
]);

it('lists the other tools of the third step the plan has, never the main one', function (): void {
    givePlan($this->tenant, ['nom035', 'nom037', 'seating']);

    $others = stepsOf($this->tenant, $this->owner)->others;

    expect(array_map(fn ($other) => $other->key, $others))->toBe(['nom037', 'seating'])
        ->and($others[0]->href)->toBe(route('tenant.telework.index', ['tenant' => $this->tenant->slug], absolute: false))
        ->and($others[1]->href)->toBe(route('tenant.seating.index', ['tenant' => $this->tenant->slug], absolute: false));
});

it('leaves Humi out when it is off for the company', function (): void {
    TenantAiSettings::factory()->create(['tenant_id' => $this->tenant->id, 'enabled' => false]);

    expect(array_map(fn ($step) => $step->key, stepsOf($this->tenant, $this->owner)->steps))->not->toContain('humi');
});

it('gives three steps to a company with no evaluation tool and Humi off', function (): void {
    givePlan($this->tenant, ['vacations', 'org_chart']);
    TenantAiSettings::factory()->create(['tenant_id' => $this->tenant->id, 'enabled' => false]);

    expect(array_map(fn ($step) => $step->key, stepsOf($this->tenant, $this->owner)->steps))->toBe(['work_centers', 'people', 'team']);
});

it('sends the questions step to the work centers while there is none', function (): void {
    givePlan($this->tenant, ['nom037']);

    $href = stepsOf($this->tenant, $this->owner)->steps[2]->href;
    expect($href)->toEndWith('/work-centers');

    WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    expect(stepsOf($this->tenant, $this->owner)->steps[2]->href)->toEndWith('/work-centers/onboarding');
});

it('links every step inside the company', function (): void {
    $steps = stepsOf($this->tenant, $this->owner)->steps;

    foreach ($steps as $step) {
        if ($step->href !== null) {
            expect($step->href)->toStartWith("/admin/{$this->tenant->slug}/");
        }
    }
    expect(collect($steps)->firstWhere('key', 'humi')?->href)->toBeNull();
});

it('serializes the steps, the state and the other tools', function (): void {
    givePlan($this->tenant, ['nom035', 'seating']);

    $array = stepsOf($this->tenant, $this->owner)->withState(true, false)->toArray();

    expect($array['welcomed'])->toBeTrue()
        ->and($array['hidden'])->toBeFalse()
        ->and($array['steps'][0])->toBe(['key' => 'work_centers', 'done' => false, 'href' => "/admin/{$this->tenant->slug}/work-centers", 'tool' => null])
        ->and($array['others'])->toBe([['key' => 'seating', 'href' => "/admin/{$this->tenant->slug}/seating"]]);
});

it('never reads another company', function (): void {
    $other = Tenant::factory()->create();
    WorkCenter::factory()->create(['tenant_id' => $other->id]);
    Employee::factory()->create(['tenant_id' => $other->id]);
    Campaign::factory()->active()->create(['tenant_id' => $other->id]);
    ownerOf($other);
    TenantInvitation::factory()->create(['tenant_id' => $other->id]);
    $participant = ConversationParticipant::factory()->for($this->owner)->create(['tenant_id' => $other->id]);
    AiUsage::factory()->create(['tenant_id' => $other->id, 'participant_id' => $participant->id]);

    expect(array_filter(doneOf(stepsOf($this->tenant, $this->owner))))->toBe([]);
});

it('reads the modules of the company once, however many tools it checks', function (): void {
    $modules = Mockery::spy(CompanyModules::class);
    $modules->shouldReceive('enabledKeys')->andReturn(['nom035', 'nom037', 'seating', 'attendance']);
    app()->instance(CompanyModules::class, $modules);

    stepsOf($this->tenant, $this->owner);

    $modules->shouldHaveReceived('enabledKeys')->once();
});
