<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

mutates(DeactivateEmployee::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    resolve(CompanyLimits::class)->sync($this->tenant->id, ['seats' => 5]);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'hired_on' => '2020-03-01']);
    resolve(CompanyLimits::class)->reconcile();
});

it('does nothing when the same person is deactivated a second time with a stale copy of them (a double submit)', function (): void {
    Event::fake([EmployeeDeactivated::class]);
    Log::spy();
    $first = Employee::query()->findOrFail($this->ana->id);
    $second = Employee::query()->findOrFail($this->ana->id);

    resolve(DeactivateEmployee::class)->handle($first, effectiveOn: CarbonImmutable::parse('2026-02-15'));
    resolve(DeactivateEmployee::class)->handle($second, effectiveOn: CarbonImmutable::parse('2026-03-01'));

    expect(Employee::query()->withoutGlobalScopes()->findOrFail($this->ana->id)->terminated_on?->toDateString())->toBe('2026-02-15')
        ->and(AuditEntry::query()->where('event', 'employee.deactivated')->count())->toBe(1)
        ->and(TenantLimit::query()->where('tenant_id', $this->tenant->id)->where('resource', 'seats')->firstOrFail()->used)->toBe(0);
    Event::assertDispatchedTimes(EmployeeDeactivated::class, 1);
    Log::shouldHaveReceived('info')->with('employee.deactivated', ['employee_id' => $this->ana->id])->once();
});

it('writes its log only once the deactivation has committed', function (): void {
    Log::spy();
    Event::listen(EmployeeDeactivated::class, fn () => throw new RuntimeException('A listener failed.'));

    expect(fn () => resolve(DeactivateEmployee::class)->handle($this->ana))->toThrow(RuntimeException::class)
        ->and($this->ana->refresh()->deactivated_at)->toBeNull();
    Log::shouldNotHaveReceived('info', ['employee.deactivated', ['employee_id' => $this->ana->id]]);
});

it('does not log the deactivation when it is part of a transaction that rolls back', function (): void {
    Log::spy();

    try {
        DB::transaction(function (): void {
            resolve(DeactivateEmployee::class)->handle($this->ana);

            throw new RuntimeException('Rolled back.');
        });
    } catch (RuntimeException) {
        // The outer transaction undid the deactivation.
    }

    expect($this->ana->refresh()->deactivated_at)->toBeNull();
    Log::shouldNotHaveReceived('info', ['employee.deactivated', ['employee_id' => $this->ana->id]]);
});
