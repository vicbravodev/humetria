<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Enums\Destination;
use App\Modules\Results\Http\Controllers\PortalSupportController;
use App\Modules\Results\Jobs\SendSupportNotice;
use App\Modules\Results\Notifications\SupportWaiting;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalSupportController::class, SendSupportNotice::class, SupportWaiting::class, Destination::class);

// Rediseño v2 (board V4-N035-Canalizacion, «Aviso en su portal»): a person channeled through their portal gets an email that
// says only that a message waits, and reads it with their link, as «usted»: never the note nor anything of the Guía I.

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-04 18:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    $this->campaign = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id]);
    TraumaScreening::factory()->create(['campaign_id' => $this->campaign->id, 'employee_id' => $this->ana->id, 'requires_clinical_assessment' => true]);
    workIn($this->tenant);
    $this->token = resolve(PortalAccess::class)->token($this->ana->id);
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
            && $mail->actionUrl === route('portal.support.show', $this->token)
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

    $response = $this->get(route('portal.support.show', $this->token))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('results/portal-support')
        ->where('company', 'Acme')
        ->where('name', 'Ana Ruiz')
        ->where('on', '4 nov 2026')
        ->where('message', 'Por lo que contestó en la encuesta, la empresa quiere ofrecerle apoyo. Es confidencial y no afecta su trabajo. Le proponemos acudir a la institución que prefiera. Si tiene dudas, puede hablar con Recursos Humanos.'));
    expect((string) $response->getContent())->not->toContain('Nota privada');
});

it('has no message for a person told in person, nor for a link that is not valid', function (): void {
    channelAna('in_person');

    $this->get(route('portal.support.show', $this->token))->assertNotFound();
    $this->get(route('portal.support.show', 'inventado'))->assertNotFound();
});

it('lives next to the rest of the personal page, throttled like it', function (): void {
    expect(route('portal.support.show', 'abc', absolute: false))->toBe('/portal/abc/support')
        ->and(Route::getRoutes()->getByName('portal.support.show')?->gatherMiddleware())->toContain('throttle:portal-views');
});

it('speaks to HR as «tú» in the script and to the person as «usted» in the notice', function (): void {
    expect(Destination::Imss->script())->toContain('acudir a tu Unidad de Medicina Familiar del IMSS. ¿Te parece?')
        ->and(Destination::Imss->notice())->toContain('acudir a su Unidad de Medicina Familiar del IMSS.')
        ->and(Destination::Other->script())->toContain('que prefieras.')
        ->and(Destination::Declined->notice())->toContain('a atención médica o psicológica')
        ->and(Destination::CompanyMedical->label())->toBe('Servicio médico de la empresa');
});
