<?php

declare(strict_types=1);

use App\Modules\Messaging\Enums\TemplateCategory;
use App\Modules\Messaging\Support\TemplateButton;
use App\Modules\Messaging\Support\TemplateDefinition;

mutates(TemplateDefinition::class, TemplateButton::class);

/** @param  array<string, mixed>  $overrides */
function definition(array $overrides = []): TemplateDefinition
{
    return new TemplateDefinition(...[
        'key' => 'sample_v1',
        'language' => 'es_MX',
        'body' => 'Hola. Le escribimos de parte de {{empresa}}. Tiene hasta el {{fecha_limite}} para contestar.',
        'examples' => ['empresa' => 'Industrias Ejemplo', 'fecha_limite' => 'viernes 20 de noviembre'],
        'header' => null,
        'footer' => 'Humetria',
        'buttons' => [TemplateButton::urlWithVariable('Contestar ahora', 'https://humetria.test/survey/{{1}}', 'abc123')],
        ...$overrides,
    ]);
}

it('lists the body variables in the order they appear, once each', function (): void {
    $template = definition(['body' => 'Hola. {{fecha_limite}} de parte de {{empresa}}. Otra vez {{fecha_limite}}.', 'examples' => ['empresa' => 'A', 'fecha_limite' => 'B']]);

    expect($template->bodyVariables())->toBe(['fecha_limite', 'empresa'])
        ->and($template->category)->toBe(TemplateCategory::Utility);
});

it('maps the positional parameters of a message to the names, in order', function (): void {
    expect(definition()->namedParameters(['Acme', '12 de mayo']))->toBe(['empresa' => 'Acme', 'fecha_limite' => '12 de mayo']);
});

it('refuses a message whose parameters do not match the variables', function (): void {
    definition()->namedParameters(['Acme']);
})->throws(InvalidArgumentException::class);

it('accepts a header with one variable the body also has', function (): void {
    $template = definition(['header' => 'Encuesta de {{empresa}}']);

    expect($template->headerVariable())->toBe('empresa');
});

it('refuses an invalid definition', function (array $overrides): void {
    definition($overrides);
})->throws(InvalidArgumentException::class)->with([
    'unversioned name' => [['key' => 'sample']],
    'empty body' => [['body' => '', 'examples' => []]],
    'body over 1024' => [['body' => str_repeat('a', 1020).' {{empresa}} fin', 'examples' => ['empresa' => 'A']]],
    'header over 60' => [['header' => str_repeat('a', 61)]],
    'footer over 60' => [['footer' => str_repeat('a', 61)]],
    'body starts with a variable' => [['body' => '{{empresa}} le escribe y {{fecha_limite}} es el día.']],
    'body ends with a variable' => [['body' => 'Hola, de parte de {{empresa}} hasta {{fecha_limite}}']],
    'two variables together' => [['body' => 'Hola {{empresa}}{{fecha_limite}} gracias.']],
    'variable without an example' => [['examples' => ['empresa' => 'A']]],
    'example of a variable nobody uses' => [['examples' => ['empresa' => 'A', 'fecha_limite' => 'B', 'otra' => 'C']]],
    'positional variable' => [['body' => 'Hola {{1}} gracias.', 'examples' => []]],
    'header with two variables' => [['header' => '{{empresa}} {{fecha_limite}}']],
    'header variable not in the examples' => [['header' => 'Hola {{otra}}']],
    'more than 10 buttons' => [['buttons' => array_fill(0, 11, TemplateButton::quickReply('Dejar de recibir'))]],
    'more than 2 url buttons' => [['buttons' => array_fill(0, 3, TemplateButton::fixedUrl('Aviso de privacidad', 'https://humetria.test/privacy-notice'))]],
    'more than 1 phone button' => [['buttons' => [TemplateButton::phone('Llamar a RH', '+525511112222'), TemplateButton::phone('Llamar a RH', '+525511112222')]]],
    'quick reply before another type' => [['buttons' => [TemplateButton::quickReply('Dejar de recibir'), TemplateButton::fixedUrl('Aviso de privacidad', 'https://humetria.test/privacy-notice')]]],
]);

it('accepts the limits exactly', function (): void {
    $body = str_repeat('a', 1024 - strlen(' {{empresa}} fin') - strlen(' {{fecha_limite}} fin')).' {{empresa}} fin {{fecha_limite}} fin';

    expect(definition(['body' => $body, 'header' => str_repeat('a', 60), 'footer' => str_repeat('a', 60)])->body)->toHaveLength(1024)
        ->and(definition(['buttons' => array_fill(0, 10, TemplateButton::quickReply(str_repeat('a', 25)))])->buttons)->toHaveCount(10);
});

it('refuses an invalid button', function (Closure $make): void {
    $make();
})->throws(InvalidArgumentException::class)->with([
    'text over 25' => [fn () => TemplateButton::quickReply(str_repeat('a', 26))],
    'empty text' => [fn () => TemplateButton::quickReply('')],
    'url variable not at the end' => [fn () => TemplateButton::urlWithVariable('Ver', 'https://humetria.test/{{1}}/x', 'a')],
    'url with two variables' => [fn () => TemplateButton::urlWithVariable('Ver', 'https://humetria.test/{{1}}/{{1}}', 'a')],
    'url without a variable' => [fn () => TemplateButton::urlWithVariable('Ver', 'https://humetria.test/x', 'a')],
    'fixed url with a variable' => [fn () => TemplateButton::fixedUrl('Ver', 'https://humetria.test/{{1}}')],
    'pending without a reason' => [fn () => TemplateButton::pendingUrl('Ver', '')],
]);

it('keeps a pending button explicit and lets the template say it is pending', function (): void {
    $template = definition(['buttons' => [TemplateButton::pendingUrl('Ver respuesta', 'Falta la ruta que termina en la variable'), TemplateButton::quickReply('Dejar de recibir')]]);

    expect($template->isPending())->toBeTrue()
        ->and($template->buttons[0]->url)->toBeNull()
        ->and($template->buttons[0]->pending)->toBe('Falta la ruta que termina en la variable')
        ->and(definition()->isPending())->toBeFalse();
});
