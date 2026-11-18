<?php

declare(strict_types=1);

use App\Modules\Messaging\Enums\TemplateButtonType;
use App\Modules\Messaging\Enums\TemplateCategory;
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
