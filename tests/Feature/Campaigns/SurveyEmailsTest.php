<?php

declare(strict_types=1);

use App\Modules\Campaigns\Jobs\SendSurveyInvitations;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Bus\PendingBatch;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

mutates(SurveyInvitation::class, SendSurveyInvitations::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo']);
    $this->owner = ownerOf($this->tenant);
    $this->owner->update(['email' => 'olga@autopartes.mx']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
});

/** Runs the jobs the last request queued, as the worker would. */
function sendQueuedLinks(): void
{
    Bus::assertBatched(function (PendingBatch $batch): bool {
        $batch->jobs->each(fn (SendSurveyInvitations $job) => $job->handle());

        return true;
    });
}

/** Launches an evaluation of `$people` people closing on 2026-11-20, with its links sent. */
function sentEvaluation(Tenant $tenant, WorkCenter $plant, App\Models\User $hr, int $people): Campaign
{
    staffOf($tenant, $plant, $people)->first()?->update(['name' => 'Aaron Pérez']);
    test()->actingAs($hr)->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
    Bus::fake();
    test()->actingAs($hr)->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => '2026-11-20'])->assertSessionHasNoErrors();
    sendQueuedLinks();

    return $campaign;
}

/** The email the person named `$name` got last. */
function emailTo(string $name): MailMessage
{
    $email = Employee::query()->withoutGlobalScopes()->where('name', $name)->sole()->email;
    $mails = [];
    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$mails): bool {
        if ($notifiable->routes['mail'] === $email) {
            $mails[] = $notification->toMail($notifiable);
        }

        return true;
    });

    return end($mails) ?: throw new RuntimeException("No email to {$name}.");
}

it('invites a person to the evaluation of their work center, as the survey speaks', function (): void {
    sentEvaluation($this->tenant, $this->plant, $this->hr, 20);

    $mail = emailTo('Aaron Pérez');
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Su opinión sobre su trabajo · evaluación confidencial')
        ->and($mail->actionText)->toBe('Contestar la evaluación')
        ->and($html)
        ->toContain('Unos 15 minutos, desde su celular. Sus respuestas sobre su trabajo son anónimas.')
        ->toMatch('#<h1[^>]*>Le invitamos a contestar la evaluación de su centro de trabajo</h1>#')
        ->toContain('Hola, Aaron Pérez:')
        ->toContain('Autopartes Demo aplica la evaluación de factores de riesgo psicosocial que pide la NOM-035-STPS-2018.')
        ->toContain('Nadie de su empresa puede ver lo que contesta sobre su trabajo. Recursos Humanos solo recibe resultados de grupos de 5 o más personas.')
        // Guía I does carry the name (C-07): the email says so.
        ->toContain('La parte sobre acontecimientos traumáticos sí lleva su nombre')
        ->toContain('Unos 15 minutos')
        ->toContain('Su celular o computadora')
        ->toContain('20 de noviembre')
        ->toContain('El enlace es personal: no lo reenvíe.')
        ->toContain('Si el botón no funciona, copie este enlace en su navegador:')
        ->toContain('Recibe este correo porque Autopartes Demo lo registró para la evaluación NOM-035. ¿Dudas? Escriba a <a href="mailto:olga@autopartes.mx"')
        ->toContain('hecho con <strong')
        // The company's email: its name (no logo yet) and "Confidencial", not Humetría's legal line.
        ->toContain('Autopartes Demo</span>')
        ->toContain('Confidencial')
        ->not->toContain('Aviso de privacidad');
});

it('says how long the guides of the evaluation take together', function (int $people, string $time): void {
    sentEvaluation($this->tenant, $this->plant, $this->hr, $people);

    expect((string) emailTo('Aaron Pérez')->render())->toContain($time);
})->with([
    'Guías I and II' => [50, 'Unos 15 minutos'],
    'Guías I and III' => [51, 'Unos 20 minutos'],
]);

it('takes the minimum group from the results setting', function (): void {
    config(['results.min_group' => 8]);
    sentEvaluation($this->tenant, $this->plant, $this->hr, 20);

    expect((string) emailTo('Aaron Pérez')->render())->toContain('grupos de 8 o más personas');
});

it('shows the company logo instead of its name when it has one', function (): void {
    $this->tenant->update(['logo_path' => 'logos/x.png', 'logo_width' => 100, 'logo_height' => 40]);
    sentEvaluation($this->tenant, $this->plant, $this->hr, 20);

    expect((string) emailTo('Aaron Pérez')->render())
        ->toMatch('#<img src="[^"]+/logos/x\.png" width="100" height="40" alt="Autopartes Demo"#')
        ->not->toContain('Autopartes Demo</span>');
});

