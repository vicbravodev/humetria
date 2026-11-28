<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Support\AssistantScopeFactory;
use App\Modules\Assistant\Tools\GetCampaignProgress;
use App\Modules\Assistant\Tools\GetComplianceOverview;
use App\Modules\Assistant\Tools\GetRiskResults;
use App\Modules\Assistant\Tools\ListPendingActions;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\CompanyRoles;

mutates(GetCampaignProgress::class, GetComplianceOverview::class, GetRiskResults::class, ListPendingActions::class);

/*
 * Humi tells a person only the areas their role lets them see in the panel: a role of the company's own that may use
 * Humi and nothing else learns no figure of any area, and the templates keep telling what they always told.
 */

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->owner = ownerOf($this->a);
});

/** A person of `$tenant` who holds a role of the company's own made of `$permissions`. */
function humiPersonWith(Tenant $tenant, User $owner, array $permissions): User
{
    $role = app(CreateCompanyRole::class)->handle($tenant, 'Rol '.count($permissions).' '.implode(',', $permissions), $permissions, null, $owner);
    $person = User::factory()->create(['name' => 'Persona con rol propio']);
    TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();
    resolve(CompanyRoles::class)->assign($person, $tenant, $role);

    return $person;
}

/** The scope Humi builds for `$user` in `$tenant`. */
function humiScopeOf(Tenant $tenant, User $user): AssistantScope
{
    workIn($tenant);

    return resolve(AssistantScopeFactory::class)->make($user);
}

/** @return list<string> the keys of the overview's lines for `$scope`, in order */
function overviewKeys(AssistantScope $scope): array
{
    return array_column(toolCard(GetComplianceOverview::class, $scope)['rows'], 'key');
}

it('tells a role that may only use Humi no figure of any area: only its own access and what the channel and compensation say to anyone', function (): void {
    $scope = humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant']));
    $rows = array_column(toolCard(GetComplianceOverview::class, $scope)['rows'], null, 'key');

    expect(array_keys($rows))->toBe(['complaints', 'compensation', 'access'])
        ->and($rows['complaints']['summary'])->toBe('Tu canal de quejas aún no recibe casos.')
        ->and($rows['compensation']['summary'])->toBe('Compensación solo la ven quienes tienen ese permiso.')
        ->and($rows['access']['summary'])->toStartWith('Tu acceso: Humi, el asistente: Usar.');
});

