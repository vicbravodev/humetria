<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Actions\SetManager;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\ManagerRules;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

mutates(ManagerRules::class, SetManager::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    // Ana reports to Beto, who reports to Carla; Dora is on her own.
    $this->carla = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->beto = Employee::factory()->reportingTo($this->carla)->create();
    $this->ana = Employee::factory()->reportingTo($this->beto)->create();
    $this->dora = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->rules = resolve(ManagerRules::class);
});

it('accepts as manager an active person of the company who does not report to the person', function (): void {
    expect($this->rules->violation($this->ana->id, $this->carla->id))->toBeNull()
        ->and($this->rules->violation($this->carla->id, $this->dora->id))->toBeNull()
        ->and($this->rules->violation(null, $this->ana->id))->toBeNull();
});

it('rejects a manager who does not exist or is of another company', function (?string $employee): void {
    $employeeId = $employee === null ? null : $this->ana->id;

    expect($this->rules->violation($employeeId, Employee::factory()->create()->id))->toBe('Elige a una persona de la lista.')
        ->and($this->rules->violation($employeeId, fake()->uuid()))->toBe('Elige a una persona de la lista.')
        ->and($this->rules->violation($employeeId, 'not-a-uuid'))->toBe('Elige a una persona de la lista.');
})->with(['an existing person' => 'ana', 'a new person' => null]);

it('rejects a manager who left', function (): void {
    $this->dora->update(['deactivated_at' => now()]);

    expect($this->rules->violation($this->ana->id, $this->dora->id))->toBe('Esa persona está dada de baja.')
        ->and($this->rules->violation(null, $this->dora->id))->toBe('Esa persona está dada de baja.');
});

it('rejects a person as their own manager', function (): void {
    expect($this->rules->violation($this->ana->id, $this->ana->id))->toBe('Una persona no puede ser su propio jefe.');
});

it('rejects a manager who reports to the person, directly or not', function (): void {
    expect($this->rules->violation($this->carla->id, $this->ana->id))->toBe('Esa persona le reporta, directa o indirectamente. Elige a alguien más.')
        ->and($this->rules->violation($this->carla->id, $this->beto->id))->toBe('Esa persona le reporta, directa o indirectamente. Elige a alguien más.');
});

it('changes the manager, to the log and the audit trail', function (): void {
    Log::spy();

    $changed = resolve(SetManager::class)->handle($this->ana, $this->dora->id);

    $entry = AuditEntry::query()->where('event', 'employee.manager_changed')->sole();
    expect($changed)->toBeTrue()
        ->and($this->ana->refresh()->manager_id)->toBe($this->dora->id)
        ->and($entry->subject_id)->toBe($this->ana->id)
        ->and($entry->subject_type)->toBe($this->ana->getMorphClass())
        ->and($entry->metadata)->toEqual(['from_manager_id' => $this->beto->id, 'to_manager_id' => $this->dora->id]);
    // Ids only: the log never carries anybody's name.
    Log::shouldHaveReceived('info')->once()->with('employee.manager_changed', [
        'employee_id' => $this->ana->id,
        'from_manager_id' => $this->beto->id,
        'to_manager_id' => $this->dora->id,
    ]);
});

it('writes nothing when the manager is the same', function (?string $who): void {
    Log::spy();
    $employee = $who === null ? $this->carla : $this->ana;

    expect(resolve(SetManager::class)->handle($employee, $who === null ? null : $this->beto->id))->toBeFalse()
        ->and($employee->refresh()->manager_id)->toBe($who === null ? null : $this->beto->id)
        ->and(AuditEntry::query()->count())->toBe(0);
    Log::shouldNotHaveReceived('info');
})->with(['the same manager' => 'beto', 'still no manager' => null]);

it('leaves a person with no manager', function (): void {
    expect(resolve(SetManager::class)->handle($this->ana, null))->toBeTrue()
        ->and($this->ana->refresh()->manager_id)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'employee.manager_changed')->sole()->metadata)
        ->toEqual(['from_manager_id' => $this->beto->id, 'to_manager_id' => null]);
});

it('writes neither the log nor the audit trail when the change is rejected', function (): void {
    Log::spy();

    // The trigger is the backstop for a race: Carla under Ana closes a cycle.
    expect(fn () => resolve(SetManager::class)->handle($this->carla, $this->ana->id))->toThrow(Illuminate\Database\QueryException::class, 'employees_manager_cycle')
        ->and(AuditEntry::query()->count())->toBe(0);
    Log::shouldNotHaveReceived('info');
});
