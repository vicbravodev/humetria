<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
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
 * one and goes on with «Siguiente» until the screen that sends (or `$limit` screens), and says which came first.
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
                [...document.querySelectorAll('button')].find((button) => button.textContent.includes('Siguiente'))?.click();
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
        ->assertSee('Su opinión ayuda a mejorar el trabajo en Autopartes del Bajío. Son unos 12 minutos.')
        ->assertSee('El sistema no guarda quién contestó qué y solo muestra resultados de grupos de 5 personas o más.')
        ->assertSee('Puede dejar sin contestar cualquier pregunta.')
        ->assertSee('Lo que escriba en las preguntas abiertas lo procesa un proveedor de inteligencia artificial para resumirlo.')
        ->assertDontSee('nadie sabrá')
        ->assertDontSee('100 %')
        ->assertDontSee('totalmente anónim');
})->with($looks);

it('explains a link that does not work, naming no company', function (bool $dark, bool $mobile): void {
    engagementInvitee();

    visitPage('engagement/survey', '/engagement/not-the-token', $dark, $mobile)
        ->assertSee('Este enlace ya no está disponible')
        ->assertDontSee('Autopartes del Bajío');
})->with($looks);

it('says the survey is closed once its last day passed', function (bool $dark, bool $mobile): void {
    engagementInvitee();
    Survey::query()->withoutGlobalScopes()->update(['closes_on' => now()->subDay()->toDateString()]);

    visitPage('engagement/survey', '/engagement/the-token', $dark, $mobile)->assertSee('Esta encuesta ya cerró');
})->with($looks);

it('shows one question at a time with large options, and choosing never moves on', function (bool $dark, bool $mobile): void {
    engagementInvitee();

    visitPage('engagement/survey', '/engagement/the-token', $dark, $mobile, fn ($page) => $page->click('Siguiente'))
        ->assertSee('Pregunta 1 de 44')
        ->assertSee('Me siento orgulloso(a) de trabajar en esta empresa.')
        ->assertSee('Totalmente en desacuerdo')
        ->assertSee('Omitir pregunta')
        ->assertScript('() => [...document.querySelectorAll("main fieldset label")].every((option) => option.getBoundingClientRect().height >= 44)');
})->with($looks);

it('stays on the question after choosing an option, and moves on with «Siguiente» or «Omitir pregunta»', function (): void {
    engagementInvitee();

    $page = visit('/engagement/the-token')->on()->mobile()->click('Siguiente');
    $page->script("() => { document.querySelector('input[type=radio][value=\"4\"]').click(); return true; }");
    $page->assertSee('Pregunta 1 de 44')->assertSee('Me siento orgulloso(a)')
        ->click('Siguiente')
        ->assertSee('Pregunta 2 de 44')
        ->click('Omitir pregunta')
        ->assertSee('Pregunta 3 de 44');
});

it('keeps the closed answers on the device across a reload and the comments nowhere', function (): void {
    engagementInvitee();
    $page = visit('/engagement/the-token')->on()->mobile()->click('Siguiente');
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

    $page = visitPage('engagement/survey', '/engagement/the-token', $dark, $mobile, fn ($page) => $page->click('Siguiente'));
    $page->script(engagementAnswerScript('Más claridad <b>por favor</b>'));
    $page->assertSee('Ya terminó la encuesta')
        ->click('Enviar mis respuestas')
        ->assertSee('Gracias. Sus respuestas ya se enviaron.')
        ->assertNoJavaScriptErrors();

    expect(Response::query()->sole()->answers)->toHaveCount(40)
        ->and(Comment::query()->pluck('body')->all())->toContain('Más claridad <b>por favor</b>')
        ->and($invitation->fresh()?->answered)->toBeTrue();
})->with($looks);

it('shows the thanks when the used link is opened again', function (bool $dark, bool $mobile): void {
    engagementInvitee();
    Invitation::query()->withoutGlobalScopes()->update(['answered' => true]);

    visitPage('engagement/survey', '/engagement/the-token', $dark, $mobile)
        ->assertSee('Gracias. Sus respuestas ya se enviaron.');
})->with($looks);
