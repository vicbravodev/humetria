<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\SurveyEmail;
use App\Modules\Campaigns\Notifications\ProgressDigest;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Complaints\Notifications\ComplaintReceived;
use App\Modules\Complaints\Notifications\ComplaintsDigest;
use App\Modules\Complaints\Notifications\DailyCapReached;
use App\Modules\Complaints\Notifications\HandlersChanged;
use App\Modules\Complaints\Notifications\NewsOnYourCase;
use App\Modules\Complaints\Notifications\YouWereDesignated;
use App\Modules\Disclosure\Notifications\DisclosureDigest;
use App\Modules\Disclosure\Notifications\PolicyToConfirm;
use App\Modules\Interventions\Notifications\ProgramDigest;
use App\Modules\Results\Notifications\ClosingSummary;
use App\Modules\Results\Notifications\ReferralFollowUp;
use App\Modules\Results\Notifications\SupportWaiting;
use App\Modules\Seating\Notifications\RisksToConfirm;
use App\Modules\Seating\Notifications\SeatingDigest;
use App\Modules\Telework\Data\PortalPendingData;
use App\Modules\Telework\Notifications\AccidentReported;
use App\Modules\Telework\Notifications\DocumentsToConfirm;
use App\Modules\Telework\Notifications\NoticeAnswered;
use App\Modules\Telework\Notifications\PortalInvitation;
use App\Modules\Telework\Notifications\PortalReminder;
use App\Modules\Telework\Notifications\TeleworkDigest;
use App\Modules\Telework\Notifications\TeleworkMoved;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Notifications\InvitationEmail;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/*
 * Every email Humetría sends, in one layout (decision A15, boards V3-Correos and V4-Correos-2): one button, the gray seal
 * «hecho con humetría», and the company's initials and name when it is the company's; Humetría's logo when it is not, as
 * the complaint receipts, which never name the company.
 */

function inventoryCompany(): CompanySummary
{
    return new CompanySummary('t', 'Autopartes Demo', 'autopartes', false);
}

