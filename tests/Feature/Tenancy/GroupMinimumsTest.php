<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\GroupMinimums;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\CompanyGroupMinimums;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

mutates(CompanyGroupMinimums::class);

/** The minimum the company applies, read as a request inside it would. */
function minimumOf(Tenant $tenant): int
{
    resolve(Companies::class)->enter($tenant->id);
    app()->forgetScopedInstances();

    return resolve(GroupMinimums::class)->current();
}

it('follows the platform default while the company has not chosen', function (): void {
    $tenant = Tenant::factory()->create();

    expect(minimumOf($tenant))->toBe(5)
        ->and(resolve(GroupMinimums::class)->own())->toBeNull();

    config(['results.min_group' => 8]);

    expect(minimumOf($tenant))->toBe(8);
});

it('uses the minimum the company chose, lower or higher than the default', function (int $chosen): void {
    $tenant = Tenant::factory()->create(['min_group' => $chosen]);

    expect(minimumOf($tenant))->toBe($chosen)
        ->and(resolve(GroupMinimums::class)->own())->toBe($chosen);
})->with([1, 3, 5, 20, 50]);

it('keeps one company minimum from reaching another', function (): void {
    $strict = Tenant::factory()->create(['min_group' => 12]);
    $open = Tenant::factory()->create(['min_group' => 1]);
    $default = Tenant::factory()->create();

    expect(minimumOf($strict))->toBe(12)
        ->and(minimumOf($open))->toBe(1)
        ->and(minimumOf($default))->toBe(5);
});

it('refuses in the database a minimum outside 1 to 50', function (int $chosen): void {
    $tenant = Tenant::factory()->create();

    expect(fn () => DB::table('tenants')->where('id', $tenant->id)->update(['min_group' => $chosen]))->toThrow(QueryException::class);
})->with([0, -1, 51]);

it('states the platform default and the bounds', function (): void {
    expect(GroupMinimums::LOWEST)->toBe(1)
        ->and(GroupMinimums::HIGHEST)->toBe(50)
        ->and(resolve(GroupMinimums::class)->platformDefault())->toBe(5);
});

it('answers with the platform default outside any company instead of failing', function (): void {
    expect(resolve(GroupMinimums::class)->current())->toBe(5)
        ->and(resolve(GroupMinimums::class)->own())->toBeNull();
});

it('learns the minimum from the company row a request already loaded, with no query', function (): void {
    $tenant = Tenant::factory()->create(['min_group' => 7]);
    minimumOf($tenant);
    app()->forgetScopedInstances();
    resolve(CompanyGroupMinimums::class)->prime(Tenant::query()->findOrFail($tenant->id));
    DB::enableQueryLog();

    expect(resolve(GroupMinimums::class)->current())->toBe(7)
        ->and(DB::getQueryLog())->toBe([]);
});

it('reads the minimum itself when the row loaded left it out', function (): void {
    $tenant = Tenant::factory()->create(['min_group' => 7]);
    minimumOf($tenant);
    app()->forgetScopedInstances();
    resolve(CompanyGroupMinimums::class)->prime(Tenant::query()->select('id')->findOrFail($tenant->id));

    expect(resolve(GroupMinimums::class)->current())->toBe(7);
});

it('reads no query to know the minimum once the company is the current one', function (): void {
    $tenant = Tenant::factory()->create(['min_group' => 7]);
    app()->forgetScopedInstances();
    workIn($tenant);

    DB::enableQueryLog();

    expect(resolve(GroupMinimums::class)->current())->toBe(7)
        ->and(DB::getQueryLog())->toBe([]);
});
