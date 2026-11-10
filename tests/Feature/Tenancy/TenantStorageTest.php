<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

mutates(Tenant::class);

/**
 * The database guards companies on its own, whatever path writes them.
 */
it('stores the id as a random UUID v4', function (): void {
    $tenant = Tenant::factory()->create();

    expect(Str::isUuid($tenant->id, 4))->toBeTrue();
});

it('rejects a duplicate RFC', function (): void {
    $first = Tenant::factory()->create();

    Tenant::factory()->create(['rfc' => $first->rfc]);
})->throws(QueryException::class, 'tenants_rfc_unique');

it('rejects a duplicate slug', function (): void {
    $first = Tenant::factory()->create();

    Tenant::factory()->create(['slug' => $first->slug]);
})->throws(QueryException::class, 'tenants_slug_unique');

it('rejects an RFC in lowercase', function (): void {
    Tenant::factory()->create(['rfc' => 'abc010101xy1']);
})->throws(QueryException::class, 'tenants_rfc_format');

it('rejects a slug that is not lowercase letters, digits and hyphens', function (): void {
    Tenant::factory()->create(['slug' => 'Grupo_Acme']);
})->throws(QueryException::class, 'tenants_slug_format');

it('rejects a blank legal name', function (): void {
    Tenant::factory()->create(['legal_name' => '  ']);
})->throws(QueryException::class, 'tenants_legal_name_filled');

it('rejects an empty trade name', function (): void {
    Tenant::factory()->create(['trade_name' => '']);
})->throws(QueryException::class, 'tenants_trade_name_filled');

it('mass-assigns every editable field', function (): void {
    $attributes = [
        ...Tenant::factory()->raw(),
        'trial_started_at' => '2026-10-01 12:00:00+00',
        'active_until' => '2027-10-01 12:00:00+00',
        'restriction' => 'suspended',
        'restriction_reason' => 'Falta de pago',
        'restricted_at' => '2026-11-01 12:00:00+00',
    ];

    $tenant = Tenant::query()->create($attributes)->refresh();

    expect($tenant->only(['legal_name', 'trade_name', 'rfc', 'slug']))->toBe(Arr::only($attributes, ['legal_name', 'trade_name', 'rfc', 'slug']))
        ->and($tenant->trial_started_at?->toIso8601String())->toBe('2026-10-01T12:00:00+00:00')
        ->and($tenant->active_until?->toIso8601String())->toBe('2027-10-01T12:00:00+00:00')
        ->and($tenant->restriction)->toBe(TenantRestriction::Suspended)
        ->and($tenant->restriction_reason)->toBe('Falta de pago')
        ->and($tenant->restricted_at?->toIso8601String())->toBe('2026-11-01T12:00:00+00:00');
});

it('rejects a restriction the platform does not know', function (): void {
    Tenant::factory()->create();

    DB::table('tenants')->update(['restriction' => 'frozen', 'restriction_reason' => 'x', 'restricted_at' => now()]);
})->throws(QueryException::class, 'tenants_restriction_known');

it('rejects a restriction without its reason or its date, and a reason without a restriction', function (array $columns): void {
    Tenant::factory()->create($columns);
})->throws(QueryException::class, 'tenants_restriction_complete')->with([
    'no reason' => [['restriction' => 'suspended', 'restricted_at' => '2026-11-01 12:00:00+00']],
    'blank reason' => [['restriction' => 'suspended', 'restriction_reason' => ' ', 'restricted_at' => '2026-11-01 12:00:00+00']],
    'no date' => [['restriction' => 'archived', 'restriction_reason' => 'Baja']],
    'reason alone' => [['restriction_reason' => 'Baja']],
    'date alone' => [['restricted_at' => '2026-11-01 12:00:00+00']],
]);
