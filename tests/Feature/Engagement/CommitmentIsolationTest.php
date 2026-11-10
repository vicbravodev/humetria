<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Commitment;
use App\Modules\Tenancy\Models\Tenant;

it('does not read or change the commitments of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $ofB = Commitment::factory()->create(['tenant_id' => $b->id]);

    workIn($a);
    expect(Commitment::query()->count())->toBe(0)
        ->and(Commitment::query()->find($ofB->id))->toBeNull()
        ->and(Commitment::query()->whereKey($ofB->id)->update(['will_do' => 'Tomado']))->toBe(0)
        ->and(Commitment::query()->whereKey($ofB->id)->delete())->toBe(0);

    workIn($b);
    expect(Commitment::query()->sole()->will_do)->not->toBe('Tomado');
});

it('keeps a company-wide commitment without a group id and rejects a group commitment without one', function (): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);

    expect(Commitment::factory()->create(['tenant_id' => $tenant->id])->group_id)->toBeNull();
    expect(fn () => Commitment::factory()->create(['tenant_id' => $tenant->id, 'group_type' => 'area', 'group_id' => null]))->toThrow(Illuminate\Database\QueryException::class);
});
