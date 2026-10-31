<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\ScheduleReviews;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Jobs\SendPortalReminder;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

mutates(ScheduleReviews::class);

beforeEach(function (): void {
    // 2026-12-16 07:00 in Mexico City: the validation of 2026-01-15 lapses on 2027-01-15, 30 days later.
    $this->travelTo('2026-12-16 13:00:00');
    Queue::fake();
    $this->tenant = Tenant::factory()->create();
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
});

/**
 * A workplace of an active teleworker of the center, validated on `$validatedOn` (null: never reviewed).
 *
 * @param  array<string, mixed>  $teleworker
 */
function scheduledWorkplace(?string $validatedOn, array $teleworker = [], ?Tenant $tenant = null, ?WorkCenter $center = null): Workplace
{
    $tenant ??= test()->tenant;
    $center ??= test()->center;
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id, ...$teleworker]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $person->id]);

    if ($validatedOn !== null) {
        WorkplaceReviewFactory::new()->decidedAt("{$validatedOn} 18:00:00")->create(['tenant_id' => $tenant->id, 'workplace_id' => $workplace->id]);
    }

    return $workplace;
}

it('requests Sección II for workplaces expiring in 30 days or less, once', function (string $validatedOn, int $created): void {
    $workplace = scheduledWorkplace($validatedOn);

    $first = resolve(ScheduleReviews::class)->handle();
    $second = resolve(ScheduleReviews::class)->handle();
    workIn($this->tenant);

    expect($first)->toBe($created)
        ->and($second)->toBe(0)
        ->and(ChecklistApplication::query()->where('workplace_id', $workplace->id)->where('section', Section::Two)->count())->toBe($created);
    Queue::assertPushed(SendPortalReminder::class, $created);
})->with([
    '30 days left' => ['2026-01-15', 1],
    '31 days left' => ['2026-01-16', 0],
    'expiring in 2 days' => ['2025-12-18', 1],
]);

it('leaves alone expired, never validated, not valid, retired and non-active workplaces, and those with a Sección II already open', function (): void {
    scheduledWorkplace('2025-12-15');
    scheduledWorkplace(null);
    $notValid = scheduledWorkplace(null);
    WorkplaceReviewFactory::new()->notValid()->decidedAt('2026-01-15 18:00:00')->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $notValid->id]);
    scheduledWorkplace('2026-01-15')->update(['retired_at' => now()]);
    scheduledWorkplace('2026-01-15', ['status' => App\Modules\Telework\Enums\TeleworkerStatus::Aspirant]);
    $open = scheduledWorkplace('2026-01-15');
    ChecklistApplication::factory()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $open->id, 'section' => Section::Two]);

    expect(resolve(ScheduleReviews::class)->handle())->toBe(0);
    Queue::assertNothingPushed();
});

it('asks again once the Sección II was answered, and skips a center that has no published list', function (): void {
    $answered = scheduledWorkplace('2026-01-15');
    ChecklistApplication::factory()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $answered->id, 'section' => Section::Two]);
    $bare = WorkCenter::factory()->for($this->tenant)->create();
    scheduledWorkplace('2026-01-15', center: $bare);

    expect(resolve(ScheduleReviews::class)->handle())->toBe(1);
});

it('goes through every usable company and skips the blocked ones', function (): void {
    $other = Tenant::factory()->create();
    $otherCenter = WorkCenter::factory()->for($other)->create();
    ChecklistFactory::new()->published()->create(['tenant_id' => $other->id, 'work_center_id' => $otherCenter->id]);
    $blocked = Tenant::factory()->create(['deactivated_at' => now()]);
    $blockedCenter = WorkCenter::factory()->for($blocked)->create();
    ChecklistFactory::new()->published()->create(['tenant_id' => $blocked->id, 'work_center_id' => $blockedCenter->id]);
    scheduledWorkplace('2026-01-15');
    scheduledWorkplace('2026-01-15', tenant: $other, center: $otherCenter);
    scheduledWorkplace('2026-01-15', tenant: $blocked, center: $blockedCenter);

    expect(resolve(ScheduleReviews::class)->handle())->toBe(2);
    Queue::assertPushed(SendPortalReminder::class, 2);
});

it('logs how many it asked for, without personal data', function (): void {
    Log::spy();
    scheduledWorkplace('2026-01-15');

    resolve(ScheduleReviews::class)->handle();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'telework.reviews_scheduled' && $context === ['applications' => 1])->once();
});
