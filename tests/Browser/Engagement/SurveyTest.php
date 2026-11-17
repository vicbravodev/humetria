<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** A person invited to a climate survey of Autopartes del Bajío about their manager, whose link is /engagement/the-token. */
function engagementInvitee(): Invitation
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create();
    $manager = Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Marta Ruiz']);
    $person = Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
    $survey = Survey::factory()->active(now()->addDays(10)->toDateString())->create(['tenant_id' => $tenant->id]);

    return Invitation::factory()->create([
        'tenant_id' => $tenant->id,
        'survey_id' => $survey->id,
        'employee_id' => $person->id,
        'about_manager_id' => $manager->id,
        'token_hash' => Invitation::hashToken('the-token'),
    ]);
}

/**
 * A script that answers 4 to every closed question as a person would, by clicking, writes `$comment` in the first open
 * one and goes on with «Siguiente» (on the last question «Revisar y enviar») until the screen that sends (or `$limit` screens), and says which came first.
 */
function engagementAnswerScript(string $comment = '', int $limit = 80): string
{
    return <<<JS
        async () => {
            const wait = () => new Promise((done) => setTimeout(done, 25));
            const write = (box, text) => {
                Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(box, text);
                box.dispatchEvent(new Event('input', { bubbles: true }));
            };
            let written = false;
            for (let step = 0; step < {$limit}; step++) {
                const send = [...document.querySelectorAll('button')].find((button) => button.textContent.includes('Enviar mis respuestas'));
                if (send) return 'send';
                const choice = document.querySelector('input[type=radio][value="4"]');
                if (choice) choice.click();
                const box = document.querySelector('textarea');
                if (box && !written && '{$comment}' !== '') { write(box, '{$comment}'); written = true; }
                await wait();
                [...document.querySelectorAll('button')].find((button) => /Siguiente|Revisar y enviar/.test(button.textContent))?.click();
                await wait();
            }
            return 'lost';
        }
        JS;
}

it('opens with what the system keeps and shows, in «usted», and no promise of secrecy', function (bool $dark, bool $mobile): void {
    engagementInvitee();

    visitPage('engagement/survey', '/engagement/the-token', $dark, $mobile)
        ->assertSee('Autopartes del Bajío')
        ->assertSee('Confidencial')
        ->assertSee('Clima · 44 preguntas')
        ->assertSee('Su opinión ayuda a mejorar el trabajo en Autopartes del Bajío.')
        ->assertSee('El sistema no guarda quién contestó qué y solo muestra resultados de grupos de 5 personas o más.')
        ->assertSee('Puede dejar preguntas sin contestar.')
        ->assertSee('Unos 12 min')
        ->assertSee('Hasta el')
        ->assertDontSee('nadie sabrá')
        ->assertDontSee('100 %')
        ->assertDontSee('totalmente anónim');
})->with($looks);

// The same welcome as NOM-035: «Comenzar» alone at the foot, no «Anterior» that leads nowhere; the AI notice goes where the person writes.
it('starts with «Comenzar» alone at the foot of the phone, and says nothing of the AI before the open question', function (): void {
    engagementInvitee();

    visit('/engagement/the-token')->on()->mobile()
        ->assertDontSee('inteligencia artificial')
        ->assertDontSee('Anterior')
        ->assertScript('() => { const button = [...document.querySelectorAll("button")].find((it) => it.textContent.trim() === "Comenzar"); const box = button.getBoundingClientRect(); return box.bottom <= window.innerHeight && box.bottom > window.innerHeight - 100; }', true);
});

// On a question: «Anterior» and «Siguiente» in one row at the foot, «Omitir pregunta» under them as a lighter text action.
it('keeps «Anterior» and «Siguiente» in a row at the foot, with «Omitir pregunta» under them as text', function (bool $dark): void {
    engagementInvitee();

    visitPage('engagement/survey', '/engagement/the-token', $dark, true, fn ($page) => $page->click('Comenzar'))
        ->assertScript('() => { const find = (text) => [...document.querySelectorAll("button")].find((it) => it.textContent.trim() === text).getBoundingClientRect(); const back = find("Anterior"); const next = find("Siguiente"); const skip = find("Omitir pregunta"); return Math.abs(back.top - next.top) < 8 && skip.top >= next.bottom && skip.height >= 44 && skip.bottom <= window.innerHeight; }', true)
        ->assertScript('() => getComputedStyle([...document.querySelectorAll("button")].find((it) => it.textContent.trim() === "Omitir pregunta")).borderTopWidth', '0px');
})->with(['light' => false, 'dark' => true]);

it('explains a link that does not work, naming no company', function (bool $dark, bool $mobile): void {
    engagementInvitee();

    visitPage('engagement/survey', '/engagement/not-the-token', $dark, $mobile)
        ->assertSee('Este enlace ya no está disponible')
        ->assertDontSee('Autopartes del Bajío');
})->with($looks);

