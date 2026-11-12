<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Data\PortalIdentity;
use App\Modules\Telework\Support\TokenPortalAccess;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;

mutates(TokenPortalAccess::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    workIn($this->tenant);
    $this->token = resolve(IssuePortalLink::class)->handle($this->employee->id);
});

it('opens the same person from the same token as the Telework portal does', function (): void {
    $identity = resolve(PortalAccess::class)->open($this->token);

    expect(resolve(PortalAccess::class))->toBeInstanceOf(TokenPortalAccess::class)
        ->and($identity)->toEqual(new PortalIdentity($this->tenant->id, $this->employee->id, 'Acme', 'Ana Ruiz'));
});

it('answers null, without saying why, for a made-up token, a replaced link and a person who left', function (): void {
    $access = resolve(PortalAccess::class);

    expect($access->open('nope'))->toBeNull();

    resolve(IssuePortalLink::class)->handle($this->employee->id, revoke: true);
    expect($access->open($this->token))->toBeNull();

    $current = resolve(IssuePortalLink::class)->handle($this->employee->id);
    expect($access->open($current))->not->toBeNull();

    $this->employee->forceFill(['deactivated_at' => now()])->save();
    expect($access->open($current))->toBeNull();
});

it('answers null for a company that may not be used', function (): void {
    Tenant::query()->whereKey($this->tenant->id)->update(suspension());

    expect(resolve(PortalAccess::class)->open($this->token))->toBeNull();
});

it('gives the token of a person so other tools can link to their pages, the same one each time', function (): void {
    $access = resolve(PortalAccess::class);

    expect($access->token($this->employee->id))->toBe($this->token)
        ->and($access->token($this->employee->id))->toBe($this->token)
        ->and($access->open($access->token($this->employee->id))?->employeeId)->toBe($this->employee->id);
});
