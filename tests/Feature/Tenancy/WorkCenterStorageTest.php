<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

mutates(WorkCenter::class);

/**
 * The database guards work centers on its own, whatever path writes them.
 */
it('stores the id as a random UUID v4', function (): void {
    $workCenter = WorkCenter::factory()->create();

    expect(Str::isUuid($workCenter->id, 4))->toBeTrue();
});

it('rejects a second work center with the same name in the same company, whatever the case', function (): void {
    $first = WorkCenter::factory()->create(['name' => 'Planta Querétaro']);

    WorkCenter::factory()->for($first->tenant)->create(['name' => 'PLANTA QUERÉTARO']);
})->throws(QueryException::class, 'work_centers_tenant_name_unique');

it('lets two companies use the same name', function (): void {
    WorkCenter::factory()->create(['name' => 'Matriz']);
    WorkCenter::factory()->create(['name' => 'Matriz']);

    expect(WorkCenter::query()->withoutGlobalScopes()->where('name', 'Matriz')->count())->toBe(2);
});

it('rejects a postal code that is not five digits', function (string $postalCode): void {
    WorkCenter::factory()->create(['postal_code' => $postalCode]);
})->throws(QueryException::class, 'work_centers_postal_code_format')->with(['1234', '123456', '7600A']);

it('rejects a state outside the 32', function (): void {
    // Straight to the table: the model's enum cast would stop it before the database sees it.
    DB::table('work_centers')->insert([
        ...WorkCenter::factory()->raw(['tenant_id' => Tenant::factory()->create()->id, 'state' => 'XYZ']),
        'id' => Str::uuid()->toString(),
    ]);
})->throws(QueryException::class, 'work_centers_state_known');

it('rejects a blank required field', function (string $field): void {
    WorkCenter::factory()->create([$field => '  ']);
})->throws(QueryException::class, 'filled')->with([
    'name', 'street', 'exterior_number', 'interior_number', 'neighborhood', 'municipality', 'main_activity',
]);

it('stores a missing interior number as null', function (): void {
    $workCenter = WorkCenter::factory()->create(['interior_number' => null]);

    expect(WorkCenter::query()->withoutGlobalScopes()->findOrFail($workCenter->id)->interior_number)->toBeNull();
});

it('mass-assigns every editable field', function (): void {
    $attributes = [
        'tenant_id' => Tenant::factory()->create()->id,
        'name' => 'Planta Querétaro',
        'street' => 'Av. 5 de Febrero',
        'exterior_number' => '1200',
        'interior_number' => 'Nave 3',
        'neighborhood' => 'Zona Industrial Benito Juárez',
        'postal_code' => '76120',
        'municipality' => 'Querétaro',
        'state' => MexicanState::Queretaro,
        'main_activity' => 'Manufactura de autopartes',
        'archived_at' => '2026-11-01 12:00:00+00',
    ];

    $workCenter = WorkCenter::query()->withoutGlobalScopes()->findOrFail(WorkCenter::query()->create($attributes)->id);

    expect($workCenter->only(array_keys(Arr::except($attributes, 'archived_at'))))->toBe(Arr::except($attributes, 'archived_at'))
        ->and($workCenter->archived_at?->toIso8601String())->toBe('2026-11-01T12:00:00+00:00');
});