it('says the survey is closed once its last day passed', function (bool $dark, bool $mobile): void {
    engagementInvitee();
    Survey::query()->withoutGlobalScopes()->update(['closes_on' => SurveyClock::day()->subDay()->toDateString()]);

    visitPage('engagement/survey', '/engagement/the-token', $dark, $mobile)->assertSee('Esta encuesta ya cerró');
})->with($looks);

it('shows one question at a time with large options, and choosing never moves on', function (bool $dark, bool $mobile): void {
    engagementInvitee();

    visitPage('engagement/survey', '/engagement/the-token', $dark, $mobile, fn ($page) => $page->click('Comenzar'))
        ->assertSee('Pregunta 1 de 44')
        ->assertSee('Me siento orgulloso(a) de trabajar en esta empresa.')
        ->assertSee('Totalmente en desacuerdo')
        ->assertSee('Omitir pregunta')
        ->assertScript('() => [...document.querySelectorAll("main fieldset label")].every((option) => option.getBoundingClientRect().height >= 44)');
})->with($looks);

it('stays on the question after choosing an option, and moves on with «Siguiente» or «Omitir pregunta»', function (): void {
    engagementInvitee();

    $page = visit('/engagement/the-token')->on()->mobile()->click('Comenzar');
    $page->script("() => { document.querySelector('input[type=radio][value=\"4\"]').click(); return true; }");
    $page->assertSee('Pregunta 1 de 44')->assertSee('Me siento orgulloso(a)')
        ->click('Siguiente')
        ->assertSee('Pregunta 2 de 44')
        ->click('Omitir pregunta')
        ->assertSee('Pregunta 3 de 44');
});

it('keeps the closed answers on the device across a reload and the comments nowhere', function (): void {
    engagementInvitee();
    $page = visit('/engagement/the-token')->on()->mobile()->click('Comenzar');
    $page->script("() => { document.querySelector('input[type=radio][value=\"5\"]').click(); return true; }");
    $page->click('Siguiente')->refresh()
        ->assertSee('Pregunta 2 de 44')
        ->click('Anterior')
        ->assertScript('() => document.querySelector(\'input[type=radio][value="5"]\').checked', true);

    $page->script(engagementAnswerScript('ZZ-comentario-del-navegador', 30));
    $page->assertScript("() => Object.values(localStorage).join('|').includes('ZZ-comentario')", false);
});

it('sends the whole survey once and says thanks, with the comment as the person wrote it', function (bool $dark, bool $mobile): void {
    $invitation = engagementInvitee();

    $page = visitPage('engagement/survey', '/engagement/the-token', $dark, $mobile, fn ($page) => $page->click('Comenzar'));
    $page->script(engagementAnswerScript('Más claridad <b>por favor</b>'));
    $page->assertSee('Ya terminó la encuesta')
        ->click('Enviar mis respuestas')
        ->assertSee('Gracias. Sus respuestas ya se enviaron.')
        ->assertNoJavaScriptErrors();

    expect(Response::query()->sole()->answers)->toHaveCount(40)
        ->and(Comment::query()->pluck('body')->all())->toContain('Más claridad <b>por favor</b>')
        ->and($invitation->fresh()?->answered)->toBeTrue();
})->with($looks);

// E-04: the answers wait on the phone; sending without signal says so and sends by itself when it is back, once.
it('says there is no signal when sending, and sends by itself when it is back', function (): void {
    engagementInvitee();

    $page = visit('/engagement/the-token')->on()->mobile()->click('Comenzar');
    $page->script(engagementAnswerScript());
    // Every request fails as without a network until `__offline` is turned off.
    $page->script('() => { const send = XMLHttpRequest.prototype.send; window.__offline = true; XMLHttpRequest.prototype.send = function (...args) { if (window.__offline) { setTimeout(() => this.onerror?.(), 0); return; } send.apply(this, args); }; }');
    $page->click('Enviar mis respuestas');

    $page->assertSee('Sin conexión:')
        ->assertSee('se enviará sola cuando vuelva la señal')
        ->assertSee('Sus respuestas siguen en este teléfono.')
        ->assertNoJavaScriptErrors();

    expect(Response::query()->count())->toBe(0);

    $page->script('() => { window.__offline = false; window.dispatchEvent(new Event("online")); }');
    $page->assertSee('Gracias. Sus respuestas ya se enviaron.')->assertDontSee('Sin conexión:')->assertNoJavaScriptErrors();

    expect(Response::query()->count())->toBe(1);
});

it('shows the thanks when the used link is opened again', function (bool $dark, bool $mobile): void {
    engagementInvitee();
    Invitation::query()->withoutGlobalScopes()->update(['answered' => true]);

    visitPage('engagement/survey', '/engagement/the-token', $dark, $mobile)
        ->assertSee('Gracias. Sus respuestas ya se enviaron.');
})->with($looks);

