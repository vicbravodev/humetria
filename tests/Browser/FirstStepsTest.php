<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\FirstSteps\Models\FirstStepsState;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Pest\Browser\Api\AwaitableWebpage;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

const FIRST_STEPS_BUTTON = '[aria-label^="Primeros pasos"]';

beforeEach(function (): void {
    config(['first_steps.enabled' => true]);
});

/** The one row of first steps the test's owner has, if any. */
function firstStepsRow(): ?FirstStepsState
{
    return FirstStepsState::query()->withoutGlobalScopes()->first();
}

it('welcomes a new owner and opens the steps', function (bool $dark, bool $mobile): void {
    signInToPanel();

    visitPage('dashboard', '/admin/acme', $dark, $mobile)
        ->assertSee('Te acompañamos en tus primeros pasos')
        ->click('Empezar')
        ->assertSee('Primeros pasos')
        ->assertSee('0 de 5 pasos')
        ->assertSee('Guíame')
        ->assertAttribute(FIRST_STEPS_BUTTON, 'aria-expanded', 'true')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(firstStepsRow()?->welcomed_at)->not->toBeNull();
})->with($looks);

it('leaves only the button after «Ahora no»', function (): void {
    signInToPanel();

    $page = visitPage('dashboard', '/admin/acme')
        ->assertSee('Te acompañamos en tus primeros pasos')
        ->click('Ahora no')
        ->assertDontSee('Te acompañamos en tus primeros pasos')
        ->assertAttribute(FIRST_STEPS_BUTTON, 'aria-label', 'Primeros pasos: 0 de 5 pasos');

    expect(firstStepsRow()?->welcomed_at)->not->toBeNull();

    $page->navigate('/admin/acme')
        ->assertDontSee('Te acompañamos en tus primeros pasos')
        ->assertAttribute(FIRST_STEPS_BUTTON, 'aria-label', 'Primeros pasos: 0 de 5 pasos')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('hides the steps and takes them back from the user menu', function (): void {
    signInToPanel();

    visitPage('dashboard', '/admin/acme')
        ->click('Empezar')
        ->click('Ocultar primeros pasos')
        ->assertSee('Puedes retomarlos desde tu menú de usuario.')
        ->assertMissing(FIRST_STEPS_BUTTON)
        ->click('@sidebar-menu-button')
        ->click('Primeros pasos')
        ->assertSee('0 de 5 pasos')
        ->assertAttribute(FIRST_STEPS_BUTTON, 'aria-expanded', 'true')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(firstStepsRow()?->hidden_at)->toBeNull();
});

it('does not open the welcome to a superadmin working as the owner', function (): void {
    $this->actingAs(superadmin());
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    TenantMembership::factory()->for($tenant)->for(User::factory()->create(['name' => 'Ana Pérez']))->create();

    visitPage('dashboard', '/admin/companies', false, false, fn (AwaitableWebpage $page) => $page
        ->click('@tenant-actions')->click('Entrar como owner')
        ->assertPathIs('/admin/acme')
        ->assertSee('Estás viendo Humetría como Ana Pérez'))
        ->assertDontSee('Te acompañamos en tus primeros pasos')
        ->assertAttribute(FIRST_STEPS_BUTTON, 'aria-label', 'Primeros pasos: 0 de 5 pasos')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(firstStepsRow())->toBeNull();
});

it('says it is done once, then the button is gone', function (): void {
    $owner = User::factory()->create();
    signInToPanel($owner);
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    finishFirstSteps($tenant);
    FirstStepsState::factory()->welcomed()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    $page = visitPage('dashboard', '/admin/acme')
        ->assertSee('Listo: Humetria ya está trabajando para ti')
        ->assertAttribute(FIRST_STEPS_BUTTON, 'aria-expanded', 'true')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    $page->navigate('/admin/acme')
        ->assertMissing(FIRST_STEPS_BUTTON)
        ->assertDontSee('Listo: Humetria ya está trabajando para ti')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('shows nothing to hr', function (): void {
    signInToPanel(User::factory()->create());
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $hr = memberOf($tenant, App\Modules\Tenancy\Enums\TenantRole::Hr);
    $this->actingAs($hr);

    visitPage('dashboard', '/admin/acme')
        ->assertDontSee('Te acompañamos en tus primeros pasos')
        ->assertMissing(FIRST_STEPS_BUTTON)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

/** Loads every step of a company with only the always-there ones (no evaluation tool, Humi off), as the contract test does. */
function finishFirstSteps(Tenant $tenant): void
{
    resolve(App\Modules\Tenancy\Contracts\CompanyModules::class)->syncPlan($tenant->id, ['vacations'], Carbon\CarbonImmutable::now());
    App\Modules\Assistant\Models\TenantAiSettings::factory()->create(['tenant_id' => $tenant->id, 'enabled' => false]);
    $center = App\Modules\Tenancy\Models\WorkCenter::factory()->create(['tenant_id' => $tenant->id]);
    App\Modules\Organization\Models\Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    memberOf($tenant, App\Modules\Tenancy\Enums\TenantRole::Hr);
}
