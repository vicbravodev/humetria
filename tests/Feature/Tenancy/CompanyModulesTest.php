<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Actions\SyncCompanyModules;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\CompanyModuleAccess;
use App\Modules\Tenancy\Support\ModuleStates;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

mutates(SyncCompanyModules::class, CompanyModuleAccess::class, ModuleStates::class);

/** @return list<string> */
function enabledModulesOf(Tenant $tenant): array
{
    // What was read before the clock moved is stale: a request starts without it.
    resolve(ModuleStates::class)->forget();

    return resolve(CompanyModules::class)->enabledKeys($tenant->id);
}

const EXISTING_MODULES = ['attendance', 'nom035', 'nom037', 'seating', 'complaints'];

it('keeps every module a company can use today when it has no plan: nothing is lost by default', function (): void {
    $legacy = Tenant::factory()->create();

    expect(enabledModulesOf($legacy))->toBe(EXISTING_MODULES)
        ->and(resolve(CompanyModules::class)->restrictedTo($legacy->id))->toBeNull();

    foreach (EXISTING_MODULES as $module) {
        expect(resolve(CompanyModules::class)->enabled($legacy->id, $module))->toBeTrue();
    }

    // The modules that do not exist in code yet are only names: nobody has them before a plan says so.
    expect(resolve(CompanyModules::class)->enabled($legacy->id, 'vacations'))->toBeFalse();
});

it('limits a company with a plan to that plan\'s modules', function (): void {
    $tenant = Tenant::factory()->create();

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate'], CarbonImmutable::now());

    expect(enabledModulesOf($tenant))->toBe(['nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate'])
        ->and(resolve(CompanyModules::class)->enabled($tenant->id, 'attendance'))->toBeFalse()
        ->and(resolve(CompanyModules::class)->restrictedTo($tenant->id))->toBe(['nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate']);
});

it('keeps the modules a plan takes away until the day the change takes effect, then switches them off without deleting anything', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    $tenant = Tenant::factory()->create();

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035'], CarbonImmutable::parse('2027-01-01'));

    expect(enabledModulesOf($tenant))->toBe(['attendance', 'nom035', 'nom037', 'seating', 'complaints']);

    $this->travelTo('2027-01-01 08:00:00');

    expect(enabledModulesOf($tenant))->toBe(['nom035'])
        // The rows stay: switching a module off is an expiry date, never a delete.
        ->and(TenantModule::query()->where('tenant_id', $tenant->id)->where('module', 'nom037')->count())->toBe(1);
});

it('enables what a plan adds at once and enables again what was lost, so its data shows again', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    $tenant = Tenant::factory()->create();
    $modules = resolve(CompanyModules::class);
    $modules->syncPlan($tenant->id, ['nom035', 'nom037'], CarbonImmutable::parse('2026-12-01'));
    expect(enabledModulesOf($tenant))->toBe(['nom035', 'nom037']);

    $modules->syncPlan($tenant->id, ['nom035'], CarbonImmutable::parse('2026-12-10'));
    expect(enabledModulesOf($tenant))->toBe(['nom035']);

    $modules->syncPlan($tenant->id, ['attendance', 'nom035', 'seating'], CarbonImmutable::parse('2026-12-10'));

    expect(enabledModulesOf($tenant))->toBe(['attendance', 'nom035', 'seating'])
        ->and(TenantModule::query()->where('tenant_id', $tenant->id)->where('module', 'nom037')->count())->toBe(1);

    $modules->syncPlan($tenant->id, ['nom035', 'nom037'], CarbonImmutable::parse('2026-12-10'));
    expect(enabledModulesOf($tenant))->toBe(['nom035', 'nom037']);
});

it('does the same for one company and no other', function (): void {
    [$one, $other] = [Tenant::factory()->create(), Tenant::factory()->create()];

    resolve(CompanyModules::class)->syncPlan($one->id, ['seating'], CarbonImmutable::now());

    expect(enabledModulesOf($one))->toBe(['seating'])
        ->and(enabledModulesOf($other))->toBe(EXISTING_MODULES);
});

