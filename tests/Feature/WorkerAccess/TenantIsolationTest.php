<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerAccessState;

/*
 * Company A reads and changes none of company B's links or access states. By address: tests/Feature/WorkerAccess/AccessLinkTest.php.
 */
it('does not read nor spend the links of another company', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    WorkerAccessLink::factory()->withToken('link-of-b')->create(['tenant_id' => $b->id]);

    workIn($a);
    expect(WorkerAccessLink::query()->count())->toBe(0)
        ->and(WorkerAccessLink::findPending('link-of-b'))->toBeNull()
        ->and(WorkerAccessLink::query()->update(['revoked_at' => now()]))->toBe(0);

    workIn($b);
    expect(WorkerAccessLink::findPending('link-of-b')?->spend())->toBeTrue();
});

it('does not read nor move the access state of another company', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $state = WorkerAccessState::factory()->create(['tenant_id' => $b->id, 'valid_after' => now()->subDay()]);

    workIn($a);
    expect(WorkerAccessState::query()->count())->toBe(0)
        ->and(WorkerAccessState::query()->update(['valid_after' => now()]))->toBe(0);

    workIn($b);
    expect(WorkerAccessState::query()->sole()->valid_after->toIso8601String())->toBe($state->valid_after->toIso8601String());
});

it('finds a link only while it may still open, and spends it once', function (): void {
    workIn($tenant = Tenant::factory()->create());
    WorkerAccessLink::factory()->withToken('used')->used()->create(['tenant_id' => $tenant->id]);
    WorkerAccessLink::factory()->withToken('expired')->expired()->create(['tenant_id' => $tenant->id]);
    WorkerAccessLink::factory()->withToken('revoked')->revoked()->create(['tenant_id' => $tenant->id]);
    WorkerAccessLink::factory()->withToken('good')->create(['tenant_id' => $tenant->id]);

    $link = WorkerAccessLink::findPending('good');

    expect(WorkerAccessLink::findPending('used'))->toBeNull()
        ->and(WorkerAccessLink::findPending('expired'))->toBeNull()
        ->and(WorkerAccessLink::findPending('revoked'))->toBeNull()
        ->and(WorkerAccessLink::findPending('unknown'))->toBeNull()
        ->and($link?->spend())->toBeTrue()
        ->and($link?->spend())->toBeFalse()
        ->and(WorkerAccessLink::findPending('good'))->toBeNull();
});

it('keeps only the hash of a token', function (): void {
    workIn($tenant = Tenant::factory()->create());
    $link = WorkerAccessLink::factory()->withToken('secret-token')->create(['tenant_id' => $tenant->id]);

    expect($link->token_hash)->toBe(hash('sha256', 'secret-token'))
        ->and($link->toArray())->not->toHaveKey('token_hash');
});

it('refuses a link whose kind and issuer disagree', function (): void {
    workIn($tenant = Tenant::factory()->create());

    expect(fn () => WorkerAccessLink::factory()->create(['tenant_id' => $tenant->id, 'issued_by' => null]))->toThrow(Illuminate\Database\QueryException::class);
});
