<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerCredentials;
use App\Modules\WorkerAccess\Data\CredentialSummary;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $this->beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $this->credentials = resolve(WorkerCredentials::class);
});

it('counts the active and the revoked keys of each person, and leaves out who has none', function (): void {
    WorkerCredential::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'revoked_at' => now()]);
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id, 'revoked_at' => now()]);
    $carla = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);

    $counts = $this->credentials->countsByEmployee([$this->ana->id, $this->beto->id, $carla->id]);

    expect(array_keys($counts))->toEqualCanonicalizing([$this->ana->id, $this->beto->id])
        ->and($counts[$this->ana->id])->active->toBe(2)->revoked->toBe(1)
        ->and($counts[$this->beto->id])->active->toBe(0)->revoked->toBe(1);
});

it('lists the keys of the people asked, the active first and then the newest', function (): void {
    $old = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'Old', 'registered_at' => now()->subDays(5)]);
    $new = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'New', 'registered_at' => now()->subDay(), 'backup_eligible' => true]);
    $revoked = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'Gone', 'registered_at' => now(), 'revoked_at' => now()]);

    $listed = $this->credentials->ofEmployees([$this->ana->id, $this->beto->id]);

    expect(array_keys($listed))->toBe([$this->ana->id])
        ->and(array_map(fn (CredentialSummary $key): string => $key->id, $listed[$this->ana->id]))->toBe([$new->id, $old->id, $revoked->id])
        ->and($listed[$this->ana->id][0])->label->toBe('New')->synced->toBeTrue()->revokedAt->toBeNull()
        ->and($listed[$this->ana->id][1]->synced)->toBeFalse()
        ->and($listed[$this->ana->id][2]->revokedAt?->toIso8601String())->toBe('2026-11-07T15:00:00+00:00');
});

it('never shows the keys of another company', function (): void {
    $other = WorkerCredential::factory()->create();

    expect($this->credentials->countsByEmployee([$other->employee_id]))->toBe([])
        ->and($this->credentials->ofEmployees([$other->employee_id]))->toBe([]);
});