it('logs and audits what the plan switched on and off', function (): void {
    Log::spy();
    $tenant = Tenant::factory()->create();

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['seating'], CarbonImmutable::now());

    Log::shouldHaveReceived('info')->with('tenant.modules_synced', ['tenant_id' => $tenant->id, 'enabled' => ['seating'], 'disabled' => ['attendance', 'nom035', 'nom037', 'complaints']]);
    expect(AuditEntry::query()->where('event', 'tenant.modules_synced')->sole()->metadata)->toEqualCanonicalizing(['tenant_id' => $tenant->id, 'enabled' => ['seating'], 'disabled' => ['attendance', 'nom035', 'nom037', 'complaints']]);
});

it('refuses a plan with no modules or with one that is not a module', function (array $modules): void {
    $tenant = Tenant::factory()->create();

    expect(fn () => resolve(CompanyModules::class)->syncPlan($tenant->id, $modules, CarbonImmutable::now()))->toThrow(InvalidArgumentException::class)
        ->and(TenantModule::query()->count())->toBe(0);
})->with([[[]], [['nom035', 'inventado']]]);

it('refuses to ask about a module that does not exist', function (): void {
    expect(fn () => resolve(CompanyModules::class)->enabled(Tenant::factory()->create()->id, 'inventado'))->toThrow(InvalidArgumentException::class);
});

it('lists every module with whether the company has it and where it comes from', function (): void {
    $this->travelTo('2026-12-10 12:00:00');
    $legacy = Tenant::factory()->create();
    $planned = Tenant::factory()->create();
    resolve(CompanyModules::class)->syncPlan($planned->id, ['nom035', 'pulse'], CarbonImmutable::parse('2027-01-01'));

    $byKey = fn (Tenant $tenant): array => array_column(array_map(fn ($row): array => ['key' => $row->key, 'enabled' => $row->enabled, 'source' => $row->source, 'ends_on' => $row->endsOn, 'exists' => $row->existsInCode], resolve(CompanyModules::class)->list($tenant->id)), null, 'key');

    $legacyRows = $byKey($legacy);
    expect(array_keys($legacyRows))->toBe(array_map(fn (Module $module): string => $module->value, Module::cases()))
        ->and($legacyRows['nom035'])->toMatchArray(['enabled' => true, 'source' => 'legacy', 'exists' => true])
        ->and($legacyRows['vacations'])->toMatchArray(['enabled' => false, 'source' => null, 'exists' => false]);

    $plannedRows = $byKey($planned);
    expect($plannedRows['nom035'])->toMatchArray(['enabled' => true, 'source' => 'plan', 'ends_on' => null])
        ->and($plannedRows['pulse'])->toMatchArray(['enabled' => true, 'source' => 'plan', 'exists' => false])
        // Still on until the cut, and it says until when.
        ->and($plannedRows['nom037'])->toMatchArray(['enabled' => true, 'source' => 'legacy', 'ends_on' => '1 ene 2027'])
        ->and($plannedRows['seating']['ends_on'])->toBe('1 ene 2027');
});

it('keeps one row per company, module and source, and a grant always says why', function (): void {
    $tenant = Tenant::factory()->create();
    TenantModule::factory()->for($tenant)->create(['module' => 'nom035', 'source' => 'plan']);

    expect(fn () => TenantModule::factory()->for($tenant)->create(['module' => 'nom035', 'source' => 'plan']))->toThrow(QueryException::class)
        ->and(fn () => TenantModule::factory()->for($tenant)->create(['module' => 'nom037', 'source' => 'grant', 'reason' => '  ']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('tenant_modules')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'module' => 'nom037', 'source' => 'regalo']))->toThrow(QueryException::class);
});
