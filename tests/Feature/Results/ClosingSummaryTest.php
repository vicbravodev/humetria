<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Events\CampaignClosed;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Listeners\SendClosingSummary;
use App\Modules\Results\Notifications\ClosingSummary;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

mutates(SendClosingSummary::class, ClosingSummary::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo', 'slug' => 'autopartes']);
    ownerOf($this->tenant)->update(['email' => 'olga@autopartes.mx']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->campaign = Campaign::factory()->active('2026-11-25')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
});

/** `$count` people who answered Guía II with `$value` everywhere, and `$pending` who did not. */
function guideTwoBy(Campaign $campaign, Area $area, int $count, string $value, int $pending = 0): void
{
    foreach (range(1, $count + $pending) as $index) {
        ParticipantInstrument::factory()->create([
            'participant_id' => Participant::factory()->create(['campaign_id' => $campaign->id])->id,
            'instrument' => 'nom035.guide-ii',
            ...($index <= $count ? ['status' => InstrumentStatus::Completed, 'completed_at' => now()] : []),
        ]);

        if ($index <= $count) {
            AnonymousResponse::factory()->create(['campaign_id' => $campaign->id, 'area_id' => $area->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', $value)]);
        }
    }
}

function closingSummaryTo(string $email): MailMessage
{
    $mail = null;
    Notification::assertSentOnDemand(ClosingSummary::class, function (ClosingSummary $summary, array $channels, AnonymousNotifiable $notifiable) use ($email, &$mail): bool {
        if ($notifiable->routes['mail'] === $email) {
            $mail = $summary->toMail($notifiable);
        }

        return true;
    });

    return $mail ?? throw new RuntimeException("No closing summary to {$email}.");
}

it('tells the owner how the evaluation closed: participation, risk levels, the domain that weighs most and what follows', function (): void {
    guideTwoBy($this->campaign, $this->area, 6, 'never');
    guideTwoBy($this->campaign, $this->area, 5, 'always', pending: 1);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $this->campaign->id, 'requires_clinical_assessment' => true]);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.close', [$this->tenant, $this->campaign->id]))->assertSessionHasNoErrors();

    $mail = closingSummaryTo('olga@autopartes.mx');
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Planta Querétaro cerró: 5 de 11 en riesgo alto o muy alto')
        ->and($mail->actionText)->toBe('Ver resultados')
        ->and($mail->actionUrl)->toBe(route('tenant.campaigns.results', ['autopartes', $this->campaign->id]))
        ->and($html)
        ->toContain('Participación de 92 %. Relaciones en el trabajo es el dominio que más pesa.')
        ->toMatch('#<h1[^>]*>Planta Querétaro cerró con 11 de 12 respuestas</h1>#')
        ->toContain(asset('mail/arc/closed-92.png'))
        ->toMatch('#<strong[^>]*>5 de 11 cuestionarios</strong> quedan en riesgo alto o muy alto\.#')
        // A count of questionnaires, never an average (C-14): here all 11 sit at Alto or Muy alto in that domain.
        ->toMatch('#El dominio con más cuestionarios en riesgo alto o muy alto es <strong[^>]*>Relaciones en el trabajo</strong>, con 100 %\.#')
        ->toMatch('#Nulo <strong[^>]*>0</strong>#')
        ->toMatch('#Medio <strong[^>]*>6</strong>#')
        ->toMatch('#Muy alto <strong[^>]*>5</strong>#')
        ->toContain('Lo que sigue')
        ->toContain('2 personas requieren valoración clínica (Guía I)')
        ->toContain('Completa el informe 7.7 con las conclusiones y las acciones de intervención.')
        ->toContain('Recibes este aviso porque eres el dueño de Autopartes Demo.')
        ->toContain('alt="Humetría"');
});

it('shows no risk level below the minimum group, only the participation', function (): void {
    guideTwoBy($this->campaign, $this->area, 4, 'always');

    $this->actingAs($this->hr)->post(route('tenant.campaigns.close', [$this->tenant, $this->campaign->id]));

    $mail = closingSummaryTo('olga@autopartes.mx');
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Planta Querétaro cerró con 4 de 4 respuestas')
        ->and($html)
        ->toContain('Con menos de 5 cuestionarios no se muestran niveles de riesgo, para cuidar el anonimato.')
        ->not->toContain('riesgo alto o muy alto')
        ->not->toContain('Muy alto')
        ->not->toContain('valoración clínica');
});

it('closes a Guía I campaign with its participation and who needs a clinical assessment', function (): void {
    $trauma = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    ParticipantInstrument::factory()->count(3)->create(['participant_id' => fn () => Participant::factory()->create(['campaign_id' => $trauma->id])->id, 'instrument' => 'nom035.guide-i', 'status' => InstrumentStatus::Completed, 'completed_at' => now()]);
    TraumaScreening::factory()->create(['campaign_id' => $trauma->id, 'requires_clinical_assessment' => true]);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.close', [$this->tenant, $trauma->id]));

    $mail = closingSummaryTo('olga@autopartes.mx');

    expect($mail->subject)->toBe('Planta Querétaro cerró con 3 de 3 respuestas')
        ->and((string) $mail->render())
        ->toContain('1 persona requiere valoración clínica (Guía I)')
        ->not->toContain('informe 7.7')
        ->not->toContain('niveles de riesgo');
});

it('sends it once the closing is committed, also when the last day closes it', function (): void {
    Event::fake([CampaignClosed::class]);
    $this->travelTo('2026-11-26 18:00 UTC');

    $this->artisan('campaigns:close-expired')->assertSuccessful();

    Event::assertDispatched(CampaignClosed::class, fn (CampaignClosed $event): bool => $event->tenantId === $this->tenant->id && $event->campaignId === $this->campaign->id);
    expect(new CampaignClosed('t', 'c'))->toBeInstanceOf(Illuminate\Contracts\Events\ShouldDispatchAfterCommit::class);
});

it('sends nothing to a blocked company', function (): void {
    $this->campaign->update(['status' => 'closed', 'closed_at' => now()]);
    $this->tenant->update(['deactivated_at' => now()]);

    app(SendClosingSummary::class)->handle(new CampaignClosed($this->tenant->id, $this->campaign->id));

    Notification::assertNothingSent();
});

it('sends nothing to a company with no owner', function (): void {
    $tenant = Tenant::factory()->create();
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => WorkCenter::factory()->for($tenant)->create()->id]);

    app(SendClosingSummary::class)->handle(new CampaignClosed($tenant->id, $campaign->id));

    Notification::assertNothingSent();
});
