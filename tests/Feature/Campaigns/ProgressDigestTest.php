<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\SendProgressDigests;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Notifications\ProgressDigest;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(SendProgressDigests::class, ProgressDigest::class);

beforeEach(function (): void {
    // Monday, 9:00 in Mexico City.
    $this->travelTo('2026-11-02 15:00 UTC');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo', 'slug' => 'autopartes']);
    ownerOf($this->tenant)->update(['email' => 'olga@autopartes.mx']);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
});

/** `$answered` of `$people` people of the area `$name` answered every guide of the campaign. */
function peopleIn(Campaign $campaign, string $name, int $answered, int $people, bool $reachable = true): void
{
    $area = Area::query()->withoutGlobalScopes()->firstOrCreate(['tenant_id' => $campaign->tenant_id, 'name' => $name]);

    foreach (range(1, $people) as $index) {
        $employee = Employee::factory()->create([
            'tenant_id' => $campaign->tenant_id, 'work_center_id' => $campaign->work_center_id, 'area_id' => $area->id,
            ...($reachable ? [] : ['email' => null, 'mobile' => '5512345678']),
        ]);
        $participant = Participant::factory()->create(['campaign_id' => $campaign->id, 'employee_id' => $employee->id]);

        foreach ($campaign->instruments as $instrument) {
            ParticipantInstrument::factory()->create([
                'participant_id' => $participant->id,
                'instrument' => $instrument,
                ...($index <= $answered ? ['status' => InstrumentStatus::Completed, 'completed_at' => now()] : []),
            ]);
        }
    }
}

function digestTo(string $email): MailMessage
{
    $mail = null;
    Notification::assertSentOnDemand(ProgressDigest::class, function (ProgressDigest $digest, array $channels, AnonymousNotifiable $notifiable) use ($email, &$mail): bool {
        if ($notifiable->routes['mail'] === $email) {
            $mail = $digest->toMail($notifiable);
        }

        return true;
    });

    return $mail ?? throw new RuntimeException("No digest to {$email}.");
}

function evaluationOf(Tenant $tenant, WorkCenter $plant, string $closesOn = '2026-11-12'): Campaign
{
    return Campaign::factory()->active($closesOn)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
}

it('tells the owner each Monday how far each open evaluation got, area by area', function (): void {
    $campaign = evaluationOf($this->tenant, $this->plant);
    peopleIn($campaign, 'Administración', 6, 14);
    peopleIn($campaign, 'Dirección', 4, 8);
    peopleIn($campaign, 'Ventas', 8, 38);
    Log::spy();

    expect(app(SendProgressDigests::class)->handle())->toBe(1);

    $mail = digestTo('olga@autopartes.mx');
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Oficinas Monterrey va en 18 de 60 · faltan 11 días')
        ->and($mail->actionText)->toBe('Recordar a 42 personas')
        ->and($mail->actionUrl)->toBe(route('tenant.campaigns.show', ['autopartes', $campaign->id]))
        ->and($html)
        ->toContain('Ventas es el área que más falta. Un recordatorio suele subir la participación.')
        ->toMatch('#<h1[^>]*>Oficinas Monterrey va en 18 de 60</h1>#')
        ->toContain('Quedan 11 días. Ventas es el área que más falta: 30 personas sin contestar.')
        ->toContain(asset('mail/arc/30.png'))
        ->toMatch('#Administración</td>\s*<td[^>]*>\s*<strong[^>]*>6</strong> <span[^>]*>de 14</span>#')
        ->toMatch('#Dirección</td>\s*<td[^>]*>\s*<strong[^>]*>4</strong> <span[^>]*>de 8</span>#')
        ->toMatch('#Ventas</td>\s*<td[^>]*>\s*<strong[^>]*>8</strong> <span[^>]*>de 38</span>#')
        ->toContain('Ver avance')
        ->toContain('Te mandamos este resumen cada lunes mientras haya una evaluación en curso.')
        ->toContain('alt="Humetría"');
    Log::shouldHaveReceived('info')->with('campaign.digest_sent', ['campaign_id' => $campaign->id]);
});