// One closing for both surveys: the arch of the logo filled to the end with the check, centered; no word on clinical attention.
it('closes with the same full arch as NOM-035, centered, and the pulse words', function (bool $dark): void {
    engagementInvitee();
    Invitation::query()->withoutGlobalScopes()->update(['answered' => true]);

    visitPage('engagement/survey', '/engagement/the-token', $dark, true)
        ->assertSee('Encuesta enviada')
        ->assertSee('Gracias. Sus respuestas ya se enviaron.')
        ->assertSee('Ya puede cerrar esta página.')
        ->assertDontSee('IMSS')
        ->assertScript('() => document.querySelector("main [data-slot=arc-meter] path:last-of-type").getAttribute("stroke-dashoffset")', '0')
        ->assertScript('() => { const box = document.querySelector("main [data-slot=arc-meter]").getBoundingClientRect(); return box.top > window.innerHeight * 0.15; }', true);
})->with(['light' => false, 'dark' => true]);

/** A script that leaves every question blank («Omitir pregunta», or «Siguiente» on an instruction) until an open question shows. */
const TO_OPEN_QUESTION = <<<'JS'
    async () => {
        for (let step = 0; step < 60 && document.querySelector('textarea') === null; step++) {
            const buttons = [...document.querySelectorAll('button')];
            (buttons.find((it) => it.textContent.trim() === 'Omitir pregunta') ?? buttons.find((it) => it.textContent.includes('Siguiente')))?.click();
            await new Promise((done) => setTimeout(done, 25));
        }
        return true;
    }
    JS;

/** A script that leaves every question blank until the 0 to 10 one shows. */
const TO_RECOMMEND_QUESTION = <<<'JS'
    async () => {
        for (let step = 0; step < 60 && document.querySelector('input[type=radio][value="10"]') === null; step++) {
            const buttons = [...document.querySelectorAll('button')];
            (buttons.find((it) => it.textContent.trim() === 'Omitir pregunta') ?? buttons.find((it) => it.textContent.includes('Siguiente')))?.click();
            await new Promise((done) => setTimeout(done, 25));
        }
        return true;
    }
    JS;

// Option A of the review: 0 to 5 and 6 to 10 in two equal rows on a phone, the anchors on the scale and an echo of the choice.
it('asks the 0 to 10 question in two equal rows with its anchors, and echoes the number chosen', function (bool $dark): void {
    engagementInvitee();

    $page = visitPage('engagement/survey', '/engagement/the-token', $dark, true, function ($page): void {
        $page->click('Comenzar');
        $page->script(TO_RECOMMEND_QUESTION);
    })
        ->assertSee('¿Qué tan probable es que recomiende esta empresa como lugar para trabajar?')
        ->assertDontSee('(0 = nada probable')
        ->assertSee('Nada probable')
        ->assertSee('Muy probable')
        // Six numbers on the first row, five on the second, every one as wide and at least 44 px.
        ->assertScript('() => { const boxes = [...document.querySelectorAll("main input[type=radio]")].map((input) => input.closest("label").getBoundingClientRect()); const rows = [...new Set(boxes.map((box) => Math.round(box.top)))]; return rows.length === 2 && boxes.filter((box) => Math.round(box.top) === rows[0]).length === 6 && boxes.every((box) => box.height >= 44 && Math.abs(box.width - boxes[0].width) < 1); }', true)
        ->assertDontSee('Su respuesta:');

    $page->script("() => { document.querySelector('input[type=radio][value=\"9\"]').click(); return true; }");
    $page->assertSee('Su respuesta: 9 de 10');
})->with(['light' => false, 'dark' => true]);

it('lays the 0 to 10 question in one row on a computer', function (): void {
    engagementInvitee();

    $page = visit('/engagement/the-token')->click('Comenzar');
    $page->script(TO_RECOMMEND_QUESTION);

    $page->assertScript('() => new Set([...document.querySelectorAll("main input[type=radio]")].map((input) => Math.round(input.closest("label").getBoundingClientRect().top))).size', 1);
});

// The comments still go to an AI provider to be summarized: it is said where the person writes, not in the welcome.
it('says at the open question that an AI provider summarizes the comments, and not to write a name', function (bool $dark): void {
    engagementInvitee();

    visitPage('engagement/survey', '/engagement/the-token', $dark, true, function ($page): void {
        $page->click('Comenzar');
        $page->script(TO_OPEN_QUESTION);
    })
        ->assertSee('Un proveedor de inteligencia artificial resume los comentarios.')
        ->assertSee('Por eso, no escriba su nombre ni datos que lo identifiquen.');
})->with(['light' => false, 'dark' => true]);
