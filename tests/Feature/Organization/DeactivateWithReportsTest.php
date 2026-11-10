<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Http\Requests\DeactivateEmployeeRequest;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Event;

mutates(DeactivateEmployee::class, DeactivateEmployeeRequest::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    workIn($this->tenant);
    // Root has Mid, Eva and Gil under them; Mid has Ana and Luis, who left; Ana has Zoe.
    $this->root = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->mid = Employee::factory()->reportingTo($this->root)->create();
    $this->eva = Employee::factory()->reportingTo($this->root)->create();
    $this->gil = Employee::factory()->reportingTo($this->root)->create();
    $this->ana = Employee::factory()->reportingTo($this->mid)->create();
    $this->luis = Employee::factory()->reportingTo($this->mid)->deactivated()->create();
    $this->zoe = Employee::factory()->reportingTo($this->ana)->create();
});

function deactivateWith(mixed $test, Employee $employee, array $data = []): Illuminate\Testing\TestResponse
{
    return $test->actingAs($test->owner)->post(route('tenant.employees.deactivate', [$test->tenant, $employee->id]), $data);
}

/** @return array<string, string|null> each person's manager, by the name the test gives them */
function reportingLinesOf(mixed $test): array
{
    return array_map(fn (string $who): ?string => $test->{$who}->refresh()->manager_id, array_combine($people = ['root', 'mid', 'eva', 'gil', 'ana', 'luis', 'zoe'], $people));
}

it('asks whom the direct reports of who leaves will report to, counting only the active ones', function (): void {
    Event::fake([EmployeeDeactivated::class]);

    deactivateWith($this, $this->root)->assertSessionHasErrors(['reassign_to' => 'Elige a quién le reportarán sus 3 reportes directos.']);
    deactivateWith($this, $this->root, ['reassign_to' => ''])->assertSessionHasErrors(['reassign_to' => 'Elige a quién le reportarán sus 3 reportes directos.']);
    // Luis left: only Ana reports to Mid.
    deactivateWith($this, $this->mid)->assertSessionHasErrors(['reassign_to' => 'Elige a quién le reportará su reporte directo.']);

    expect($this->root->refresh()->deactivated_at)->toBeNull()
        ->and($this->mid->refresh()->deactivated_at)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
    Event::assertNotDispatched(EmployeeDeactivated::class);
});

it('moves the active direct reports to whoever is chosen, their manager for one, and then deactivates', function (): void {
    Event::fake([EmployeeDeactivated::class]);

    deactivateWith($this, $this->mid, ['reassign_to' => $this->root->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $entry = AuditEntry::query()->where('event', 'employee.manager_changed')->sole();

    expect($this->mid->refresh()->deactivated_at)->not->toBeNull()
        // Whoever leaves keeps their place, should they come back; Luis, who had left, is not moved.
        ->and(reportingLinesOf($this))->toBe(['root' => null, 'mid' => $this->root->id, 'eva' => $this->root->id, 'gil' => $this->root->id, 'ana' => $this->root->id, 'luis' => $this->mid->id, 'zoe' => $this->ana->id])
        ->and($entry->subject_id)->toBe($this->ana->id)
        ->and($entry->metadata)->toEqual(['from_manager_id' => $this->mid->id, 'to_manager_id' => $this->root->id]);
    Event::assertDispatched(EmployeeDeactivated::class, fn (EmployeeDeactivated $event): bool => $event->employeeId === $this->mid->id);
});

it('deactivates someone without active reports as always, whatever is sent', function (): void {
    deactivateWith($this, $this->zoe)->assertSessionHasNoErrors();
    // Zoe left: Ana has nobody to hand over.
    deactivateWith($this, $this->ana)->assertSessionHasNoErrors();

    expect($this->zoe->refresh()->deactivated_at)->not->toBeNull()
        ->and($this->ana->refresh()->deactivated_at)->not->toBeNull()
        ->and($this->zoe->manager_id)->toBe($this->ana->id)
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('lets one of the direct reports of who is at the top take their place', function (): void {
    deactivateWith($this, $this->root, ['reassign_to' => $this->eva->id])->assertSessionHasNoErrors();

    expect($this->root->refresh()->deactivated_at)->not->toBeNull()
        ->and(reportingLinesOf($this))->toBe(['root' => null, 'mid' => $this->eva->id, 'eva' => null, 'gil' => $this->eva->id, 'ana' => $this->mid->id, 'luis' => $this->mid->id, 'zoe' => $this->ana->id])
        // Eva to the top, Mid and Gil under her.
        ->and(AuditEntry::query()->where('event', 'employee.manager_changed')->count())->toBe(3);
});

it('lets someone two levels below take the place of who leaves, under the same manager', function (): void {
    // Zoe, under Ana under Mid, takes the place of Mid, who reports to Root.
    deactivateWith($this, $this->mid, ['reassign_to' => $this->zoe->id])->assertSessionHasNoErrors();

    expect($this->mid->refresh()->deactivated_at)->not->toBeNull()
        ->and(reportingLinesOf($this))->toBe(['root' => null, 'mid' => $this->root->id, 'eva' => $this->root->id, 'gil' => $this->root->id, 'ana' => $this->zoe->id, 'luis' => $this->mid->id, 'zoe' => $this->root->id]);
});

it('rejects a replacement who cannot be it', function (Closure $replacement, string $message): void {
    deactivateWith($this, $this->mid, ['reassign_to' => $replacement($this)])->assertSessionHasErrors(['reassign_to' => $message]);

    expect($this->mid->refresh()->deactivated_at)->toBeNull()
        ->and($this->ana->refresh()->manager_id)->toBe($this->mid->id);
})->with([
    'who leaves' => [fn ($test): string => $test->mid->id, 'Una persona no puede ser su propio jefe.'],
    'someone who left' => [fn ($test): string => $test->luis->id, 'Esa persona está dada de baja.'],
    'someone of another company' => [fn (): string => Employee::factory()->create()->id, 'Elige a una persona de la lista.'],
    'not an id' => [fn (): string => 'root', 'Elige a una persona de la lista.'],
]);

it('still answers 404 for another company\'s employee and 409 for one who already left', function (): void {
    deactivateWith($this, Employee::factory()->create())->assertNotFound();
    deactivateWith($this, $this->luis)->assertConflict();
    $this->actingAs(memberOf($this->tenant, App\Modules\Tenancy\Enums\TenantRole::Viewer))
        ->post(route('tenant.employees.deactivate', [$this->tenant, $this->mid->id]), ['reassign_to' => $this->root->id])
        ->assertForbidden();

    expect($this->mid->refresh()->deactivated_at)->toBeNull();
});

it('refuses to leave reports without a manager when nobody was chosen', function (): void {
    expect(fn () => resolve(DeactivateEmployee::class)->handle($this->mid))->toThrow(LogicException::class);

    expect($this->mid->refresh()->deactivated_at)->toBeNull();
});