it('counts only who can get a reminder: people with a guide pending and an email', function (): void {
    $campaign = evaluationOf($this->tenant, $this->plant);
    peopleIn($campaign, 'Ventas', 2, 5);
    peopleIn($campaign, 'Almacén', 0, 3, reachable: false);

    app(SendProgressDigests::class)->handle();

    expect(digestTo('olga@autopartes.mx')->actionText)->toBe('Recordar a 3 personas');
});

it('shows the five areas that lack the most, in alphabetical order', function (): void {
    $campaign = evaluationOf($this->tenant, $this->plant);

    foreach (['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6] as $name => $pending) {
        peopleIn($campaign, "Área {$name}", 0, $pending);
    }

    app(SendProgressDigests::class)->handle();

    $html = (string) digestTo('olga@autopartes.mx')->render();
    preg_match_all('#(Área [A-F])</td>#', $html, $areas);

    expect($areas[1])->toBe(['Área B', 'Área C', 'Área D', 'Área E', 'Área F']);
});

it('only offers to look at the progress once everyone answered', function (): void {
    $campaign = evaluationOf($this->tenant, $this->plant);
    peopleIn($campaign, 'Ventas', 5, 5);

    app(SendProgressDigests::class)->handle();

    $mail = digestTo('olga@autopartes.mx');

    expect($mail->actionText)->toBe('Ver avance')
        ->and((string) $mail->render())->toContain('Quedan 11 días. Ya contestaron todas las personas.')
        ->not->toContain('Recordar a');
});

it('says on the last day that it is the last', function (): void {
    peopleIn(evaluationOf($this->tenant, $this->plant, '2026-11-02'), 'Ventas', 1, 5);

    app(SendProgressDigests::class)->handle();

    $mail = digestTo('olga@autopartes.mx');

    expect($mail->subject)->toBe('Oficinas Monterrey va en 1 de 5 · hoy es el último día')
        ->and((string) $mail->render())->toContain('Hoy es el último día. Ventas es el área que más falta: 4 personas sin contestar.');
});

it('measures a sampled Guía III against the sample', function (): void {
    $campaign = Campaign::factory()->active('2026-11-12')->create([
        'tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id,
        'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'workers' => 100, 'population' => App\Modules\Campaigns\Enums\Population::Sample, 'sample_size' => 80, 'quotas' => ['female' => 80],
    ]);
    peopleIn($campaign, 'Ventas', 20, 80);

    app(SendProgressDigests::class)->handle();

    expect(digestTo('olga@autopartes.mx')->subject)->toBe('Oficinas Monterrey va en 20 de 80 · faltan 11 días');
});

it('leaves out drafts, closed evaluations, blocked companies and companies with no owner', function (): void {
    peopleIn(Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]), 'Ventas', 0, 5);
    peopleIn(Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]), 'Ventas', 0, 5);
    $blocked = Tenant::factory()->create(['deactivated_at' => now()]);
    ownerOf($blocked);
    peopleIn(evaluationOf($blocked, WorkCenter::factory()->for($blocked)->create()), 'Ventas', 0, 5);
    $ownerless = Tenant::factory()->create();
    peopleIn(evaluationOf($ownerless, WorkCenter::factory()->for($ownerless)->create()), 'Ventas', 0, 5);

    expect(app(SendProgressDigests::class)->handle())->toBe(0);

    Notification::assertNothingSent();
});

it('sends each company the digest of its own evaluations', function (): void {
    peopleIn(evaluationOf($this->tenant, $this->plant), 'Ventas', 0, 5);
    $other = Tenant::factory()->create();
    ownerOf($other)->update(['email' => 'ana@otra.mx']);
    $otherPlant = WorkCenter::factory()->for($other)->create(['name' => 'Planta Sur']);
    peopleIn(evaluationOf($other, $otherPlant), 'Ventas', 0, 5);

    expect(app(SendProgressDigests::class)->handle())->toBe(2)
        ->and(digestTo('ana@otra.mx')->subject)->toStartWith('Planta Sur va en 0 de 5')
        ->and(digestTo('olga@autopartes.mx')->subject)->toStartWith('Oficinas Monterrey va en 0 de 5');
});

it('runs on Mondays at 9:00 in Mexico City', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->command, 'campaigns:send-digests'));

    expect($event?->expression)->toBe('0 9 * * 1')
        ->and($event?->timezone)->toBe('America/Mexico_City');
});
