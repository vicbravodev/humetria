<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\ListUserTenants;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;

mutates(ListUserTenants::class);

/** A person with `$count` companies named in order, and another person's company that is never theirs. */
function personWithCompanies(int $count): User
{
    $user = User::factory()->create();

    foreach (range(1, $count) as $index) {
        TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => sprintf('Empresa %02d', $index)]))->for($user)->create();
    }

    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'De otra persona']))->create();

    return $user;
}

it('lists the companies of a person by name, and none of anybody else\'s', function (): void {
    $page = resolve(ListUserTenants::class)->handle(personWithCompanies(3)->id, 10);

    expect($page->total())->toBe(3)
        ->and($page->getCollection()->pluck('trade_name')->all())->toBe(['Empresa 01', 'Empresa 02', 'Empresa 03']);
});

it('asks the database once when the companies fit on the first page', function (): void {
    $user = personWithCompanies(3);
    $this->expectsDatabaseQueryCount(1);

    resolve(ListUserTenants::class)->handle($user->id, 10);
});

it('knows the total at the exact size of the page without counting', function (): void {
    $user = personWithCompanies(10);
    $this->expectsDatabaseQueryCount(1);

    $page = resolve(ListUserTenants::class)->handle($user->id, 10);

    expect($page->total())->toBe(10)->and($page->count())->toBe(10)->and($page->hasMorePages())->toBeFalse();
});

it('counts them all, and shows only a page, from one more than the page', function (): void {
    $page = resolve(ListUserTenants::class)->handle(personWithCompanies(11)->id, 10);

    expect($page->total())->toBe(11)->and($page->count())->toBe(10)->and($page->hasMorePages())->toBeTrue()
        ->and($page->getCollection()->last()?->trade_name)->toBe('Empresa 10');
});

it('gives the rest on the next page', function (): void {
    $user = personWithCompanies(11);
    request()->merge(['page' => 2]);

    $page = resolve(ListUserTenants::class)->handle($user->id, 10);

    expect($page->total())->toBe(11)->and($page->currentPage())->toBe(2)->and($page->getCollection()->pluck('trade_name')->all())->toBe(['Empresa 11']);
});

it('has a total of zero for a person without companies, also on a page beyond the last', function (): void {
    $user = User::factory()->create();
    expect(resolve(ListUserTenants::class)->handle($user->id, 10)->total())->toBe(0);

    $other = personWithCompanies(2);
    request()->merge(['page' => 5]);
    $page = resolve(ListUserTenants::class)->handle($other->id, 10);

    expect($page->total())->toBe(2)->and($page->count())->toBe(0);
});

it('lists only the company it is limited to, though the person has more', function (): void {
    $user = personWithCompanies(3);
    $only = Tenant::query()->where('trade_name', 'Empresa 02')->sole();

    $page = resolve(ListUserTenants::class)->handle($user->id, 10, $only->id);

    expect($page->total())->toBe(1)->and($page->getCollection()->pluck('trade_name')->all())->toBe(['Empresa 02']);
});

it('lists nothing when the company it is limited to is not the person\'s', function (): void {
    $user = personWithCompanies(2);
    $foreign = Tenant::query()->where('trade_name', 'De otra persona')->sole();

    expect(resolve(ListUserTenants::class)->handle($user->id, 10, $foreign->id)->total())->toBe(0);
});
