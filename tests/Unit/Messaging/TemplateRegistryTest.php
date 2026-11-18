<?php

declare(strict_types=1);

use App\Modules\Messaging\Enums\TemplateButtonType;
use App\Modules\Messaging\Enums\TemplateCategory;
use App\Modules\Messaging\Support\AuthenticationTemplate;
use App\Modules\Messaging\Support\TemplateButton;
use App\Modules\Messaging\Support\TemplateDefinition;
use App\Modules\Messaging\Support\TemplateRegistry;
use Tests\TestCase;

uses(TestCase::class);

mutates(TemplateRegistry::class);

it('registers the two invitation templates as UTILITY in es_MX, with the variables of their text in order', function (string $key): void {
    $template = (new TemplateRegistry)->get($key);

    expect($template->key)->toBe($key)
        ->and($template->language)->toBe('es_MX')
        ->and($template->category)->toBe(TemplateCategory::Utility)
        ->and($template->bodyVariables())->toBe(['empresa', 'fecha_limite'])
        ->and($template->bodyParameterCount)->toBe(2)
        ->and($template->footer)->toBe('Humetria')
        ->and($template->isPending())->toBeFalse();
})->with(['survey_invite_v1', 'engagement_invite_v1']);

it('keeps the approved texts of the catalog, word for word', function (): void {
    $registry = new TemplateRegistry;
    $survey = $registry->get('survey_invite_v1');
    $engagement = $registry->get('engagement_invite_v1');

    expect($survey->header)->toBe('Su evaluación de la NOM-035')
        ->and($survey->body)->toBe('Hola, buen día. Le escribimos de parte de {{empresa}}. Como parte de la NOM-035, tiene pendiente contestar una evaluación sobre su trabajo. Es confidencial y puede hacerlo hasta el {{fecha_limite}}. Su enlace es personal y solo sirve una vez.')
        ->and($engagement->header)->toBe('Encuesta anónima de {{empresa}}')
        ->and($engagement->body)->toBe('Hola, buen día. Le escribimos de parte de {{empresa}}. Queremos saber cómo le va en su trabajo con esta encuesta anónima: nadie verá sus respuestas individuales. Tiene hasta el {{fecha_limite}} para contestarla y su enlace es personal.');
});

it('gives the invitations their three buttons: the link, the privacy notice and the opt-out', function (string $key, string $routeName): void {
    $buttons = (new TemplateRegistry)->get($key)->buttons;

    expect(array_map(fn ($button): string => $button->text, $buttons))->toBe(['Contestar ahora', 'Aviso de privacidad', 'Dejar de recibir'])
        ->and($buttons[0]->url)->toEndWith('{{1}}')
        ->and(str_replace('{{1}}', 'x', (string) $buttons[0]->url))->toBe(route($routeName, 'x'))
        ->and($buttons[1]->url)->toBe(route('privacy-notice'))
        ->and($buttons[2]->type)->toBe(TemplateButtonType::QuickReply);
})->with([
    ['survey_invite_v1', 'survey.show'],
    ['engagement_invite_v1', 'engagement.survey.show'],
]);

it('throws for a template it does not know', function (): void {
    (new TemplateRegistry)->get('nope');
})->throws(InvalidArgumentException::class);

it('has a template for each invitation type the modules register', function (): void {
    $types = collect(app()->tagged(App\Modules\Messaging\Contracts\InvitationLinks::TAG))->map(fn (App\Modules\Messaging\Contracts\InvitationLinks $links): string => $links->type())->sort()->values()->all();
    $registry = new TemplateRegistry;

    expect($types)->toBe(['campaign_participant', 'engagement_invitation'])
        ->and(array_map(fn (string $type): string => $registry->forInvitation($type)->key, $types))->toBe(['survey_invite_v1', 'engagement_invite_v1']);
});

/**
 * The catalog of `2026-11-18-whatsapp-templates.md`, written out so a change to the registry is a change to this test too:
 * key => [header, body, button texts in order].
 */
