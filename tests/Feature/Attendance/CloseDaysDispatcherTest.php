<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\DispatchCloseDays;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Events\DayClosed;
use App\Modules\Attendance\Jobs\CloseCenterDays;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Support\LocalRuns;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

mutates(DispatchCloseDays::class, CloseCenterDays::class, LocalRuns::class);

/*
 * The nightly close by center (design of time zones §4, phase 3): a dispatcher every 15 minutes queues, for each center whose
 * local time passed 00:15, one job for that local day; a row unique by task, center and local date makes it exactly once,
 * whatever ticks were missed, ran twice or were retried, and wherever the server's clock is.
 */
beforeEach(function (): void {
    Queue::fake([CloseCenterDays::class]);
    $this->tenant = Tenant::factory()->create();
    $this->cancun = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Cancun']);
    $this->mexico = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Mexico_City']);
    $this->tijuana = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Tijuana']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->mexico->id]);
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $ana->id]);
});

/**
 * The centers whose close was queued, and for which local date.
 *
 * @return array<string, string>
 */
function queuedCloses(): array
{
    return Queue::pushed(CloseCenterDays::class)->mapWithKeys(fn (CloseCenterDays $job): array => [$job->workCenterId => $job->localDate])->all();
}

it('queues each center once its own clock passed 00:15, east to west', function (): void {
    // The night of the 10th already ran everywhere.
    foreach ([$this->cancun, $this->mexico, $this->tijuana] as $center) {
        LocalRuns::claim(DispatchCloseDays::TASK, $center->id, '2026-11-10');
    }
    // Wednesday 11 November: 05:20 UTC is 00:20 in Cancún, 23:20 of the 10th in Mexico City, 21:20 in Tijuana.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 05:20', 'UTC'));
    resolve(DispatchCloseDays::class)->handle();
    expect(queuedCloses())->toBe([$this->cancun->id => '2026-11-11']);

    $this->travelTo(CarbonImmutable::parse('2026-11-11 06:20', 'UTC'));
    resolve(DispatchCloseDays::class)->handle();
    expect(queuedCloses())->toBe([$this->cancun->id => '2026-11-11', $this->mexico->id => '2026-11-11']);

    $this->travelTo(CarbonImmutable::parse('2026-11-11 08:20', 'UTC'));
    resolve(DispatchCloseDays::class)->handle();
    expect(queuedCloses())->toHaveCount(3)->and(queuedCloses()[$this->tijuana->id])->toBe('2026-11-11');
});

it('waits until 00:15 of the center, not a minute earlier', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 00:14:59', 'America/Mexico_City'));
    resolve(DispatchCloseDays::class)->handle();
    expect(queuedCloses())->not->toHaveKey($this->mexico->id);

    $this->travelTo(CarbonImmutable::parse('2026-11-11 00:15:00', 'America/Mexico_City'));
    resolve(DispatchCloseDays::class)->handle();
    expect(queuedCloses())->toHaveKey($this->mexico->id);
});

it('queues a center exactly once per local day, however many ticks run', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 08:20', 'UTC'));

    foreach (range(1, 3) as $tick) {
        resolve(DispatchCloseDays::class)->handle();
        $this->travel(15)->minutes();
    }

    Queue::assertPushed(CloseCenterDays::class, 3);
    expect(DB::table('local_runs')->count())->toBe(3);
});

it('catches up a day whose early ticks were missed, and queues the next day again', function (): void {
    // The first tick of the day comes at 09:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 09:00', 'America/Mexico_City'));
    resolve(DispatchCloseDays::class)->handle();
    $this->travelTo(CarbonImmutable::parse('2026-11-12 00:30', 'America/Mexico_City'));
    resolve(DispatchCloseDays::class)->handle();

    Queue::assertPushed(CloseCenterDays::class, fn (CloseCenterDays $job): bool => $job->workCenterId === $this->mexico->id && $job->localDate === '2026-11-11');
    Queue::assertPushed(CloseCenterDays::class, fn (CloseCenterDays $job): bool => $job->workCenterId === $this->mexico->id && $job->localDate === '2026-11-12');
});

it('queues nothing for a company with no registro', function (): void {
    $other = Tenant::factory()->create();
    WorkCenter::factory()->for($other)->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00', 'UTC'));

    resolve(DispatchCloseDays::class)->handle();

    expect(Queue::pushed(CloseCenterDays::class)->every(fn (CloseCenterDays $job): bool => $job->tenantId === $this->tenant->id))->toBeTrue();
});

it('closes, in the job, the day before yesterday of the center\'s local date, whenever the job runs', function (): void {
    Event::fake([DayClosed::class]);
    Log::spy();
    $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->tijuana->id]);
    AttendanceMark::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $bob->id, 'work_center_id' => $this->tijuana->id, 'type' => MarkType::Start,
        'marked_at' => CarbonImmutable::parse('2026-11-09 09:00', 'America/Tijuana'), 'timezone' => 'America/Tijuana',
    ]);
    // Queued for the 11th, run late, on the 12th: still the 9th is closed.
    $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'America/Tijuana'));

    (new CloseCenterDays($this->tenant->id, $this->tijuana->id, '2026-11-11'))->handle(resolve(App\Modules\Attendance\Actions\CloseDays::class));

    Event::assertDispatched(DayClosed::class, fn (DayClosed $event): bool => $event->employeeId === $bob->id && $event->date === '2026-11-09');
    Event::assertNotDispatched(DayClosed::class, fn (DayClosed $event): bool => $event->date === '2026-11-10');
});

it('retries a job that failed, a few times apart', function (): void {
    $job = new CloseCenterDays($this->tenant->id, $this->mexico->id, '2026-11-11');

    expect($job->tries)->toBe(3)->and($job->backoff)->toBe([60, 300]);
});
