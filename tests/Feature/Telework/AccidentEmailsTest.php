<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\RemindUnansweredAccidents;
use App\Modules\Telework\Jobs\SendAccidentAlert;
use App\Modules\Telework\Notifications\AccidentReported;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(SendAccidentAlert::class, RemindUnansweredAccidents::class, AccidentReported::class);

// Rediseño v2 (docs/02, «Aviso de accidente»; board V3-Correos): RH learns of an accident at once, and once more if three
// days pass without anyone recording what was done. Never what the person wrote: that is read in Humetría.

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = ownerOf($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Hugo Saiz']);
    $this->teleworker = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    workIn($this->tenant);
});

it('emails RH at once when a person reports an accident, with who, where and when, never what they wrote', function (): void {
    $this->post(route('portal.notices.store', resolve(IssuePortalLink::class)->handle($this->employee->id)), [
        'kind' => 'work_risk', 'written_on' => '2026-02-28', 'occurred_on' => '2026-02-25', 'description' => 'Me lastimé la espalda.',
    ])->assertSessionHasNoErrors();
    $notice = App\Modules\Telework\Models\WorkerNotice::query()->sole();

    Notification::assertSentOnDemand(AccidentReported::class, function (AccidentReported $notification, array $channels, AnonymousNotifiable $notifiable) use ($notice): bool {
        $mail = $notification->toMail($notifiable);
        $text = json_encode($mail->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $notifiable->routes['mail'] === $this->owner->email
            && $mail->subject === 'Hugo Saiz avisó un accidente de trabajo'
            && str_contains($text, 'Hugo Saiz · Planta Querétaro · ocurrió el 25 feb')
            && str_contains($text, 'Si pasan 3 días sin respuesta, te lo volvemos a recordar.')
            && $mail->actionText === 'Revisar aviso'
            && $mail->actionUrl === route('tenant.telework.notices.show', ['acme', $notice->id])
            && ! str_contains($text, 'espalda');
    });
});

it('emails nobody for the other notices', function (): void {
    $this->post(route('portal.notices.store', resolve(IssuePortalLink::class)->handle($this->employee->id)), [
        'kind' => 'condition_change', 'written_on' => '2026-02-28', 'occurred_on' => '2026-02-25', 'description' => 'Se fue la luz.',
    ])->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

describe('the reminder', function (): void {
    it('reminds RH once of an accident nobody answered in three days', function (): void {
        Log::spy();
        $late = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'submitted_at' => '2026-02-26 11:00:00']);
        // Within three days, answered, or another kind: none is reminded.
        WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'submitted_at' => '2026-02-26 13:00:00']);
        WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'submitted_at' => '2026-02-20 10:00:00', 'reviewed_by' => $this->owner->id, 'reviewed_at' => now(), 'review_actions' => ['leave']]);
        WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'submitted_at' => '2026-02-20 10:00:00', 'kind' => 'condition_change']);

        expect(resolve(RemindUnansweredAccidents::class)->handle())->toBe(1)
            ->and(resolve(RemindUnansweredAccidents::class)->handle())->toBe(0);

        Notification::assertSentOnDemandTimes(AccidentReported::class, 1);
        Notification::assertSentOnDemand(AccidentReported::class, function (AccidentReported $notification, array $channels, AnonymousNotifiable $notifiable) use ($late): bool {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === $this->owner->email
                && $mail->subject === 'Sigue sin respuesta el aviso de Hugo Saiz'
                && $mail->actionUrl === route('tenant.telework.notices.show', ['acme', $late->id]);
        });
        expect($late->refresh()->reminded_at?->toIso8601String())->toBe('2026-03-01T12:00:00+00:00');
        Log::shouldHaveReceived('info')->with('telework.accident_reminders_sent', ['sent' => 1])->once();
    });

    it('reminds nobody in a blocked company, and runs from the command', function (): void {
        WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'submitted_at' => '2026-02-20 10:00:00']);
        $this->tenant->update(suspension());

        $this->artisan('telework:remind-accidents')->assertSuccessful();

        Notification::assertNothingSent();
    });
});