/** @return array<string, array{Closure(): Notification, bool}> the notification, and whether it is the company's */
function inventory(): array
{
    $company = inventoryCompany();
    $url = 'https://humetria.test/x';

    return [
        'survey invitation' => [fn () => new SurveyInvitation(SurveyEmail::Invitation, 'tok', 'Ana', $company, null, 'Planta', CarbonImmutable::parse('2026-12-01'), 15), true],
        'climate invitation' => [fn () => new App\Modules\Engagement\Notifications\SurveyInvitation(false, 'tok', 'Ana', $company, null, CarbonImmutable::parse('2026-12-01'), App\Modules\Engagement\Enums\SurveyKind::Climate), true],
        'climate reminder' => [fn () => new App\Modules\Engagement\Notifications\SurveyInvitation(true, 'tok', 'Ana', $company, null, CarbonImmutable::parse('2026-12-01'), App\Modules\Engagement\Enums\SurveyKind::Climate), true],
        'climate sensitive mentions' => [fn () => new App\Modules\Engagement\Notifications\SensitiveMentions($company, 'Clima · noviembre 2026', $url), true],
        'climate manager feedback' => [fn () => new App\Modules\Engagement\Notifications\ManagerFeedbackReady($company, 'Marta', 'Clima · noviembre 2026', $url), true],
        'progress digest' => [fn () => new ProgressDigest($company, 'Planta', 3, 10, 5, [], ['name' => 'Ventas', 'pending' => 7], 7, $url), true],
        'next evaluation due' => [fn () => new App\Modules\Campaigns\Notifications\NextEvaluationDue($company, ['Planta Norte', 'Planta Sur'], '4 ene 2027', $url), true],
        'complaint received' => [fn () => new ComplaintReceived($url, 3, 30), false],
        'complaints digest' => [fn () => new ComplaintsDigest($company, 'Olga', 1, ['Tienes 1 caso nuevo.'], $url), true],
        'daily cap' => [fn () => new DailyCapReached($company, 20, $url), true],
        'handlers changed' => [fn () => new HandlersChanged($company, 'Rita', true, $url), true],
        'news on your case' => [fn () => new NewsOnYourCase($url), false],
        'you were designated' => [fn () => new YouWereDesignated($company, 'Rita', 'de todo', $url), true],
        'disclosure digest' => [fn () => new DisclosureDigest($company, ['1 centro sin política.'], $url, ['confirmed' => 1, 'total' => 3, 'pending' => 2], $url), true],
        'policy to confirm' => [fn () => new PolicyToConfirm($url, 'Ana', $company), true],
        'climate feedback published' => [fn () => new App\Modules\Engagement\Notifications\FeedbackPublished($url, 'Ana', $company), true],
        'pulse upcoming' => [fn () => new App\Modules\Engagement\Notifications\PulseUpcoming($company, CarbonImmutable::parse('2026-12-15'), 'Recorrido', 10, 2, $url), true],
        'program digest' => [fn () => new ProgramDigest($company, 'Olga', ['1 acción vencida.'], $url), true],
        'closing summary' => [fn () => new ClosingSummary($company, 'Planta', 8, 10, null, null, true, 0, $url), true],
        'referral follow-up' => [fn () => new ReferralFollowUp($company, $url), true],
        'support waiting' => [fn () => new SupportWaiting($url, 'Ana', $company), true],
        'risks to confirm' => [fn () => new RisksToConfirm($url, 'Ana', $company), true],
        'standing questions' => [fn () => new App\Modules\Seating\Notifications\StandingQuestions($company, 'Aitor', $url), true],
        'seating digest' => [fn () => new SeatingDigest($company, 'Olga', ['1 puesto sin evaluar.'], $url), true],
        'accident reported' => [fn () => new AccidentReported($company, 'Hugo', 'Planta', '25 feb', $url), true],
        'documents to confirm' => [fn () => new DocumentsToConfirm($url, 'Cora', $company, ['Política · versión 1']), true],
        'notice answered' => [fn () => new NoticeAnswered($url, 'Ana', $company), true],
        'portal invitation' => [fn () => new PortalInvitation('tok', 'Ana', $company), true],
        'portal link' => [fn () => new App\Modules\Telework\Notifications\PortalLinkEmail('tok', 'Ana', $company), true],
        'portal reminder' => [fn () => new PortalReminder($url, 'Ana', $company, new PortalPendingData([], [])), true],
        'telework digest' => [fn () => new TeleworkDigest($company, 'Olga', ['1 lista por revisar.'], $url), true],
        'telework moved' => [fn () => new TeleworkMoved($company, 'Ana', 'A', 'B', $url), true],
        'attendance enrollment' => [fn () => new App\Modules\Attendance\Notifications\EnrollmentInvitation($url, 'Ana', $company), true],
        'attendance device linked' => [fn () => new App\Modules\Attendance\Notifications\DeviceLinked('iPhone', CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC'), 'Ana', $company), true],
        'leave request to decide' => [fn () => new App\Modules\Leave\Notifications\RequestToDecide($url, $company, 'Beto', 'Ana', new App\Modules\Leave\Support\RequestSummary(true, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-20'), 9, true)), true],
        'leave request escalated' => [fn () => new App\Modules\Leave\Notifications\RequestEscalated($url, $company, 'Ana', App\Modules\Leave\Enums\EscalationReason::Timeout, new App\Modules\Leave\Support\RequestSummary(false, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-20'), 9, null)), true],
        'leave request decided' => [fn () => new App\Modules\Leave\Notifications\RequestDecided($url, $company, 'Ana', new App\Modules\Leave\Support\RequestSummary(true, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-20'), 9, true), false, 'Cierre de mes'), true],
        'vacations portal link' => [fn () => new App\Modules\Leave\Notifications\VacationsPortalLink($url, $company, 'Ana'), true],
        'invitation' => [fn () => new InvitationEmail('Autopartes Demo', 'ana@x.mx', 'tok', CarbonImmutable::now()->addDays(7), TenantRole::Hr, null, 1, 3), false],
    ];
}

it('lays out every email the same way: one button and the seal', function (Closure $make, bool $companys): void {
    $html = (string) $make()->toMail(new AnonymousNotifiable)->render();

    expect(substr_count($html, 'class="button button-'))->toBe(1)
        ->and($html)->toContain('class="button button-primary"')
        ->toMatch('#<p class="seal"[^>]*>hecho con <strong[^>]*>humetría</strong></p>#');

    if ($companys) {
        expect($html)->toContain('Autopartes Demo</span>')->not->toContain('alt="Humetría"');
    } else {
        expect($html)->toContain('alt="Humetría"')->not->toContain('<span class="company-name"');
    }
})->with(inventory());

// H-13: the link spelled out sat against its sentence in the plain-text email («navegador:https://…»).
it('keeps a space between the sentence and the link spelled out under the button', function (Closure $make): void {
    $html = (string) $make()->toMail(new AnonymousNotifiable)->render();

    expect($html)->toMatch('#navegador: <br><a href="https?://[^"]+"#')->not->toContain('navegador:<br>');
})->with([
    'survey invitation reminder' => [fn () => new SurveyInvitation(SurveyEmail::Reminder, 'tok', 'Ana', inventoryCompany(), null, 'Planta', CarbonImmutable::parse('2026-12-01'), 15)],
]);

// E-12: what a person of the company receives speaks of «usted», the spelled-out link included. The Comisión's «documents to
// confirm» is written in the panel voice (AcknowledgementsTest) and stays out.
it('speaks to the worker as usted under the button too', function (Closure $make): void {
    $html = (string) $make()->toMail(new AnonymousNotifiable)->render();

    expect($html)->toContain('copie este enlace en su navegador')->not->toContain('en tu navegador');
})->with(array_map(fn (array $row): array => [$row[0]], array_intersect_key(inventory(), array_flip([
    'vacations portal link', 'leave request decided', 'leave request to decide', 'attendance enrollment', 'attendance device linked',
    'policy to confirm', 'support waiting', 'risks to confirm', 'standing questions', 'portal invitation', 'portal link', 'portal reminder',
    'notice answered', 'climate feedback published',
]))));
