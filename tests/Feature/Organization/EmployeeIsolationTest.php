<?php

declare(strict_types=1);

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;

mutates(BelongsToCurrentTenant::class);

/**
 * Company A never reads nor changes company B's employees or areas through a query.
 */
it('reads only the employees and areas of the current company', function (string $model): void {
    $mine = $model::factory()->create();
    $model::factory()->create();

    workIn(Tenant::query()->findOrFail($mine->tenant_id));

    expect($model::query()->pluck('id')->all())->toBe([$mine->id]);
})->with([Employee::class, Area::class]);

it('cannot change the employees of another company', function (): void {
    $foreign = Employee::factory()->create(['name' => 'Ajeno']);
    workIn(Tenant::factory()->create());

    expect(Employee::query()->whereKey($foreign->id)->update(['name' => 'Robado']))->toBe(0)
        ->and(Employee::query()->withoutGlobalScopes()->findOrFail($foreign->id)->name)->toBe('Ajeno');
});

it('refuses to read employees when no company is resolved', function (): void {
    Employee::factory()->create();

    Employee::query()->get();
})->throws(NoCurrentTenant::class);
