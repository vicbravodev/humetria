<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Enums\Destination;
use App\Modules\Results\Http\Controllers\PortalSupportController;
use App\Modules\Results\Jobs\SendSupportNotice;
use App\Modules\Results\Notifications\SupportWaiting;
use App\Modules\Results\Support\SupportWorkerSection;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalSupportController::class, SendSupportNotice::class, SupportWaiting::class, Destination::class, SupportWorkerSection::class);

// Rediseño v2 (board V4-N035-Canalizacion, «Aviso en su portal»): a person channeled through their portal gets an email that
// says only that a message waits, and reads it in their session, as «usted»: never the note nor anything of the Guía I.

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-04 18:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    $this->campaign = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id]);
    TraumaScreening::factory()->create(['campaign_id' => $this->campaign->id, 'employee_id' => $this->ana->id, 'requires_clinical_assessment' => true]);
    workIn($this->tenant);
    $this->slug = asPerson($this->ana->id);
});

function channelAna(string $told, string $destination = 'imss'): void
{
    test()->actingAs(memberOf(test()->tenant, TenantRole::Hr))
        ->put(route('tenant.campaigns.screenings.update', [test()->tenant, test()->campaign->id, test()->ana->id]), ['destination' => $destination, 'told' => $told, 'referred_on' => '2026-11-04', 'note' => 'Nota privada de RH'])
        ->assertSessionHasNoErrors();
}

it('emails the person told through their portal that a message waits, without what it is about', function (): void {
    channelAna('portal');

    Notification::assertSentOnDemand(SupportWaiting::class, function (SupportWaiting $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $notification->toMail($notifiable);
        $text = implode(' ', [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);

        return $notifiable->routes['mail'] === 'ana@acme.test'
            && $mail->subject === 'Tiene un mensaje de Acme'
            && $mail->actionUrl === route('portal.support.show', $this->tenant->slug)
            && ! str_contains((string) $mail->render(), 'el anterior deja de funcionar')
            && ! str_contains($text, 'IMSS') && ! str_contains($text, 'Nota privada') && ! str_contains($text, 'encuesta');
    });
});

it('sends nothing to whoever was told in person, nor to a person without email', function (): void {
    channelAna('in_person');
    $this->ana->update(['email' => null]);
    channelAna('portal');

    Notification::assertNothingSent();
});

it('shows the person their message as «usted», with where it proposes they go and nothing else', function (): void {
    channelAna('portal', 'other');

    $response = $this->get(route('portal.support.show', $this->slug))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('results/portal-support')
        ->where('company', 'Acme')
        ->where('name', 'Ana Ruiz')
        ->where('on', '4 nov 2026')
        ->where('message', 'Por lo que contestó en la encuesta, la empresa quiere ofrecerle apoyo. Es confidencial y no afecta su trabajo. Le proponemos acudir a la institución que prefiera. Si tiene dudas, puede hablar con Recursos Humanos.'));
    expect((string) $response->getContent())->not->toContain('Nota privada');
});

it('has no message for a person told in person', function (): void {
    channelAna('in_person');

    $this->get(route('portal.support.show', $this->slug))->assertNotFound();
});

it('sends a visitor to enter, ends the page for a person who left, and opens nothing of another company with this session', function (): void {
    channelAna('portal');
    $other = Tenant::factory()->create();

    $this->get(route('portal.support.show', $other->slug))->assertRedirect(route('worker.home', $other->slug));

    $this->ana->update(['deactivated_at' => now()]);
    $this->get(route('portal.support.show', $this->slug))->assertRedirect(route('worker.home', $this->slug));

    session()->flush();
    $this->get(route('portal.support.show', $this->slug))->assertRedirect(route('worker.home', $this->slug));
});

it('answers 404 when the company of the address no longer has NOM-035', function (): void {
    channelAna('portal');
    $this->get(route('portal.support.show', $this->slug))->assertOk();

    resolve(App\Modules\Tenancy\Contracts\CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));

    $this->get(route('portal.support.show', $this->slug))->assertNotFound();
});

it('never shows a person the message of another', function (): void {
    channelAna('portal');
    $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Paz']);

    $this->get(route('portal.support.show', asPerson($beto->id)))->assertNotFound();
});

it('offers «Apoyo» in the person\'s start only to whoever was told through the portal, at their page with no credential', function (): void {
    $section = fn () => resolve(SupportWorkerSection::class)->for(new App\Modules\WorkerAccess\Data\WorkerIdentity($this->tenant->id, $this->ana->id, 'Acme', 'Ana Ruiz'));

    channelAna('in_person');
    expect($section())->toBeNull();

    channelAna('portal');
    expect($section()?->key)->toBe('support')
        ->and($section()?->label)->toBe('Apoyo')
        ->and($section()?->href)->toBe(route('portal.support.show', 'acme'))
        ->and($section()?->pending)->toBe(1);

    resolve(App\Modules\Tenancy\Contracts\CompanyModules::class)->syncPlan($this->tenant->id, ['seating'], CarbonImmutable::now(config('app.display_timezone')));
    expect($section())->toBeNull();
});

it('lives in the person\'s pages, throttled like them', function (): void {
    expect(route('portal.support.show', 'acme', absolute: false))->toBe('/me/acme/support')
        ->and(Route::getRoutes()->getByName('portal.support.show')?->gatherMiddleware())->toContain('throttle:portal-views');
});

it('speaks to HR as «tú» in the script and to the person as «usted» in the notice', function (): void {
    expect(Destination::Imss->script())->toContain('acudir a tu Unidad de Medicina Familiar del IMSS. ¿Te parece?')
        ->and(Destination::Imss->notice())->toContain('acudir a su Unidad de Medicina Familiar del IMSS.')
        ->and(Destination::Other->script())->toContain('que prefieras.')
        ->and(Destination::Declined->notice())->toContain('a atención médica o psicológica')
        ->and(Destination::CompanyMedical->label())->toBe('Servicio médico de la empresa');
});
