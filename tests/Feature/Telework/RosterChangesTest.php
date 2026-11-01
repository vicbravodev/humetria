<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Enums\EndReason;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Listeners\EndOnDeactivation;
use App\Modules\Telework\Listeners\EndOnMove;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Notifications\TeleworkMoved;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

mutates(EndOnDeactivation::class, EndOnMove::class, TeleworkMoved::class);

beforeEach(function (): void {
    Notification::fake();
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->warehouse = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Bodega Norte']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    $this->period = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'work_center_id' => $this->plant->id, 'started_at' => '2026-01-10 12:00:00']);
    $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->period->id]);
});

function periodNow(): Teleworker
{
    workIn(test()->tenant);

    return Teleworker::query()->findOrFail(test()->period->id);
}

function deactivate(): void
{
    test()->actingAs(test()->owner)->post(route('tenant.employees.deactivate', [test()->tenant, test()->employee->id]))->assertRedirect();
}

function moveToWarehouse(): void
{
    $employee = test()->employee;
    test()->actingAs(test()->owner)->put(route('tenant.employees.update', [test()->tenant, $employee->id]), [
        'name' => $employee->name, 'email' => $employee->email, 'mobile' => $employee->mobile, 'gender' => $employee->gender->value,
        'work_center_id' => test()->warehouse->id, 'area' => 'Producción',
        'job_title' => $employee->job_title, 'employee_number' => $employee->employee_number,
    ])->assertSessionHasNoErrors();
}

it('ends the period with left_company when the employee is deactivated, keeping the history', function (): void {
    ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);

    deactivate();

    $period = periodNow();
    expect($period->status)->toBe(TeleworkerStatus::Ended)
        ->and($period->end_reason)->toBe(EndReason::LeftCompany)
        ->and($period->ended_at?->toIso8601String())->toBe('2026-03-01T12:00:00+00:00')
        ->and(ChecklistApplication::query()->whereNotNull('cancelled_at')->count())->toBe(1)
        ->and(AuditEntry::query()->where('event', 'telework.teleworker_ended')->sole()->metadata)->toHaveKey('ended_by', null);
    Notification::assertNothingSent();
});

it('ends the period with transfer when the employee moves to another work center, and tells the owner', function (): void {
    moveToWarehouse();

    $period = periodNow();
    expect($period->status)->toBe(TeleworkerStatus::Ended)
        ->and($period->end_reason)->toBe(EndReason::Transfer)
        ->and($period->work_center_id)->toBe($this->plant->id);
    Notification::assertSentOnDemandTimes(TeleworkMoved::class, 1);
    Notification::assertSentOnDemand(TeleworkMoved::class, function (TeleworkMoved $notification, array $channels, object $notifiable): bool {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === $this->owner->email
            && $channels === ['mail']
            && str_contains($mail->subject, 'Ana Ruiz')
            && str_contains(implode(' ', $mail->introLines), 'Planta Querétaro')
            && str_contains(implode(' ', $mail->introLines), 'Bodega Norte')
            && str_contains((string) $mail->actionUrl, "/admin/acme/telework/teleworkers/{$this->period->id}");
    });
});

it('queues the move notice, which carries no secret', function (): void {
    expect(new TeleworkMoved('Ana', 'A', 'B', 'https://x.test'))->toBeInstanceOf(ShouldQueue::class);
});

it('does nothing when the person has no open period, whether never in telework or already ended', function (): void {
    $this->period->update(['status' => TeleworkerStatus::Ended, 'ended_at' => '2026-02-01 12:00:00', 'end_reason' => EndReason::Reversibility]);

    deactivate();

    expect(periodNow()->end_reason)->toBe(EndReason::Reversibility);
    expect(AuditEntry::query()->where('event', 'telework.teleworker_ended')->count())->toBe(0);
    Notification::assertNothingSent();

    $other = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $other->id]))->assertRedirect();
    Notification::assertNothingSent();
});

it('does not notify when a move finds nothing to end', function (): void {
    $this->period->update(['status' => TeleworkerStatus::Ended, 'ended_at' => '2026-02-01 12:00:00', 'end_reason' => EndReason::Other]);

    moveToWarehouse();

    Notification::assertNothingSent();
});

it('ends an aspirant too', function (): void {
    $this->period->update(['status' => TeleworkerStatus::Aspirant, 'started_at' => null]);

    deactivate();

    expect(periodNow()->end_reason)->toBe(EndReason::LeftCompany);
});

it('ends only the period of that person and company', function (): void {
    $other = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $theirs = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $other->id, 'work_center_id' => $this->plant->id]);
    $rival = Tenant::factory()->create();
    $rivalPeriod = TeleworkerFactory::new()->active()->create(['tenant_id' => $rival->id]);

    deactivate();

    expect(Teleworker::query()->withoutGlobalScopes()->whereKey([$theirs->id, $rivalPeriod->id])->pluck('status')->unique()->all())->toBe([TeleworkerStatus::Active]);
});
