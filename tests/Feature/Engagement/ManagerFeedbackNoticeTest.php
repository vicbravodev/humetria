<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Events\SurveyClosed;
use App\Modules\Engagement\Listeners\NotifyManagers;
use App\Modules\Engagement\Notifications\ManagerFeedbackReady;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

mutates(NotifyManagers::class, ManagerFeedbackReady::class);

beforeEach(function (): void {
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    workIn($this->tenant);
});

function noticeBoss(Tenant $tenant, string $name, ?string $email = 'jefe@example.com'): Employee
{
    return Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => $name, 'email' => $email]);
}

function closeAnnouncement(string $surveyId): void
{
    resolve(NotifyManagers::class)->handle(new SurveyClosed($surveyId, SurveyKind::Climate));
}

/** The addresses an announcement was sent to. */
function noticeRecipients(): array
{
    $sent = Notification::sentNotifications()[AnonymousNotifiable::class] ?? [];
    $addresses = [];
    foreach ($sent as $perNotifiable) {
        foreach ($perNotifiable[ManagerFeedbackReady::class] ?? [] as $entry) {
            $addresses[] = $entry['notifiable']->routes['mail'];
        }
    }
    sort($addresses);

    return $addresses;
}

it('tells, with the link of their portal, only the managers whose group is shown and who can be reached', function (): void {
    $ana = noticeBoss($this->tenant, 'Ana', 'ana@example.com');
    $beto = noticeBoss($this->tenant, 'Beto', null);
    $carla = noticeBoss($this->tenant, 'Carla', 'carla@example.com');
    $carla->update(['deactivated_at' => now()]);
    $survey = climateManagerSurvey($this->tenant, [$ana->id => [6, 5], $beto->id => [6, 5], $carla->id => [6, 5]]);

    closeAnnouncement($survey->id);

    expect(noticeRecipients())->toBe(['ana@example.com']);
    Notification::assertSentOnDemand(ManagerFeedbackReady::class, function (ManagerFeedbackReady $notification, array $channels, AnonymousNotifiable $notifiable) use ($ana): bool {
        $mail = $notification->toMail($notifiable);

        return $mail->actionUrl === route('portal.feedback.show', resolve(PortalAccess::class)->token($ana->id));
    });
});

it('does not tell a manager whose group is under the minimum or was hidden to protect another', function (): void {
    $ana = noticeBoss($this->tenant, 'Ana', 'ana@example.com');
    $eva = noticeBoss($this->tenant, 'Eva', 'eva@example.com');
    $dora = noticeBoss($this->tenant, 'Dora', 'dora@example.com');
    // Dora's four are under the minimum; so Ana's six go too, or the difference would give Dora's people away.
    $survey = climateManagerSurvey($this->tenant, [$ana->id => [6, 5], $eva->id => [9, 5], $dora->id => [4, 5]]);

    closeAnnouncement($survey->id);

    expect(noticeRecipients())->toBe(['eva@example.com']);
});

it('tells nobody while the survey is not closed', function (): void {
    $ana = noticeBoss($this->tenant, 'Ana');
    $survey = climateManagerSurvey($this->tenant, [$ana->id => [6, 5]]);
    $survey->update(['status' => 'active', 'closed_at' => null]);

    closeAnnouncement($survey->id);

    Notification::assertNothingSent();
});

it('writes to the manager in usted, with no figure, no name of anyone and no comment', function (): void {
    $company = new CompanySummary($this->tenant->id, 'Acme', $this->tenant->slug, false);
    $mail = (new ManagerFeedbackReady($company, 'Marta', 'Clima · noviembre 2026', 'https://humetria.test/portal/x/feedback'))->toMail(new AnonymousNotifiable);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Ya puede ver la retroalimentación de su equipo')
        ->and($mail->actionText)->toBe('Ver la retroalimentación')
        ->and($html)->toContain('Hola, Marta')
        ->toContain('Clima · noviembre 2026')
        ->toContain('Solo verá resultados de grupo y un resumen de los comentarios')
        ->not->toMatch('/\d+ (personas|respuestas|comentarios)/');
});

it('is wired to the closing of a survey', function (): void {
    $ana = noticeBoss($this->tenant, 'Ana', 'ana@example.com');
    $survey = climateManagerSurvey($this->tenant, [$ana->id => [6, 5]]);
    $survey->update(['status' => 'active', 'closed_at' => null]);

    resolve(App\Modules\Engagement\Actions\CloseSurvey::class)->handle($survey->id, 1);

    expect(noticeRecipients())->toBe(['ana@example.com']);
});
