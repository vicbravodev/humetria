<?php

declare(strict_types=1);

use App\Modules\Results\Contracts\SmallGroups;
use App\Modules\Results\Support\GroupMinimum;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;

mutates(GroupMinimum::class);

/** Works inside a company, as a request of its panel would. */
function inCompanyWithMinimum(?int $minimum): Tenant
{
    $tenant = Tenant::factory()->create(['min_group' => $minimum]);
    resolve(Companies::class)->enter($tenant->id);
    app()->forgetScopedInstances();

    return $tenant;
}

it('applies the minimum the company chose, lower than the default', function (): void {
    inCompanyWithMinimum(3);
    $minimum = resolve(GroupMinimum::class);

    expect($minimum->isShown(2))->toBeFalse()
        ->and($minimum->isShown(3))->toBeTrue()
        ->and($minimum->suppress(['a' => 9, 'b' => 3, 'c' => 2])->visible)->toBe(['a'])
        ->and(resolve(SmallGroups::class)->visibleKeys(['a' => 3, 'b' => 4]))->toBe(['b', 'a']);
});

it('shows every group when the company turned the minimum off, with no complementary hiding', function (): void {
    inCompanyWithMinimum(1);

    expect(resolve(GroupMinimum::class)->suppress(['a' => 1, 'b' => 7])->visible)->toBe(['b', 'a'])
        ->and(resolve(GroupMinimum::class)->isShown(0))->toBeFalse();
});

it('applies the minimum the company chose, higher than the default', function (): void {
    inCompanyWithMinimum(12);

    expect(resolve(GroupMinimum::class)->isShown(11))->toBeFalse()
        ->and(resolve(GroupMinimum::class)->isShown(12))->toBeTrue();
});

it('applies a fixed minimum instead of the company one, and leaves the original alone', function (): void {
    inCompanyWithMinimum(3);
    $company = resolve(GroupMinimum::class);
    $frozen = $company->at(8);

    expect($frozen->isShown(7))->toBeFalse()
        ->and($frozen->isShown(8))->toBeTrue()
        ->and($company->suppress(['a' => 9, 'b' => 8, 'c' => 3])->visible)->toBe(['a', 'b', 'c'])
        ->and($frozen->suppress(['a' => 9, 'b' => 8, 'c' => 3])->visible)->toBe(['a'])
        ->and($frozen)->toBeInstanceOf(SmallGroups::class)
        ->and($company->isShown(3))->toBeTrue();
});

it('falls back to the platform default outside any company', function (): void {
    config(['results.min_group' => 5]);

    expect(resolve(GroupMinimum::class)->isShown(4))->toBeFalse()
        ->and(resolve(GroupMinimum::class)->isShown(5))->toBeTrue();
});
