<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/**
 * A person invited to Guía I and the guide given, whose link is /survey/the-token.
 *
 * @param  list<string>  $instruments
 */
function invitedPerson(array $instruments = ['nom035.guide-i', 'nom035.guide-iii']): Participant
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    $campaign = Campaign::factory()->active(now()->addDays(10)->toDateString())->create([
        'tenant_id' => $tenant->id,
        'instruments' => $instruments,
    ]);
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id, 'token_hash' => Participant::hashToken('the-token')]);

    foreach ($instruments as $instrument) {
        ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => $instrument]);
    }

    return $participant;
}

it('opens the survey with what the norm asks to say before it', function (bool $dark, bool $mobile): void {
    invitedPerson();

    visitPage('campaigns/survey', '/survey/the-token', $dark, $mobile)
        ->assertSee('Autopartes del Bajío')
        ->assertSee('Confidencial')
        ->assertSee('dos últimos meses')
        ->assertSee('No hay respuestas correctas ni incorrectas.');
})->with($looks);

it('explains a link that does not work, naming no company', function (bool $dark, bool $mobile): void {
    invitedPerson();

    visitPage('campaigns/survey', '/survey/not-the-token', $dark, $mobile)
        ->assertSee('Este enlace no es válido')
        ->assertDontSee('Autopartes del Bajío');
})->with($looks);

/**
 * Answers every question on the screen as a person would, by clicking: "Algunas veces" on each item (or "No" in Guía
 * I), "No" to serving clients and "Sí" to supervising people. Then presses the main button and says which it was.
 */
const ANSWER_SCREEN = <<<'JS'
    () => {
        const gate = { serves_clients: 'no', supervises: 'yes' };
        document.querySelectorAll('fieldset').forEach((fieldset) => {
            const first = fieldset.querySelector('input[type=radio]');
            if (first === null) return;
            const value = gate[first.name] ?? (fieldset.querySelector('input[value=sometimes]') ? 'sometimes' : 'no');
            fieldset.querySelector(`input[value="${value}"]`).click();
        });
        return true;
    }
JS;

const PRESS_MAIN = <<<'JS'
    () => {
        const button = [...document.querySelectorAll('button')].find((candidate) => /Siguiente|Enviar respuestas/.test(candidate.textContent));
        const label = button?.textContent.trim() ?? 'none';
        button?.click();
        return label;
    }
JS;

/** Answers screen after screen until the guide is sent. */
function answerGuide(Pest\Browser\Api\AwaitableWebpage $page): void
{
    foreach (range(1, 90) as $screen) {
        $page->script(ANSWER_SCREEN);

        if ($page->script(PRESS_MAIN) === 'Enviar respuestas') {
            return;
        }
    }

    throw new RuntimeException('The guide never ended.');
}

it('lets the employee answer Guía I and Guía II from the phone, one question per screen', function (): void {
    $participant = invitedPerson(['nom035.guide-i', 'nom035.guide-ii']);

    $page = visit('/survey/the-token')->on()->mobile()
        ->click('Comenzar')
        ->assertSee('sí lleva su nombre')
        ->click('Empezar')
        ->assertSee('Sección 1 de 4')
        ->assertSee('Pregunta 1 de 6');

    answerGuide($page);

    $page->assertSee('Enviada')
        ->click('Continuar con la Guía II')
        ->assertSee('anónima')
        ->click('Empezar')
        ->assertSee('Para responder las preguntas siguientes considere las condiciones de su centro de trabajo');

    answerGuide($page);

    $page->assertSee('Gracias, terminó')
        ->assertSee('Ya contestó todo lo que le correspondía')
        ->assertNoJavaScriptErrors();

    $response = App\Modules\Responses\Models\AnonymousResponse::query()->withoutGlobalScopes()->sole();
    $screening = App\Modules\Responses\Models\TraumaScreening::query()->withoutGlobalScopes()->sole();

    expect($screening->employee_id)->toBe($participant->employee_id)
        ->and($screening->requires_clinical_assessment)->toBeFalse()
        ->and($screening->answers)->toHaveCount(6)
        ->and($response->answers['serves_clients'])->toBe('no')
        ->and($response->answers['supervises'])->toBe('yes')
        ->and($response->answers)->not->toHaveKey('41')
        ->and($response->answers)->toHaveKey('46')
        ->and($response->answers)->toHaveCount(45);
});