function catalog(): array
{
    $hello = 'Hola, buen día. Le escribimos de parte de {{empresa}}.';
    $stop = 'Dejar de recibir';

    return [
        'survey_invite_v1' => ['Su evaluación de la NOM-035', "{$hello} Como parte de la NOM-035, tiene pendiente contestar una evaluación sobre su trabajo. Es confidencial y puede hacerlo hasta el {{fecha_limite}}. Su enlace es personal y solo sirve una vez.", ['Contestar ahora', 'Aviso de privacidad', $stop]],
        'seating_invite_v1' => ['Encuesta sobre su puesto de trabajo', "{$hello} Como parte de la Ley Silla, queremos saber cómo son los asientos y las pausas en su trabajo. Son unos minutos y puede contestar hasta el {{fecha_limite}}. Su enlace es personal y solo sirve una vez.", ['Contestar ahora', 'Aviso de privacidad', $stop]],
        'engagement_invite_v1' => ['Encuesta anónima de {{empresa}}', "{$hello} Queremos saber cómo le va en su trabajo con esta encuesta anónima: nadie verá sus respuestas individuales. Tiene hasta el {{fecha_limite}} para contestarla y su enlace es personal.", ['Contestar ahora', 'Aviso de privacidad', $stop]],
        'survey_reminder_v1' => [null, 'Hola, buen día. Le recordamos que su evaluación de parte de {{empresa}} sigue pendiente. Puede contestarla hasta el {{fecha_limite}}. Su enlace sigue siendo personal.', ['Continuar', $stop]],
        'engagement_reminder_v1' => [null, 'Hola, buen día. Le recordamos que su evaluación de parte de {{empresa}} sigue pendiente. Puede contestarla hasta el {{fecha_limite}}. Su enlace sigue siendo personal.', ['Continuar', $stop]],
        'leave_decision_v1' => [null, 'Hola, buen día. {{empresa}} ya respondió su solicitud del {{fecha}}. Puede verla en su portal.', ['Ver respuesta', $stop]],
        'leave_to_decide_v1' => [null, 'Hola, buen día. Tiene una solicitud de su equipo en {{empresa}} que le toca responder. Puede hacerlo en su portal.', ['Responder', $stop]],
        'policy_to_confirm_v1' => [null, 'Hola, buen día. {{empresa}} publicó su política de prevención y necesita que confirme que la leyó. Puede leerla y dar su acuse en su página.', ['Leer y confirmar', $stop]],
        'engagement_feedback_v1' => [null, 'Hola, buen día. {{empresa}} publicó lo que hará a partir de la encuesta de clima. Puede leerlo en su página.', ['Leer', $stop]],
        'seating_risks_v1' => [null, 'Hola, buen día. {{empresa}} ya evaluó su puesto de trabajo. Puede leer el resultado y confirmar que se lo explicaron en su página.', ['Ver resultado', $stop]],
        'seating_questions_v1' => [null, 'Hola, buen día. {{empresa}} quiere hacerle dos preguntas sobre su trabajo de pie. Contestar es voluntario y toma un minuto.', ['Contestar', $stop]],
        'telework_invite_v1' => [null, 'Hola, buen día. {{empresa}} le pide completar su lista de verificación de teletrabajo. Puede hacerlo en su portal.', ['Completar lista', $stop]],
        'telework_reminder_v1' => [null, 'Hola, buen día. Tiene pendientes en su portal de teletrabajo de {{empresa}}. Puede revisarlos cuando guste.', ['Ver pendientes', $stop]],
        'telework_answered_v1' => [null, 'Hola, buen día. {{empresa}} respondió el aviso que usted envió. El detalle solo se ve en su portal.', ['Ver respuesta']],
        'support_waiting_v1' => [null, 'Hola, buen día. {{empresa}} le dejó un aviso en su portal personal. Solo usted puede verlo.', ['Ver aviso']],
        'attendance_correction_v1' => [null, 'Hola, buen día. {{empresa}} respondió su solicitud de corrección de asistencia. Puede verla en su portal.', ['Ver respuesta', $stop]],
        'worker_invite_v1' => [null, 'Hola, buen día. {{empresa}} le invita a crear su acceso personal al portal. El enlace es personal y solo sirve una vez.', ['Crear mi acceso']],
        'access_link_v1' => [null, 'Hola, buen día. Este es el enlace que pidió para entrar a su portal de {{empresa}}. Solo sirve una vez y vence en {{minutos}} minutos.', ['Entrar al portal']],
        'key_registered_v1' => [null, 'Hola, buen día. Se registró una llave de acceso nueva en su portal de {{empresa}}. Si no fue usted, avísenos.', ['No fui yo']],
    ];
}

it('registers every template of the catalog, and nothing else', function (): void {
    expect(array_keys((new TemplateRegistry)->all()))->toEqualCanonicalizing([...array_keys(catalog()), 'proof_code_v1'])
        ->and(count((new TemplateRegistry)->all()))->toBe(20);
});

