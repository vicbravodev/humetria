<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Actions\ReconcileCompanyLimits;
use App\Modules\Tenancy\Actions\SyncCompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\CompanyLimitCounters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(CompanyLimitCounters::class, SyncCompanyLimits::class, ReconcileCompanyLimits::class);

/** A company with a limit on a resource, working there. */
function limitedCompany(string $resource, int $amount, int $used = 0): Tenant
{
    $tenant = Tenant::factory()->create();
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => $resource, 'amount' => $amount, 'used' => $used]);
    workIn($tenant);

    return $tenant;
}

function usedOf(Tenant $tenant, string $resource): int
{
    return TenantLimit::query()->where('tenant_id', $tenant->id)->where('resource', $resource)->firstOrFail()->used;
}

/** The message of the refusal of a reservation. */
function refusalOf(Closure $reserve): string
{
    try {
        $reserve();
    } catch (ValidationException $exception) {
        return $exception->validator->errors()->first();
    }

    return '';
}

it('leaves a company with no limits unlimited: reserving and releasing do nothing', function (): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $limits = resolve(CompanyLimits::class);

    $limits->reserve(CompanyLimits::SEATS, 1000, 'seats');
    $limits->release(CompanyLimits::SEATS, 5);

    expect($limits->usage($tenant->id))->toBe([])
        ->and(TenantLimit::query()->count())->toBe(0);
});

it('reserves up to the limit and refuses the next, leaving the count where it was', function (): void {
    $tenant = limitedCompany(CompanyLimits::SEATS, 3, 2);
    $limits = resolve(CompanyLimits::class);

    $limits->reserve(CompanyLimits::SEATS, 1, 'seats');

    expect(usedOf($tenant, 'seats'))->toBe(3);

    $message = refusalOf(fn () => $limits->reserve(CompanyLimits::SEATS, 1, 'seats'));

    expect($message)->toStartWith('Tu plan cubre 3 personas y ya las tienes todas. Pide una ampliación a Humetria')
        ->and(usedOf($tenant, 'seats'))->toBe(3);
});

it('holds the line one below, at and one over the limit, for a single place and for several at once', function (int $limit, int $used, int $asks, bool $passes): void {
    $tenant = limitedCompany(CompanyLimits::SEATS, $limit, $used);
    $reserve = fn () => resolve(CompanyLimits::class)->reserve(CompanyLimits::SEATS, $asks, 'seats');

    if ($passes) {
        $reserve();
        expect(usedOf($tenant, 'seats'))->toBe($used + $asks);
    } else {
        expect($reserve)->toThrow(ValidationException::class)
            ->and(usedOf($tenant, 'seats'))->toBe($used);
    }
})->with([
    'limit minus one, asks one' => [10, 8, 1, true],
    'exactly the limit' => [10, 9, 1, true],
    'limit plus one' => [10, 10, 1, false],
    'the remaining seats exactly' => [10, 7, 3, true],
    'the remaining seats and one more' => [10, 7, 4, false],
    'nothing asked' => [10, 10, 0, true],
]);

it('says how many places are left when an import asks for more, and that nothing was added', function (): void {
    limitedCompany(CompanyLimits::SEATS, 50, 47);

    $message = refusalOf(fn () => resolve(CompanyLimits::class)->reserve(CompanyLimits::SEATS, 5, 'file'));

    expect($message)->toContain('Tu plan cubre 50 personas')
        ->and($message)->toContain('te quedan 3 lugares')
        ->and($message)->toContain('esto agrega 5')
        ->and($message)->toContain('No se agregó a nadie');
});

it('says place, in the singular, when one is left', function (): void {
    limitedCompany(CompanyLimits::SEATS, 50, 49);

    expect(refusalOf(fn () => resolve(CompanyLimits::class)->reserve(CompanyLimits::SEATS, 2, 'file')))->toContain('te queda 1 lugar;');
});

it('keys the refusal under the field of the form that asked', function (): void {
    limitedCompany(CompanyLimits::CENTERS, 1, 1);

    try {
        resolve(CompanyLimits::class)->reserve(CompanyLimits::CENTERS, 1, 'centers');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['centers'])
            ->and($exception->errors()['centers'][0])->toStartWith('Tu plan cubre 1 centro de trabajo y ya lo tienes. Pide una ampliación');
    }
});

