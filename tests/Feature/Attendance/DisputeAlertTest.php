<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\DisputeWeek;
use App\Modules\Attendance\Actions\ResolveDispute;
use App\Modules\Attendance\Jobs\SendDisputeAlert;
use App\Modules\Attendance\Listeners\AlertDispute;
use App\Modules\Attendance\Notifications\WeekDisputedAlert;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

mutates(SendDisputeAlert::class, AlertDispute::class, WeekDisputedAlert::class);

// Criterio 5.2: a disputed week stays in view of RH until resolved; RH learns of it by email at once (H30). Ids travel,
// never the reason: that is read in Humetría.

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = ownerOf($this->tenant);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana López']);
    $this->device = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

function disputeAnasWeek(object $test): string
{
    $monday = CarbonImmutable::parse('2026-11-02', 'America/Mexico_City');
    $digest = WeekDigest::of($test->ana->id, WeekPeriod::ofDate($monday, 'America/Mexico_City'));

    return resolve(DisputeWeek::class)->handle($test->ana->id, $monday, 'El martes salí a las 20:00, no a las 18:00.', $test->device->id, $digest)->id;
}

it('queues the alert with ids only, three tries a minute and five minutes apart', function (): void {
    Queue::fake();

    $id = disputeAnasWeek($this);

    Queue::assertPushed(SendDisputeAlert::class, fn (SendDisputeAlert $job): bool => $job->tenantId === $this->tenant->id && $job->confirmationId === $id
        && $job->tries === 3 && $job->backoff === [60, 300]);
});

it('emails RH who disputed which week and where to resolve it, never the reason', function (): void {
    Notification::fake();

    disputeAnasWeek($this);

    Notification::assertSentOnDemand(WeekDisputedAlert::class, function (WeekDisputedAlert $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $notification->toMail($notifiable);
        $text = json_encode($mail->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $notifiable->routes['mail'] === $this->owner->email
            && $mail->subject === 'Ana López objetó su semana'
            && str_contains($text, 'Ana López · Planta Norte · semana del 2 al 8 de noviembre de 2026')
            && $mail->actionText === 'Revisar objeción'
            && $mail->actionUrl === route('tenant.attendance.index', ['acme', 'status' => 'disputed', 'week' => '2026-11-02'])
            && ! str_contains($text, '20:00');
    });
});

it('emails nobody once the dispute was resolved, nor in a company without Asistencia', function (string $case): void {
    Notification::fake();
    Queue::fake();
    $id = disputeAnasWeek($this);

    if ($case === 'resolved') {
        resolve(ResolveDispute::class)->handle($id, memberOf($this->tenant, TenantRole::Hr)->id, 'Revisamos el lector y estaba bien.');
    } else {
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
    }

    app()->call([new SendDisputeAlert($this->tenant->id, $id), 'handle']);

    Notification::assertSentOnDemandTimes(WeekDisputedAlert::class, 0);
})->with(['resolved', 'without Asistencia']);
