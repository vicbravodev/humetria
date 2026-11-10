<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Actions\GrantModule;
use App\Modules\Tenancy\Actions\RevokeModule;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Http\Requests\GrantModuleRequest;
use App\Modules\Tenancy\Http\Requests\RevokeModuleRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\ModuleStates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(GrantModule::class, RevokeModule::class, GrantModuleRequest::class, RevokeModuleRequest::class);

/** A company whose plan has only `$modules`. */
function consolePlanned(array $modules = ['nom035']): Tenant
{
    $tenant = Tenant::factory()->create();
    resolve(CompanyModules::class)->syncPlan($tenant->id, $modules, CarbonImmutable::now());

    return $tenant;
}

/** @return list<string> */
function consoleModules(Tenant $tenant): array
{
    resolve(ModuleStates::class)->forget();

    return resolve(CompanyModules::class)->enabledKeys($tenant->id);
}

it('lives under /admin/companies', function (): void {
    expect(route('companies.show', 'c', absolute: false))->toBe('/admin/companies/c')
        ->and(route('companies.modules.grant', 'c', absolute: false))->toBe('/admin/companies/c/modules/grant')
        ->and(route('companies.modules.revoke', 'c', absolute: false))->toBe('/admin/companies/c/modules/revoke');
});

it('shows the staff the company with each module, whether it has it and where it comes from', function (): void {
    $tenant = consolePlanned(['nom035', 'pulse']);
    TenantModule::factory()->for($tenant)->create(['module' => 'seating', 'source' => 'grant', 'reason' => 'Piloto', 'expires_at' => '2027-02-01 06:00:00']);

    foreach ([superadmin(), support()] as $user) {
        $this->actingAs($user)->get(route('companies.show', $tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/company')
            ->where('company.trade_name', $tenant->trade_name)
            ->where('company.rfc', $tenant->rfc)
            ->where('can_manage', $user->isSuperadmin())
            ->has('modules', 9)
            ->where('modules.3', ['key' => 'nom035', 'label' => 'NOM-035', 'enabled' => true, 'source' => 'plan', 'source_label' => 'Plan', 'reason' => null, 'ends_on' => null, 'exists_in_code' => true])
            ->where('modules.5.source', 'grant')
            ->where('modules.5.reason', 'Piloto')
            ->where('modules.5.ends_on', '1 feb 2027')
            ->where('modules.0.enabled', false)
            ->where('modules.7.exists_in_code', false));
    }
});

it('grants a module with a reason and an end, and logs and audits it', function (): void {
    Log::spy();
    $this->travelTo('2027-01-10 12:00:00');
    $tenant = consolePlanned(['nom035']);

    $this->actingAs(superadmin())->post(route('companies.modules.grant', $tenant), ['module' => 'nom037', 'reason' => 'Piloto de 3 semanas', 'until' => '2027-01-31'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('companies.show', $tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Módulo concedido.']]);

    expect(consoleModules($tenant))->toBe(['nom035', 'nom037']);
    $row = TenantModule::query()->where('tenant_id', $tenant->id)->where('module', 'nom037')->where('source', 'grant')->sole();
    expect($row->source->value)->toBe('grant')->and($row->reason)->toBe('Piloto de 3 semanas')->and($row->granted_by)->not->toBeNull();
    Log::shouldHaveReceived('info')->with('tenant.module_granted', ['tenant_id' => $tenant->id, 'module' => 'nom037', 'until' => '2027-01-31']);
    expect(AuditEntry::query()->where('event', 'tenant.module_granted')->sole()->metadata)->toEqualCanonicalizing(['tenant_id' => $tenant->id, 'module' => 'nom037', 'reason' => 'Piloto de 3 semanas', 'until' => '2027-01-31']);

    // The last day counts; the day after it, the module is gone and nothing was deleted.
    $this->travelTo('2027-01-31 23:00:00');
    expect(consoleModules($tenant))->toBe(['nom035', 'nom037']);
    $this->travelTo('2027-02-01 12:00:00');
    expect(consoleModules($tenant))->toBe(['nom035'])->and(TenantModule::query()->where('module', 'nom037')->where('source', 'grant')->count())->toBe(1);
});

it('grants a module with no end', function (): void {
    $tenant = consolePlanned(['nom035']);

    $this->actingAs(superadmin())->post(route('companies.modules.grant', $tenant), ['module' => 'seating', 'reason' => 'Acuerdo comercial'])->assertSessionHasNoErrors();

    expect(consoleModules($tenant))->toBe(['nom035', 'seating'])
        ->and(TenantModule::query()->where('module', 'seating')->where('source', 'grant')->sole()->expires_at)->toBeNull();
});

it('refuses to grant what the rules refuse, and says on which field', function (array $input, string $field): void {
    $tenant = consolePlanned(['nom035']);

    $this->actingAs(superadmin())->post(route('companies.modules.grant', $tenant), $input)->assertSessionHasErrors($field);

    expect(consoleModules($tenant))->toBe(['nom035']);
})->with([
    'no reason' => [['module' => 'seating', 'reason' => ''], 'reason'],
    'a reason that is only spaces' => [['module' => 'seating', 'reason' => '   '], 'reason'],
    'a reason that is too long' => [['module' => 'seating', 'reason' => str_repeat('a', 501)], 'reason'],
    'no module' => [['reason' => 'x'], 'module'],
    'a module that does not exist' => [['module' => 'inventado', 'reason' => 'x'], 'module'],
    'a module it already has' => [['module' => 'nom035', 'reason' => 'x'], 'module'],
    'an end that is not a date' => [['module' => 'seating', 'reason' => 'x', 'until' => 'pronto'], 'until'],
    'an end that already passed' => [['module' => 'seating', 'reason' => 'x', 'until' => '2020-01-01'], 'until'],
]);

it('takes a module away by hand with a reason, from a company that has no plan or one that does', function (): void {
    Log::spy();
    $legacy = Tenant::factory()->create();
    $planned = consolePlanned(['nom035', 'seating']);

    $this->actingAs(superadmin())->post(route('companies.modules.revoke', $legacy), ['module' => 'seating', 'reason' => 'Pidió pausar Ley Silla'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('companies.show', $legacy))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Módulo quitado.']]);
    $this->post(route('companies.modules.revoke', $planned), ['module' => 'seating', 'reason' => 'Falta de pago'])->assertSessionHasNoErrors();

    expect(consoleModules($legacy))->toBe(['attendance', 'nom035', 'nom037', 'complaints'])
        ->and(consoleModules($planned))->toBe(['nom035']);
    Log::shouldHaveReceived('info')->with('tenant.module_revoked', ['tenant_id' => $legacy->id, 'module' => 'seating']);
    expect(AuditEntry::query()->where('event', 'tenant.module_revoked')->where('metadata->tenant_id', $legacy->id)->sole()->metadata)->toEqualCanonicalizing(['tenant_id' => $legacy->id, 'module' => 'seating', 'reason' => 'Pidió pausar Ley Silla']);

    // The page of the module is gone for its people (404), and the rest of the company stays.
    $this->actingAs(ownerOf($legacy))->get(route('tenant.seating.index', $legacy))->assertNotFound();
    $this->get(route('tenant.attendance.index', $legacy))->assertOk();
});

it('keeps a hand-made revocation when the plan changes, and takes back a grant that is revoked', function (): void {
    $tenant = consolePlanned(['nom035', 'seating']);
    $admin = superadmin();
    $this->actingAs($admin)->post(route('companies.modules.revoke', $tenant), ['module' => 'seating', 'reason' => 'Pausa'])->assertSessionHasNoErrors();

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035', 'seating', 'nom037'], CarbonImmutable::now());
    expect(consoleModules($tenant))->toBe(['nom035', 'nom037']);

    $this->post(route('companies.modules.grant', $tenant), ['module' => 'seating', 'reason' => 'Se reanuda'])->assertSessionHasNoErrors();
    expect(consoleModules($tenant))->toBe(['nom035', 'nom037', 'seating'])
        ->and(TenantModule::query()->where('module', 'seating')->where('source', 'revoke')->count())->toBe(0);

    $this->post(route('companies.modules.grant', $tenant), ['module' => 'complaints', 'reason' => 'Piloto'])->assertSessionHasNoErrors();
    $this->post(route('companies.modules.revoke', $tenant), ['module' => 'complaints', 'reason' => 'Terminó el piloto'])->assertSessionHasNoErrors();
    expect(consoleModules($tenant))->toBe(['nom035', 'nom037', 'seating'])
        ->and(TenantModule::query()->where('module', 'complaints')->where('source', 'grant')->count())->toBe(0);
});

it('refuses to revoke what the company does not have, or without a reason', function (array $input, string $field): void {
    $tenant = consolePlanned(['nom035']);

    $this->actingAs(superadmin())->post(route('companies.modules.revoke', $tenant), $input)->assertSessionHasErrors($field);

    expect(consoleModules($tenant))->toBe(['nom035']);
})->with([
    'no reason' => [['module' => 'nom035', 'reason' => ''], 'reason'],
    'a module it does not have' => [['module' => 'seating', 'reason' => 'x'], 'module'],
    'a module that does not exist' => [['module' => 'inventado', 'reason' => 'x'], 'module'],
]);

it('is the superadmin\'s alone to change: support, owners and guests get a 404 or the login', function (): void {
    $tenant = consolePlanned(['nom035']);
    $grant = ['module' => 'seating', 'reason' => 'x'];

    $owner = ownerOf($tenant);

    foreach ([support(), $owner] as $user) {
        $this->actingAs($user)->post(route('companies.modules.grant', $tenant), $grant)->assertNotFound();
        $this->post(route('companies.modules.revoke', $tenant), ['module' => 'nom035', 'reason' => 'x'])->assertNotFound();
    }

    $this->actingAs($owner)->get(route('companies.show', $tenant))->assertNotFound();
    auth()->logout();
    $this->get(route('companies.show', $tenant))->assertRedirect(route('login'));
    $this->actingAs(superadmin())->post(route('companies.modules.grant', '11111111-1111-4111-8111-111111111111'), $grant)->assertNotFound();

    expect(consoleModules($tenant))->toBe(['nom035']);
});