it('reserves with a single conditional statement: nothing is read first, so two requests cannot both pass', function (): void {
    limitedCompany(CompanyLimits::SEATS, 5, 2);
    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    resolve(CompanyLimits::class)->reserve(CompanyLimits::SEATS, 1, 'seats');

    expect($statements)->toHaveCount(1)
        ->and($statements[0])->toStartWith('update "tenant_limits" set "used" = "used" + 1')
        ->and($statements[0])->toContain('"used" + ? <= "amount"');
});

it('counts the second of two reservations for the last place as the one that fails', function (): void {
    $tenant = limitedCompany(CompanyLimits::SEATS, 4, 3);
    $limits = resolve(CompanyLimits::class);
    $outcomes = [];

    // Each request reserves in its own transaction; the row they contend for decides, whoever arrives second.
    foreach ([1, 2] as $request) {
        try {
            DB::transaction(fn () => $limits->reserve(CompanyLimits::SEATS, 1, 'seats'));
            $outcomes[$request] = 'passed';
        } catch (ValidationException) {
            $outcomes[$request] = 'refused';
        }
    }

    expect($outcomes)->toBe([1 => 'passed', 2 => 'refused'])
        ->and(usedOf($tenant, 'seats'))->toBe(4);
});

it('rolls the reservation back with the transaction that made it', function (): void {
    $tenant = limitedCompany(CompanyLimits::SEATS, 4, 1);

    try {
        DB::transaction(function (): void {
            resolve(CompanyLimits::class)->reserve(CompanyLimits::SEATS, 2, 'seats');
            throw new RuntimeException('the insert failed');
        });
    } catch (RuntimeException) {
    }

    expect(usedOf($tenant, 'seats'))->toBe(1);
});

it('releases places, never below zero', function (): void {
    $tenant = limitedCompany(CompanyLimits::SEATS, 4, 2);
    $limits = resolve(CompanyLimits::class);

    $limits->release(CompanyLimits::SEATS, 1);
    expect(usedOf($tenant, 'seats'))->toBe(1);

    $limits->release(CompanyLimits::SEATS, 5);
    expect(usedOf($tenant, 'seats'))->toBe(0);
});

it('keeps every company, and every resource, to its own count', function (): void {
    $a = limitedCompany(CompanyLimits::SEATS, 2, 2);
    TenantLimit::factory()->create(['tenant_id' => $a->id, 'resource' => 'centers', 'amount' => 1, 'used' => 0]);
    $b = Tenant::factory()->create();
    TenantLimit::factory()->create(['tenant_id' => $b->id, 'resource' => 'seats', 'amount' => 10, 'used' => 0]);

    // A is full on people but has a center left; B has room.
    workIn($a);
    resolve(CompanyLimits::class)->reserve(CompanyLimits::CENTERS, 1, 'centers');
    expect(fn () => resolve(CompanyLimits::class)->reserve(CompanyLimits::SEATS, 1, 'seats'))->toThrow(ValidationException::class);

    workIn($b);
    resolve(CompanyLimits::class)->reserve(CompanyLimits::SEATS, 1, 'seats');

    expect(usedOf($a, 'seats'))->toBe(2)->and(usedOf($a, 'centers'))->toBe(1)->and(usedOf($b, 'seats'))->toBe(1);
});

it('checks whether there is room without taking it', function (): void {
    $tenant = limitedCompany(CompanyLimits::SEATS, 5, 3);
    $limits = resolve(CompanyLimits::class);

    $limits->ensureRoom(CompanyLimits::SEATS, 2, 'file');

    expect(fn () => $limits->ensureRoom(CompanyLimits::SEATS, 3, 'file'))->toThrow(ValidationException::class)
        ->and(usedOf($tenant, 'seats'))->toBe(3);
});

it('lists what a company has used of each limit', function (): void {
    $tenant = limitedCompany(CompanyLimits::SEATS, 50, 42);
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'centers', 'amount' => 2, 'used' => 3]);

    $rows = resolve(CompanyLimits::class)->usage($tenant->id);

    expect(array_map(fn ($row): array => [$row->resource, $row->label, $row->used, $row->amount, $row->over], $rows))->toBe([
        ['seats', 'Personas', 42, 50, false],
        ['centers', 'Centros de trabajo', 3, 2, true],
    ]);
});

