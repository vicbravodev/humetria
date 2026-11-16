<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\DemoCompanyRegistry;
use Database\Seeders\Demo\Stories\OrganizationStory;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

mutates(DemoCompanyRegistry::class);

it('lets only one demo company exist', function (): void {
    Tenant::factory()->demo()->create();

    expect(fn () => Tenant::factory()->demo()->create())->toThrow(QueryException::class);
});

it('is not mass assignable', function (): void {
    // Outside production the model is strict, so a guarded attribute is refused out loud instead of dropped.
    expect(fn () => Tenant::create([
        'legal_name' => 'Demo S.A. de C.V.',
        'trade_name' => 'Demo',
        'rfc' => 'DEM010101AB1',
        'slug' => 'demo',
        'type' => 'company',
        'is_demo' => true,
    ]))->toThrow(MassAssignmentException::class)
        ->and(Tenant::query()->where('is_demo', true)->exists())->toBeFalse();

    expect(Tenant::factory()->create()->fresh()?->is_demo)->toBeFalse();
});

it('says which company is the demo', function (): void {
    $companies = resolve(DemoCompanies::class);
    $other = Tenant::factory()->create();

    expect($companies->id())->toBeNull();

    $demo = Tenant::factory()->demo()->create();

    expect($companies->isDemo($demo->id))->toBeTrue()
        ->and($companies->isDemo($other->id))->toBeFalse()
        ->and($companies->id())->toBe($demo->id);
});

/** Runs a statement in its own savepoint and gives the database's refusal, or null when it went through. */
function demoFlagRefusal(string $sql, string $id): ?string
{
    try {
        DB::transaction(fn (): int => DB::affectingStatement($sql, [$id]));
    } catch (QueryException $refusal) {
        return $refusal->getMessage();
    }

    return null;
}

it('lets the database itself refuse turning a company into the demo, or the demo back into a company', function (): void {
    $company = Tenant::factory()->create();
    $demo = Tenant::factory()->demo()->create();

    expect(demoFlagRefusal('update tenants set is_demo = true where id = ?', $company->id))->toContain('is_demo is fixed at creation')
        ->and(demoFlagRefusal('update tenants set is_demo = false where id = ?', $demo->id))->toContain('is_demo is fixed at creation')
        ->and(demoFlagRefusal("update tenants set trade_name = 'Otra' where id = ?", $demo->id))->toBeNull()
        ->and($company->fresh()?->is_demo)->toBeFalse()
        ->and($demo->fresh()?->is_demo)->toBeTrue();
});

it('creates the demo company flagged from its first row', function (): void {
    $tenant = resolve(OrganizationStory::class)->company();

    expect($tenant->fresh()?->is_demo)->toBeTrue()
        ->and(resolve(OrganizationStory::class)->company()->id)->toBe($tenant->id)
        ->and(Tenant::query()->where('is_demo', true)->count())->toBe(1);
});

it('ignores a demo flag sent with the console\'s edit of a company', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'grupo-ejemplo']);

    $this->actingAs(superadmin())->put(route('companies.update', $tenant), [
        'legal_name' => 'Grupo Ejemplo S.A. de C.V.',
        'trade_name' => 'Grupo Ejemplo',
        'rfc' => 'GEJ0101011A1',
        'slug' => 'grupo-ejemplo',
        'is_demo' => true,
    ])->assertRedirect();

    expect($tenant->fresh()?->trade_name)->toBe('Grupo Ejemplo')
        ->and($tenant->fresh()?->is_demo)->toBeFalse();
});

it('lets the flag change again only when the guard is rolled back', function (): void {
    $migration = require database_path('migrations/2027_01_21_100000_fix_the_demo_flag_at_creation.php');
    $company = Tenant::factory()->create();

    $migration->down();

    expect(demoFlagRefusal('update tenants set is_demo = true where id = ?', $company->id))->toBeNull();

    $migration->up();

    expect(demoFlagRefusal('update tenants set is_demo = false where id = ?', $company->id))->toContain('is_demo is fixed at creation');
});
