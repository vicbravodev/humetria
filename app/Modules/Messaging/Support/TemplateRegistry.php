<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use InvalidArgumentException;

/**
 * The templates Humetria sends, in code: Meta approves them, the app does not. All UTILITY, es_MX, two body parameters ({{1}} the
 * company, {{2}} the last day) and a URL button whose variable is the survey link token.
 *
 * Supuesto por confirmar (Victor, antes de la Fase 2): the texts below are provisional.
 */
final class TemplateRegistry
{
    /** @var array<string, TemplateDefinition>|null */
    private ?array $templates = null;

    public function get(string $key): TemplateDefinition
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown WhatsApp template [{$key}].");
    }

    /** @return array<string, TemplateDefinition> */
    public function all(): array
    {
        $language = config()->string('whatsapp.language');

        return $this->templates ??= [
            'survey_invite_v1' => new TemplateDefinition(
                key: 'survey_invite_v1',
                language: $language,
                category: 'UTILITY',
                body: '{{1}} le invita a contestar una evaluación confidencial sobre su trabajo. Tiene hasta el {{2}}. El enlace es personal y sirve una sola vez.',
                bodyParameterCount: 2,
                buttonLabel: 'Contestar',
                buttonUrlBase: $this->base('survey.show'),
            ),
            'engagement_invite_v1' => new TemplateDefinition(
                key: 'engagement_invite_v1',
                language: $language,
                category: 'UTILITY',
                body: '{{1}} le invita a dar su opinión en una encuesta anónima. Tiene hasta el {{2}}. El enlace es personal.',
                bodyParameterCount: 2,
                buttonLabel: 'Contestar',
                buttonUrlBase: $this->base('engagement.survey.show'),
            ),
        ];
    }

    /** The route's URL with `{{1}}` where the token goes (Meta's variable of the button). */
    private function base(string $routeName): string
    {
        return str_replace('PLACEHOLDER', '{{1}}', route($routeName, 'PLACEHOLDER'));
    }
}