it('leaves out whom to write to when the company has no owner yet', function (): void {
    $this->owner->delete();
    sentEvaluation($this->tenant, $this->plant, $this->hr, 20);

    expect((string) emailTo('Aaron Pérez')->render())
        ->toContain('lo registró para la evaluación NOM-035.')
        ->not->toContain('¿Dudas?');
});

it('reminds who already got a link, with the days left counting today', function (string $today, string $title, string $subject): void {
    $campaign = sentEvaluation($this->tenant, $this->plant, $this->hr, 20);
    Carbon::setTestNow($today);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.resend', [$this->tenant, $campaign->id]))->assertSessionHasNoErrors();
    sendQueuedLinks();

    $mail = emailTo('Aaron Pérez');
    $html = (string) $mail->render();

    expect($mail->subject)->toBe($subject)
        ->and($mail->actionText)->toBe('Continuar la evaluación')
        ->and($html)
        ->toMatch("#<h1[^>]*>{$title}</h1>#")
        ->toContain('Hola, Aaron Pérez:')
        ->toContain('Aún no recibimos su evaluación. Su opinión cuenta igual que la de todas las personas de Oficinas Monterrey, y sigue siendo confidencial.')
        ->toContain('NOV')
        ->toContain('Último día: viernes 20 de noviembre')
        ->toContain('Si ya envió una de las guías, no tiene que contestarla otra vez.')
        ->toContain('Le escribimos porque su evaluación sigue pendiente. Si ya la contestó, ignore este mensaje.');
})->with([
    'four days' => ['2026-11-17 17:00:00', 'Le quedan 4 días para contestar', 'Le quedan 4 días para contestar su evaluación'],
    'the day before' => ['2026-11-19 17:00:00', 'Le quedan 2 días para contestar', 'Le quedan 2 días para contestar su evaluación'],
    'the last day' => ['2026-11-20 17:00:00', 'Hoy es su último día para contestar', 'Hoy es su último día para contestar su evaluación'],
]);

it('asks the people of a traumatic event for Guía I, saying it carries their name', function (bool $resent): void {
    $people = staffOf($this->tenant, $this->plant, 3);
    $people->first()?->update(['name' => 'Aitor Ruiz']);
    Bus::fake();
    $this->actingAs($this->hr)->post(route('tenant.campaigns.trauma.store', $this->tenant), [
        'work_center_id' => $this->plant->id,
        'employee_ids' => $people->pluck('id')->all(),
        'closes_on' => '2026-11-09',
    ])->assertSessionHasNoErrors();
    sendQueuedLinks();

    if ($resent) {
        Bus::fake();
        $this->actingAs($this->hr)->post(route('tenant.campaigns.resend', [$this->tenant, Campaign::query()->withoutGlobalScopes()->sole()->id]));
        sendQueuedLinks();
    }

    $mail = emailTo('Aitor Ruiz');
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Una evaluación breve después de lo ocurrido')
        ->and($mail->actionText)->toBe('Contestar la Guía I')
        ->and($html)
        ->toContain('Preguntas de sí o no. La empresa puede ofrecerle atención si la necesita.')
        ->toContain('Hola, Aitor Ruiz:')
        ->toContain('Por lo que sucedió en Oficinas Monterrey, la NOM-035 pide que conteste la Guía I. Empieza con 6 preguntas de sí o no y toma unos 5 minutos.')
        ->toContain('sí lleva su nombre</strong>, para que la empresa pueda ofrecerle atención si la necesita. Solo la ven las personas autorizadas para darle seguimiento.')
        ->toContain('Puede contestar hasta el 9 de noviembre.')
        ->toContain('Si necesita hablar con alguien antes, puede escribir a <a href="mailto:olga@autopartes.mx"')
        ->not->toContain('Nadie de su empresa puede ver');
})->with(['first' => false, 'resent' => true]);

it('writes the plain-text email too, with the link spelled out', function (): void {
    sentEvaluation($this->tenant, $this->plant, $this->hr, 20);

    $mail = emailTo('Aaron Pérez');
    $text = (string) app(Illuminate\Mail\Markdown::class)->renderText($mail->markdown, $mail->data());

    expect($text)->toContain('Le invitamos a contestar la evaluación de su centro de trabajo')
        ->toContain('Contestar la evaluación: '.$mail->actionUrl)
        ->toContain('hecho con humetría')
        ->not->toContain('<');
});
