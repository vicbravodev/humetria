<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Models\Tenant;

mutates(OpenPortal::class);

/*
 * The telework portal of a person already known by id, as their session would open it (SessionPortal): what acts as the
 * person without a request, like the demo's story.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    workIn($this->tenant);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
});

it('opens the portal of an active person of the company, with their open telework period', function (): void {
    Teleworker::factory()->ended()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $open = Teleworker::factory()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

    $portal = resolve(OpenPortal::class)->handle($this->ana->id);

    expect($portal->tenantId)->toBe($this->tenant->id)
        ->and($portal->employeeId)->toBe($this->ana->id)
        ->and($portal->teleworkerId)->toBe($open->id)
        ->and($portal->company)->toBe('Acme')
        ->and($portal->name)->toBe('Ana López')
        ->and($portal->telework)->toBeTrue();
});

it('opens the portal of a person with no telework period too', function (): void {
    expect(resolve(OpenPortal::class)->handle($this->ana->id)->teleworkerId)->toBeNull();
});

it('refuses a person who left or of another company', function (Employee $person): void {
    resolve(OpenPortal::class)->handle($person->id);
})->with([
    'who left' => fn (): Employee => Employee::factory()->deactivated()->create(['tenant_id' => test()->tenant->id]),
    'of another company' => fn (): Employee => Employee::factory()->create(),
])->throws(InvalidArgumentException::class);
