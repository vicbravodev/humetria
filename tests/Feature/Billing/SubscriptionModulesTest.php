<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\ApplyScheduledChanges;
use App\Modules\Billing\Actions\SetSubscription;
use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\ModuleStates;
use Carbon\CarbonImmutable;

mutates(SetSubscription::class);

/** @return list<string> */
function companyModulesNow(Tenant $tenant): array
{
    // What was read before the clock moved is stale: a request starts without it.
    resolve(ModuleStates::class)->forget();

    return resolve(CompanyModules::class)->enabledKeys($tenant->id);
}

function planOnDay(Plan $plan, string $startsOn, SubscriptionStatus $status = SubscriptionStatus::Active): SubscriptionData
{
    return new SubscriptionData($plan, Profile::Direct, $status, 50, 1, CarbonImmutable::parse($startsOn));
}

it('keeps a company that has an account but no plan with every module it can use today', function (): void {
    [$tenant] = paidCompany();

    expect(companyModulesNow($tenant))->toBe(['attendance', 'vacations', 'org_chart', 'nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate']);
});

it('limits a company to the modules of the plan it is given, on the day the plan starts', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    [$tenant] = paidCompany();

    resolve(SetSubscription::class)->handle($tenant->id, planOnDay(Plan::Cumplimiento, '2026-12-01'));

    expect(companyModulesNow($tenant))->toBe(['nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate']);
});

it('gives a trial every module, whatever the plan it is trying', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    [$tenant] = paidCompany();

    resolve(SetSubscription::class)->handle($tenant->id, planOnDay(Plan::Jornada, '2026-12-01', SubscriptionStatus::Trial));

    expect(companyModulesNow($tenant))->toBe(array_map(fn (Module $module): string => $module->value, Module::cases()));
});

it('enables what a bigger plan adds at once, and keeps what a smaller one takes away until the change takes effect', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    [$tenant] = paidCompany();
    $set = resolve(SetSubscription::class);
    $set->handle($tenant->id, planOnDay(Plan::Jornada, '2026-12-01'));
    expect(companyModulesNow($tenant))->toBe(['attendance', 'vacations', 'org_chart']);

    $set->handle($tenant->id, planOnDay(Plan::Integral, '2026-12-01'));
    expect(companyModulesNow($tenant))->toBe(['attendance', 'vacations', 'org_chart', 'nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate']);

    // Down to Jornada is a reduction: it waits for the cut of the 1st of January and until then it still has everything it had.
    $set->handle($tenant->id, planOnDay(Plan::Jornada, '2027-01-01'));
    expect(companyModulesNow($tenant))->toBe(['attendance', 'vacations', 'org_chart', 'nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate']);

    $this->travelTo('2027-01-01 12:00:00');
    expect(companyModulesNow($tenant))->toBe(['attendance', 'vacations', 'org_chart', 'nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate']);
    resolve(ApplyScheduledChanges::class)->handle();
    expect(companyModulesNow($tenant))->toBe(['attendance', 'vacations', 'org_chart']);

    // And back up: the modules return at once, with their data.
    $set->handle($tenant->id, planOnDay(Plan::Integral, '2027-01-01'));
    expect(companyModulesNow($tenant))->toContain('nom035', 'seating', 'complaints');
});

it('moves the modules of the company through the console too, and no other company\'s', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    [$tenant] = paidCompany();
    [$other] = paidCompany();

    $this->actingAs(superadmin())->put(route('billing.subscriptions.update', $tenant->id), subscriptionInput(['plan' => 'jornada', 'starts_on' => '2026-12-01']))->assertSessionHasNoErrors();

    expect(companyModulesNow($tenant))->toBe(['attendance', 'vacations', 'org_chart'])
        ->and(companyModulesNow($other))->toBe(['attendance', 'vacations', 'org_chart', 'nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate']);
});

it('only names modules the catalog and Tenancy agree on', function (): void {
    $known = array_map(fn (Module $module): string => $module->value, Module::cases());

    foreach (Plan::cases() as $plan) {
        expect(array_diff(App\Modules\Billing\Support\Catalog::current()->modules($plan), $known))->toBe([]);
    }
});

it('previews the tools a plan takes away and the day, before it is saved', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    [$tenant] = paidCompany();

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput(['plan' => 'cumplimiento', 'starts_on' => '2026-12-01']))
        ->assertOk()
        ->assertJsonPath('modules.removed', ['Asistencia', 'Vacaciones', 'Organigrama'])
        ->assertJsonPath('modules.added', [])
        ->assertJsonPath('modules.removedOn', '10 dic 2026');

    // Previewing writes nothing: the company still has everything.
    expect(companyModulesNow($tenant))->toContain('attendance', 'vacations', 'org_chart');
});

it('previews the tools a bigger plan adds, and nothing removed', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    [$tenant] = paidCompany();
    resolve(SetSubscription::class)->handle($tenant->id, planOnDay(Plan::Jornada, '2026-12-01'));

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput(['plan' => 'integral', 'starts_on' => '2026-12-01']))
        ->assertOk()
        ->assertJsonPath('modules.added', ['NOM-035', 'NOM-037', 'Ley Silla', 'Quejas', 'Pulso', 'Clima'])
        ->assertJsonPath('modules.removed', [])
        ->assertJsonPath('modules.removedOn', null);
});

it('previews no module change for the plan the company already has', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    [$tenant] = paidCompany();
    resolve(SetSubscription::class)->handle($tenant->id, planOnDay(Plan::Cumplimiento, '2026-12-01'));

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput(['plan' => 'cumplimiento', 'starts_on' => '2026-12-01']))
        ->assertOk()
        ->assertJsonPath('modules.added', [])
        ->assertJsonPath('modules.removed', []);
});

it('previews a reduction on the day it takes effect, not today', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    [$tenant] = paidCompany();
    resolve(SetSubscription::class)->handle($tenant->id, planOnDay(Plan::Integral, '2026-12-01'));

    $this->actingAs(superadmin())->postJson(route('billing.subscriptions.preview', $tenant->id), subscriptionInput(['plan' => 'jornada', 'starts_on' => '2026-12-01']))
        ->assertOk()
        ->assertJsonPath('modules.removed', ['NOM-035', 'NOM-037', 'Ley Silla', 'Quejas', 'Pulso', 'Clima'])
        ->assertJsonPath('modules.removedOn', '1 ene 2027');
});
