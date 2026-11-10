<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Leave\Actions\AcknowledgeStatement;
use App\Modules\Leave\Actions\GrantEntitlement;
use App\Modules\Leave\Actions\ImportOpeningBalances;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Support\PolicyTerms;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

mutates(AcknowledgeStatement::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2026-01-01']);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'hired_on' => '2024-03-15']);
    // 18:00 UTC is noon in Mexico City.
    $this->travelTo('2026-03-15 18:00:00');
});

function grantYear(Employee $person, int $years = 2, string $anniversary = '2026-03-15'): ?LeaveEntitlement
{
    return resolve(GrantEntitlement::class)->handle($person->id, $years, CarbonImmutable::parse($anniversary), PolicyTerms::statutory(WorkWeek::MonFri));
}

function statementOf(Employee $person): LeaveStatement
{
    grantYear($person);

    return LeaveStatement::query()->withoutGlobalScopes()->where('employee_id', $person->id)->sole();
}

it('issues a statement, not yet acknowledged, with each period granted on its anniversary', function (): void {
    $period = grantYear($this->ana);

    $statement = LeaveStatement::query()->sole();
    expect($statement->only(['tenant_id', 'employee_id', 'leave_entitlement_id', 'acknowledged_at']))->toBe([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => $period?->id, 'acknowledged_at' => null,
    ])->and($statement->issued_at->toDateTimeString())->toBe('2026-03-15 18:00:00');
});

it('issues one statement per period, never a second one', function (): void {
    $period = grantYear($this->ana);

    expect(grantYear($this->ana))->toBeNull()
        ->and(LeaveStatement::query()->count())->toBe(1)
        ->and(fn () => LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => $period?->id]))
        ->toThrow(QueryException::class);
});

it('issues none for an opening balance', function (): void {
    $loaded = resolve(ImportOpeningBalances::class)->handle([['line' => 2, 'employeeId' => $this->ana->id, 'years' => 1, 'anniversary' => '2025-03-15', 'days' => 5]], User::factory()->create()->id);

    expect($loaded)->toBe(1)
        ->and(LeaveStatement::query()->count())->toBe(0);
});

it('keeps only the moment the person confirmed they received it, and logs it', function (): void {
    $statement = statementOf($this->ana);
    $this->travelTo('2026-03-16 15:30:00');
    Log::spy();

    resolve(AcknowledgeStatement::class)->handle($this->ana->id, $statement->id);

    expect($statement->refresh()->acknowledged_at?->toDateTimeString())->toBe('2026-03-16 15:30:00')
        ->and($statement->issued_at->toDateTimeString())->toBe('2026-03-15 18:00:00')
        // No address nor device: only when.
        ->and(Schema::getColumnListing('leave_statements'))->toBe(['id', 'tenant_id', 'employee_id', 'leave_entitlement_id', 'issued_at', 'acknowledged_at']);
    Log::shouldHaveReceived('info')->with('leave.statement_acknowledged', ['statement_id' => $statement->id, 'employee_id' => $this->ana->id])->once();
});

it('refuses a second confirmation and keeps the first moment', function (): void {
    $statement = statementOf($this->ana);
    resolve(AcknowledgeStatement::class)->handle($this->ana->id, $statement->id);
    $this->travelTo('2026-03-20 15:30:00');
    Log::spy();

    expect(fn () => resolve(AcknowledgeStatement::class)->handle($this->ana->id, $statement->id))
        ->toThrow(fn (ValidationException $refused) => expect($refused->errors())->toBe(['statement' => ['Ya confirmó esta constancia.']]))
        ->and($statement->refresh()->acknowledged_at?->toDateTimeString())->toBe('2026-03-15 18:00:00');
    Log::shouldNotHaveReceived('info');
});

it('does not find the statement of another person', function (): void {
    $statement = statementOf(Employee::factory()->create(['tenant_id' => $this->tenant->id]));

    expect(fn () => resolve(AcknowledgeStatement::class)->handle($this->ana->id, $statement->id))->toThrow(ModelNotFoundException::class)
        ->and($statement->refresh()->acknowledged_at)->toBeNull();
});

it('does not read, change nor confirm the statement of another company', function (): void {
    $other = Tenant::factory()->create();
    $stranger = Employee::factory()->create(['tenant_id' => $other->id]);
    workIn($other);
    $statement = statementOf($stranger);

    workIn($this->tenant);
    expect(LeaveStatement::query()->count())->toBe(0)
        ->and(LeaveStatement::query()->find($statement->id))->toBeNull()
        ->and(LeaveStatement::query()->whereKey($statement->id)->update(['acknowledged_at' => now()]))->toBe(0)
        ->and(fn () => resolve(AcknowledgeStatement::class)->handle($stranger->id, $statement->id))->toThrow(ModelNotFoundException::class);

    workIn($other);
    expect(LeaveStatement::query()->sole()->acknowledged_at)->toBeNull();
});
