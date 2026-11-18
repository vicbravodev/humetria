<?php

declare(strict_types=1);

use App\Modules\Messaging\Support\TemplateRegistry;
use Tests\TestCase;

uses(TestCase::class);

mutates(TemplateRegistry::class);

it('registers the two invitation templates as UTILITY in es_MX with two body parameters', function (string $key): void {
    $template = (new TemplateRegistry)->get($key);

    expect($template->key)->toBe($key)
        ->and($template->language)->toBe('es_MX')
        ->and($template->category)->toBe('UTILITY')
        ->and($template->bodyParameterCount)->toBe(2)
        ->and($template->body)->toContain('{{1}}')->toContain('{{2}}')
        ->and($template->buttonLabel)->toBe('Contestar');
})->with(['survey_invite_v1', 'engagement_invite_v1']);

it('keeps the provisional texts of the design', function (): void {
    $registry = new TemplateRegistry;

    expect($registry->get('survey_invite_v1')->body)->toBe('{{1}} le invita a contestar una evaluación confidencial sobre su trabajo. Tiene hasta el {{2}}. El enlace es personal y sirve una sola vez.')
        ->and($registry->get('engagement_invite_v1')->body)->toBe('{{1}} le invita a dar su opinión en una encuesta anónima. Tiene hasta el {{2}}. El enlace es personal.');
});

it('has only UTILITY templates', function (): void {
    foreach ((new TemplateRegistry)->all() as $template) {
        expect($template->category)->toBe('UTILITY');
    }
});

it('points the button at the real survey links', function (string $key, string $routeName): void {
    $base = (new TemplateRegistry)->get($key)->buttonUrlBase;

    expect($base)->toEndWith('{{1}}')
        ->and(str_replace('{{1}}', 'x', $base))->toBe(route($routeName, 'x'));
})->with([
    ['survey_invite_v1', 'survey.show'],
    ['engagement_invite_v1', 'engagement.survey.show'],
]);

it('throws for a template it does not know', function (): void {
    (new TemplateRegistry)->get('nope');
})->throws(InvalidArgumentException::class);