it('asks up to three questions of a section per screen on a computer, and never moves on by itself', function (): void {
    invitedPerson(['nom035.guide-iii']);

    $page = visit('/survey/the-token')
        ->click('Comenzar')
        ->click('Empezar')
        ->assertSee('Su avance')
        ->assertSee('Pregunta 1 de 5')
        ->assertSee('Pregunta 3 de 5')
        ->assertDontSee('Pregunta 4 de 5')
        ->assertButtonDisabled('Siguiente');

    $page->script(ANSWER_SCREEN);

    $page->assertSee('Pregunta 1 de 5')
        ->assertButtonEnabled('Siguiente')
        ->click('Siguiente')
        ->assertSee('Pregunta 4 de 5');
});

it('keeps what was answered on the device after a reload, and picks up at the first unanswered question', function (): void {
    invitedPerson(['nom035.guide-iii']);

    $page = visit('/survey/the-token')->click('Comenzar')->click('Empezar');
    $page->script(ANSWER_SCREEN);
    $page->click('Siguiente')->assertSee('Pregunta 4 de 5');

    $page->refresh()
        ->click('Comenzar')
        ->click('Empezar')
        ->assertSee('Sección 1 de 14')
        ->assertSee('3 de 5 respondidas')
        ->assertSee('Pregunta 4 de 5')
        ->assertDontSee('Pregunta 1 de 5')
        ->assertButtonDisabled('Siguiente')
        // What was answered is still there, one screen back.
        ->click('Anterior')
        ->assertSee('Pregunta 1 de 5')
        ->assertButtonEnabled('Siguiente');
});

it('picks up at the first unanswered question on a phone too', function (): void {
    invitedPerson(['nom035.guide-iii']);

    $page = visit('/survey/the-token')->on()->mobile()->click('Comenzar')->click('Empezar');

    foreach (range(1, 2) as $screen) {
        $page->script(ANSWER_SCREEN);
        $page->click('Siguiente');
    }

    $page->assertSee('Pregunta 3 de 5')
        ->refresh()
        ->click('Comenzar')
        ->click('Empezar')
        ->assertSee('Pregunta 3 de 5')
        ->assertSee('2 de 5 respondidas')
        ->assertButtonDisabled('Siguiente');
});

it('stays on the same question when the screen narrows to a phone', function (): void {
    invitedPerson(['nom035.guide-iii']);

    $page = visit('/survey/the-token')->click('Comenzar')->click('Empezar');
    $page->script(ANSWER_SCREEN);
    $page->click('Siguiente')->assertSee('Pregunta 4 de 5');

    $page->resize(375, 812)
        ->assertSee('Pregunta 4 de 5')
        ->assertDontSee('Pregunta 2 de 5');
});

it('moves the focus to each new screen, so a screen reader announces it', function (bool $mobile): void {
    invitedPerson(['nom035.guide-ii']);

    $page = visitPage('campaigns/survey', '/survey/the-token', mobile: $mobile, reach: fn ($page) => $page->click('Comenzar')->click('Empezar'))
        ->assertScript('() => document.activeElement.textContent.includes("Para responder las preguntas siguientes")');

    $page->script(ANSWER_SCREEN);
    $page->click('Siguiente')
        ->assertScript('() => document.activeElement.getAttribute("tabindex") === "-1" && document.activeElement.closest("main") !== null');
})->with(['desktop' => false, 'mobile' => true]);

