<?php

declare(strict_types=1);

use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

mutates(TenantContext::class);

/** Reads the work centers it can see, as a job of another module would. */
final class ReadWorkCentersJob implements ShouldQueue
{
    use Queueable;

    /** @var list<string>|string|null */
    public static array|string|null $seen = null;

    public function handle(): void
    {
        try {
            self::$seen = WorkCenter::query()->orderBy('name')->pluck('name')->all();
        } catch (NoCurrentTenant) {
            self::$seen = 'no company';
        }
    }
}

/**
 * Queues the job on the database queue and runs it the way a worker does: in a fresh scope, with nothing of the
 * request that dispatched it but its payload.
 */
function runQueued(): void
{
    ReadWorkCentersJob::$seen = null;
    dispatch(new ReadWorkCentersJob)->onConnection('database');
    app()->forgetScopedInstances();

    test()->artisan('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true])->assertSuccessful();
}

it('carries the company of the request into the jobs it queues', function (): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->for($tenant)->create(['name' => 'Mío']);
    WorkCenter::factory()->create(['name' => 'Ajeno']);
    workIn($tenant);

    runQueued();

    expect(ReadWorkCentersJob::$seen)->toBe(['Mío']);
});

it('leaves a job queued outside any company without one', function (): void {
    WorkCenter::factory()->create();

    runQueued();

    expect(ReadWorkCentersJob::$seen)->toBe('no company');
});