it('writes each text of the catalog word for word, with its footer and its buttons', function (string $key): void {
    [$header, $body, $buttons] = catalog()[$key];
    $template = (new TemplateRegistry)->get($key);

    expect($template)->toBeInstanceOf(TemplateDefinition::class)
        ->and($template->header)->toBe($header)
        ->and($template->body)->toBe($body)
        ->and($template->footer)->toBe('Humetria')
        ->and($template->language)->toBe('es_MX')
        ->and($template->category)->toBe(TemplateCategory::Utility)
        ->and(array_map(fn (TemplateButton $button): string => $button->text, $template->buttons))->toBe($buttons);
})->with(array_keys(catalog()));

it('gives the examples of the catalog to every variable', function (): void {
    foreach ((new TemplateRegistry)->all() as $template) {
        if ($template instanceof TemplateDefinition) {
            expect($template->examples)->each->not->toBeEmpty();
            expect(array_diff_assoc($template->examples, ['empresa' => 'Industrias Ejemplo', 'fecha_limite' => 'viernes 20 de noviembre', 'fecha' => 'lunes 16 de noviembre', 'minutos' => '15']))->toBe([]);
        }
    }
});

it('has the opt-out in every template that is not a code or a security notice', function (): void {
    $without = ['telework_answered_v1', 'support_waiting_v1', 'worker_invite_v1', 'access_link_v1', 'key_registered_v1'];

    foreach (catalog() as $key => [, , $buttons]) {
        expect(in_array('Dejar de recibir', $buttons, true))->toBe(! in_array($key, $without, true));
    }
});

it('leaves the buttons whose route does not end in the variable as pending, and the invitations as real links', function (): void {
    $registry = new TemplateRegistry;
    $real = ['survey_invite_v1', 'seating_invite_v1', 'engagement_invite_v1', 'survey_reminder_v1', 'engagement_reminder_v1'];

    foreach (catalog() as $key => $_) {
        expect($registry->get($key)->isPending())->toBe(! in_array($key, $real, true), $key);
    }

    expect($registry->get('survey_reminder_v1')->buttons[0]->url)->toBe(str_replace('PLACEHOLDER', '{{1}}', route('survey.show', 'PLACEHOLDER')))
        ->and($registry->get('engagement_reminder_v1')->buttons[0]->url)->toBe(str_replace('PLACEHOLDER', '{{1}}', route('engagement.survey.show', 'PLACEHOLDER')))
        ->and($registry->get('seating_invite_v1')->buttons[0]->url)->toBe(str_replace('PLACEHOLDER', '{{1}}', route('survey.show', 'PLACEHOLDER')))
        ->and($registry->get('leave_decision_v1')->buttons[0]->pending)->toBeString();
});

it('models the proof code as the authentication template of Meta: fixed text, a copy-code button, nothing of ours', function (): void {
    $template = (new TemplateRegistry)->get('proof_code_v1');

    expect($template)->toBeInstanceOf(AuthenticationTemplate::class)
        ->and($template->category)->toBe(TemplateCategory::Authentication)
        ->and($template->language)->toBe('es_MX')
        ->and($template->button()->type)->toBe(TemplateButtonType::CopyCode)
        ->and($template->button()->text)->toBe('Copiar código');
});

it('feeds the invitation templates the variables the modules fill, in the order they fill them', function (): void {
    $registry = new TemplateRegistry;

    // Campaigns and Engagement pass [company trade name, last day]; the definition names them by order.
    foreach (collect(app()->tagged(App\Modules\Messaging\Contracts\InvitationLinks::TAG)) as $links) {
        expect($registry->forInvitation($links->type())->bodyVariables())->toBe(['empresa', 'fecha_limite']);
    }
});

it('asks for the seating invite only by its name: it has no invitation type yet', function (): void {
    expect((new TemplateRegistry)->get('seating_invite_v1')->bodyVariables())->toBe(['empresa', 'fecha_limite']);
    (new TemplateRegistry)->forInvitation('seating_participant');
})->throws(InvalidArgumentException::class);

it('never starts or ends a body with a variable, which Meta rejects (confirmed against the real API)', function (): void {
    foreach (catalog() as $key => [, $body]) {
        expect($body)->not->toMatch('/\A\W*\{\{/', $key)->not->toMatch('/\{\{\w+\}\}\W*\z/', $key);
    }
});

it('has no phone button in any template: «Llamar a RH» was dropped', function (): void {
    foreach ((new TemplateRegistry)->all() as $template) {
        if ($template instanceof TemplateDefinition) {
            expect(array_map(fn (TemplateButton $button): TemplateButtonType => $button->type, $template->buttons))->not->toContain(TemplateButtonType::Phone);
        }
    }
});