it('tells each area only to whoever may see it in the panel', function (string $key, array $permissions): void {
    $without = overviewKeys(humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant'])));
    $with = overviewKeys(humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant', ...$permissions])));

    expect($without)->not->toContain($key)
        ->and($with)->toContain($key);
})->with([
    'NOM-035' => ['nom035', ['view-nom035-route']],
    'its disclosure' => ['disclosure', ['view-disclosure']],
    'NOM-037' => ['nom037', ['view-telework']],
    'Ley Silla' => ['seating', ['view-seating']],
    'vacations' => ['vacations', ['view-vacations']],
    'pulse' => ['pulse', ['view-pulse']],
    'climate' => ['climate', ['view-climate']],
    'attendance' => ['attendance', ['view-attendance']],
    'org chart' => ['org_chart', ['view-org-chart']],
]);

it('tells a role that sees attendance only attendance, never NOM-035 nor the other areas', function (): void {
    $keys = overviewKeys(humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant', 'view-attendance'])));

    expect($keys)->toBe(['complaints', 'attendance', 'compensation', 'access']);
});

it('keeps telling the templates what they always told', function (TenantRole $role, array $keys): void {
    $scope = $role === TenantRole::Owner ? humiScopeOf($this->a, $this->owner) : assistantScopeIn($this->a, $role);

    expect(overviewKeys($scope))->toBe($keys);
})->with([
    'owner' => [TenantRole::Owner, ['nom035', 'disclosure', 'nom037', 'seating', 'vacations', 'complaints', 'pulse', 'climate', 'attendance', 'org_chart', 'compensation', 'access']],
    'hr' => [TenantRole::Hr, ['nom035', 'disclosure', 'nom037', 'seating', 'vacations', 'complaints', 'pulse', 'climate', 'attendance', 'org_chart', 'compensation', 'access']],
    'viewer' => [TenantRole::Viewer, ['nom035', 'disclosure', 'nom037', 'seating', 'vacations', 'complaints', 'pulse', 'climate', 'attendance', 'org_chart', 'compensation', 'access']],
]);

it('never tells NOM-035 to the Comisión nor to the handler of complaints given Humi besides their template', function (TenantRole $role): void {
    $person = memberOf($this->a, $role);
    resolve(CompanyRoles::class)->syncDirectPermissions($person, $this->a, ['use-assistant']);

    expect(overviewKeys(humiScopeOf($this->a, $person)))->not->toContain('nom035')->not->toContain('disclosure');
})->with([TenantRole::Committee, TenantRole::Complaints]);

it('refuses the results of NOM-035 to whoever may not see its route, and writes nothing to the audit trail', function (): void {
    $center = WorkCenter::factory()->create(['tenant_id' => $this->a->id]);
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->a->id, 'work_center_id' => $center->id]);
    $scope = humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant', 'view-attendance']));

    $card = toolCard(GetRiskResults::class, $scope, ['campaign_id' => $closed->id]);

    expect($card['rows'])->toBe([])
        ->and($card['note'])->toBe('Tu rol no incluye ver NOM-035: pídele acceso a quien administra los roles de tu empresa.')
        ->and($card['link'])->toBeNull()
        ->and(AuditEntry::query()->where('event', 'assistant.results_viewed')->exists())->toBeFalse();
});

it('refuses the progress of the campaigns to whoever may not see the route of NOM-035', function (): void {
    $center = WorkCenter::factory()->create(['tenant_id' => $this->a->id]);
    $campaign = Campaign::factory()->active(now()->addDays(3)->toDateString())->create(['tenant_id' => $this->a->id, 'work_center_id' => $center->id]);
    $scope = humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant']));

    foreach ([[], ['campaign_id' => $campaign->id]] as $arguments) {
        $card = toolCard(GetCampaignProgress::class, $scope, $arguments);

        expect($card['rows'])->toBe([])
            ->and($card['note'])->toBe('Tu rol no incluye ver NOM-035: pídele acceso a quien administra los roles de tu empresa.');
    }
});

it('lists only the pending things of the areas the person sees, and refuses the list to whoever sees none of them', function (): void {
    WorkCenter::factory()->create(['tenant_id' => $this->a->id]);
    $telework = humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant', 'view-telework']));
    $nothing = humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant']));

    expect(array_column(toolCard(ListPendingActions::class, assistantScopeIn($this->a))['rows'], 'kind'))->toContain('evaluation_missing')
        ->and(array_column(toolCard(ListPendingActions::class, $telework)['rows'], 'kind'))->not->toContain('evaluation_missing')
        ->and(toolCard(ListPendingActions::class, $nothing))->toMatchArray(['rows' => [], 'note' => 'Tu rol no incluye ver pendientes de ninguna área: pídele acceso a quien administra los roles de tu empresa.']);
});

it('refuses the list to whoever sees only the work centers of a company that already has them, as nothing of theirs is reviewed', function (): void {
    WorkCenter::factory()->create(['tenant_id' => $this->a->id]);
    $centers = humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant', 'view-work-centers']));

    expect(toolCard(ListPendingActions::class, $centers))->toMatchArray(['rows' => [], 'note' => 'Tu rol no incluye ver pendientes de ninguna área: pídele acceso a quien administra los roles de tu empresa.']);
});

it('still asks whoever sees the work centers of a company with none to register the first', function (): void {
    $centers = humiScopeOf($this->a, humiPersonWith($this->a, $this->owner, ['use-assistant', 'view-work-centers']));

    expect(array_column(toolCard(ListPendingActions::class, $centers)['rows'], 'kind'))->toBe(['register_work_center']);
});

it('tells a role that may only use Humi no live fact, whoever of the company asked first', function (): void {
    $only = humiPersonWith($this->a, $this->owner, ['use-assistant']);
    $hr = memberOf($this->a, TenantRole::Hr);
    $facts = fn (User $user): array => $this->actingAs($user)->getJson(route('tenant.assistant.facts', $this->a->slug))->assertOk()->json('facts');

    expect($facts($hr))->toHaveKeys(['participation', 'risk', 'pending'])
        ->and($facts($only))->toBe([])
        ->and($facts($hr))->toHaveKeys(['participation', 'risk', 'pending']);
});