it('goes back from the first question of a guide to its introduction', function (): void {
    invitedPerson(['nom035.guide-iii']);

    visit('/survey/the-token')->on()->mobile()
        ->click('Comenzar')
        ->click('Empezar')
        ->assertSee('Pregunta 1 de 5')
        ->assertButtonEnabled('Anterior')
        ->click('Anterior')
        ->assertSee('Empezar')
        ->assertDontSee('Pregunta 1 de 5');
});

it('keeps a long company name inside the header on a phone', function (): void {
    invitedPerson(['nom035.guide-iii']);
    Tenant::query()->update(['trade_name' => 'Autopartes, Componentes y Refacciones Automotrices del Bajío Occidental de México']);

    visit('/survey/the-token')->on()->mobile()
        ->assertSee('Confidencial')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth')
        ->assertScript('() => { const header = document.querySelector("header"); const box = header.getBoundingClientRect(); return [...header.querySelectorAll("p")].every((part) => { const it = part.getBoundingClientRect(); return it.top >= box.top && it.bottom <= box.bottom && it.right <= box.right; }); }');
});

it('says there is nothing left when the used link is opened again', function (bool $dark, bool $mobile): void {
    $participant = invitedPerson(['nom035.guide-i']);
    ParticipantInstrument::query()->withoutGlobalScopes()->update(['status' => 'completed', 'completed_at' => now()]);

    visitPage('campaigns/survey', '/survey/the-token', $dark, $mobile)
        ->assertSee('Gracias, terminó')
        ->assertSee('Ya contestó todo lo que le correspondía')
        ->assertSee('Autopartes del Bajío');
})->with($looks);

it('shows each question as the design system asks: the instruction, the question and five large options', function (bool $dark, bool $mobile): void {
    invitedPerson(['nom035.guide-ii']);

    visitPage('campaigns/survey', '/survey/the-token', $dark, $mobile, fn ($page) => $page->click('Comenzar')->click('Empezar'))
        ->assertSee('Sección 1 de 8')
        ->assertSee('Mi trabajo me exige hacer mucho esfuerzo físico')
        ->assertSee('Casi siempre')
        ->assertSee('Nadie de su empresa puede ver lo que contesta.');
})->with($looks);

it('clears what the device kept for a link once it no longer works', function (): void {
    invitedPerson(['nom035.guide-i']);
    $page = visit('/survey/the-token');
    $page->script("() => { localStorage.setItem('humetria:survey:the-token:nom035.guide-i', '{\"1\":\"yes\"}'); localStorage.setItem('appearance', 'dark'); return true; }");
    ParticipantInstrument::query()->withoutGlobalScopes()->update(['status' => 'completed', 'completed_at' => now()]);

    $page->refresh()
        ->assertSee('Ya contestó todo lo que le correspondía')
        ->assertScript("() => localStorage.getItem('humetria:survey:the-token:nom035.guide-i')", null)
        ->assertScript("() => localStorage.getItem('appearance')", 'dark');
});

it('goes back from a guide\'s introduction to the list of guides', function (): void {
    invitedPerson();

    visit('/survey/the-token')
        ->click('Comenzar')
        ->assertSee('sí lleva su nombre')
        ->click('Anterior')
        ->assertSee('No hay respuestas correctas ni incorrectas.')
        ->assertSee('Comenzar');
});

it('says the survey is confidential before the first question, in the header and at the foot', function (bool $mobile): void {
    invitedPerson(['nom035.guide-iii']);

    visitPage('campaigns/survey', '/survey/the-token', mobile: $mobile)
        ->assertSeeIn('header[class]', 'Confidencial')
        ->assertSeeIn('main[class]', 'Sus respuestas son confidenciales')
        ->click('Comenzar')
        ->click('Empezar')
        ->assertSeeIn('main[class]', 'Nadie de su empresa puede ver lo que contesta.')
        ->assertScript('() => [...document.querySelectorAll("main fieldset label")].every((option) => option.getBoundingClientRect().height >= 56)');
})->with(['desktop' => false, 'mobile' => true]);