describe('synced from the plan', function (): void {
    it('starts counting from what the company already has: its active people and its centers in use', function (): void {
        $tenant = Tenant::factory()->create();
        $center = WorkCenter::factory()->create(['tenant_id' => $tenant->id]);
        WorkCenter::factory()->archived()->create(['tenant_id' => $tenant->id]);
        Employee::factory()->count(3)->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
        Employee::factory()->deactivated()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);

        resolve(CompanyLimits::class)->sync($tenant->id, ['seats' => 50, 'centers' => 2]);

        $rows = resolve(CompanyLimits::class)->usage($tenant->id);

        expect(array_map(fn ($row): array => [$row->resource, $row->used, $row->amount], $rows))->toBe([['seats', 3, 50], ['centers', 1, 2]]);
    });

    it('moves the limit and keeps the count when the plan changes', function (): void {
        $tenant = limitedCompany(CompanyLimits::SEATS, 50, 48);

        resolve(CompanyLimits::class)->sync($tenant->id, ['seats' => 100]);

        expect(TenantLimit::query()->where('tenant_id', $tenant->id)->sole()->only(['amount', 'used']))->toBe(['amount' => 100, 'used' => 48]);
    });

    it('does not block a company already above its new limit from living: it only cannot add', function (): void {
        $tenant = limitedCompany(CompanyLimits::SEATS, 50, 60);
        $limits = resolve(CompanyLimits::class);

        expect(fn () => $limits->reserve(CompanyLimits::SEATS, 1, 'seats'))->toThrow(ValidationException::class);

        $limits->release(CompanyLimits::SEATS, 11);

        expect(usedOf($tenant, 'seats'))->toBe(49);
        $limits->reserve(CompanyLimits::SEATS, 1, 'seats');
        expect(usedOf($tenant, 'seats'))->toBe(50);
    });

    it('only knows the resources it limits, and not a negative amount', function (): void {
        $tenant = Tenant::factory()->create();

        expect(fn () => resolve(CompanyLimits::class)->sync($tenant->id, ['bytes' => 5]))->toThrow(InvalidArgumentException::class)
            ->and(fn () => resolve(CompanyLimits::class)->sync($tenant->id, ['seats' => -1]))->toThrow(InvalidArgumentException::class);
    });

    it('is refused by the database for a resource it does not know, a negative amount or a negative count', function (): void {
        $tenant = Tenant::factory()->create();

        foreach ([['resource' => 'bytes'], ['amount' => -1], ['used' => -1]] as $values) {
            DB::beginTransaction();
            expect(fn () => TenantLimit::factory()->create(['tenant_id' => $tenant->id, ...$values]))->toThrow(Illuminate\Database\QueryException::class);
            DB::rollBack();
        }
    });
});

describe('reconciled with what is really there', function (): void {
    it('fixes the counters that drifted, logging each correction, and leaves the exact ones alone', function (): void {
        $tenant = Tenant::factory()->create();
        $center = WorkCenter::factory()->create(['tenant_id' => $tenant->id]);
        Employee::factory()->count(4)->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
        TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'seats', 'amount' => 50, 'used' => 9]);
        TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'centers', 'amount' => 2, 'used' => 1]);
        Log::spy();

        $corrections = resolve(ReconcileCompanyLimits::class)->handle();

        expect(array_map(fn ($correction): array => [$correction->tenantId, $correction->resource, $correction->recorded, $correction->actual], $corrections))->toBe([[$tenant->id, 'seats', 9, 4]])
            ->and(usedOf($tenant, 'seats'))->toBe(4)
            ->and(usedOf($tenant, 'centers'))->toBe(1);
        Log::shouldHaveReceived('warning')->once()->with('tenant.limit_reconciled', ['tenant_id' => $tenant->id, 'resource' => 'seats', 'recorded' => 9, 'actual' => 4]);
        expect(resolve(ReconcileCompanyLimits::class)->handle())->toBe([]);
    });

    it('does not count another company\'s people', function (): void {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();
        Employee::factory()->count(2)->create(['tenant_id' => $b->id]);
        TenantLimit::factory()->create(['tenant_id' => $a->id, 'resource' => 'seats', 'amount' => 5, 'used' => 0]);

        expect(resolve(ReconcileCompanyLimits::class)->handle())->toBe([]);
    });

    it('runs from the humetria:reconcile-limits command', function (): void {
        $tenant = limitedCompany(CompanyLimits::SEATS, 5, 3);

        $this->artisan('humetria:reconcile-limits')->expectsOutput('Contadores corregidos: 1')->assertSuccessful();

        expect(usedOf($tenant, 'seats'))->toBe(0);
    });
});
