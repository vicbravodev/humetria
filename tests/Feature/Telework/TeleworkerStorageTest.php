<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\PortalLink;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\PortalLinkFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** The write fails in its own savepoint, so the test's transaction survives it. */
function refused(Closure $attempt, string $exception = QueryException::class): void
{
    expect(fn () => DB::transaction($attempt))->toThrow($exception);
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('builds a teleworker with its workplace, application and link inside one company', function (): void {
    $application = ChecklistApplicationFactory::new()->create();
    workIn(tenantOf($application->tenant_id));
    $workplace = Workplace::query()->findOrFail($application->workplace_id);
    $teleworker = Teleworker::query()->findOrFail($workplace->teleworker_id);

    expect($teleworker->status)->toBe(TeleworkerStatus::Aspirant)
        ->and($application->section)->toBe(Section::One)
        ->and($application->submitted_at)->toBeNull()
        ->and($workplace->tenant_id)->toBe($teleworker->tenant_id)
        ->and(PortalLinkFactory::new()->create(['employee_id' => $teleworker->employee_id, 'tenant_id' => $teleworker->tenant_id])->token_hash)->toHaveLength(64);
});

it('accepts a telework percentage over 40 and refuses 40 straight in the database', function (int $percentage, bool $accepted): void {
    $create = fn (): Teleworker => TeleworkerFactory::new()->create(['telework_percentage' => $percentage]);

    if ($accepted) {
        expect($create()->telework_percentage)->toBe($percentage);
    } else {
        refused($create);
    }
})->with([[41, true], [100, true], [40, false], [101, false], [0, false]]);

it('allows one open period per employee and a new one after the last ended', function (): void {
    $first = TeleworkerFactory::new()->create();
    $again = fn () => TeleworkerFactory::new()->create(['employee_id' => $first->employee_id, 'work_center_id' => $first->work_center_id, 'tenant_id' => $first->tenant_id]);

    refused($again, UniqueConstraintViolationException::class);

    $first->forceFill(['status' => TeleworkerStatus::Ended, 'started_at' => now()->subYear(), 'ended_at' => now(), 'end_reason' => 'reversibility'])->save();

    expect($again()->status)->toBe(TeleworkerStatus::Aspirant);
});

it('keeps one pending application per workplace and section', function (): void {
    $application = ChecklistApplicationFactory::new()->create();
    $second = fn () => ChecklistApplicationFactory::new()->create(['workplace_id' => $application->workplace_id, 'checklist_id' => $application->checklist_id, 'tenant_id' => $application->tenant_id]);

    refused($second, UniqueConstraintViolationException::class);

    $application->forceFill(['submitted_at' => now()])->save();

    expect($second()->section)->toBe(Section::One);
});

it('keeps one portal link per employee', function (): void {
    $link = PortalLinkFactory::new()->create();

    refused(fn () => PortalLinkFactory::new()->create(['employee_id' => $link->employee_id, 'tenant_id' => $link->tenant_id]), UniqueConstraintViolationException::class);
});

it('does not let company B read or reference the rows of company A', function (): void {
    $application = ChecklistApplicationFactory::new()->create();
    $link = PortalLinkFactory::new()->create();
    $other = Tenant::factory()->create();
    workIn($other);

    expect(Teleworker::query()->count())->toBe(0)
        ->and(Workplace::query()->count())->toBe(0)
        ->and(PortalLink::query()->count())->toBe(0)
        ->and(ChecklistApplication::query()->count())->toBe(0);

    $foreign = EmployeeFactory::new()->create(['tenant_id' => $other->id]);

    // A teleworker of B cannot point at A's employee or checklist: the composite keys are per company.
    refused(fn () => TeleworkerFactory::new()->create(['tenant_id' => $other->id, 'employee_id' => $link->employee_id, 'work_center_id' => $foreign->work_center_id]));
    refused(fn () => ChecklistApplicationFactory::new()->create(['tenant_id' => $other->id, 'workplace_id' => $application->workplace_id, 'checklist_id' => $application->checklist_id]));

    expect(Employee::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('builds a published checklist for a work center', function (): void {
    expect(ChecklistFactory::new()->published()->create()->status->value)->toBe('published');
});
