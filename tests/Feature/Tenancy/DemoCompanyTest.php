<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\DemoCompanyRegistry;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;

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
