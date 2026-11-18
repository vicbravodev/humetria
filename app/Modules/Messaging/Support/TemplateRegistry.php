<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use InvalidArgumentException;

/**
 * The templates Humetria sends, in code: Meta approves them, the app does not. Texts are the ones approved in
 * `2026-11-18-whatsapp-templates.md`; variables are named and their examples are the ones the catalog gives.
 */
final class TemplateRegistry
{
    private const string FOOTER = 'Humetria';

    private const array EXAMPLES = [
        'empresa' => 'Industrias Ejemplo',
        'fecha_limite' => 'viernes 20 de noviembre',
        'fecha' => 'lunes 16 de noviembre',
        'minutos' => '15',
    ];

    /** @var array<string, TemplateDefinition>|null */
    private ?array $templates = null;

    public function get(string $key): TemplateDefinition
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown WhatsApp template [{$key}].");
    }

    /** The template of an invitation type (an InvitationReference constant). */
    public function forInvitation(string $invitationType): TemplateDefinition
    {
        return $this->get(match ($invitationType) {
            'campaign_participant' => 'survey_invite_v1',
            'engagement_invitation' => 'engagement_invite_v1',
            default => throw new InvalidArgumentException("No WhatsApp template for invitation type [{$invitationType}]."),
        });
    }

    /** @return array<string, TemplateDefinition> */
    public function all(): array
    {
        $language = config()->string('whatsapp.language');
        $greeting = 'Hola, buen día. Le escribimos de parte de {{empresa}}.';
        $privacy = TemplateButton::fixedUrl('Aviso de privacidad', route('privacy-notice'));
        $stop = TemplateButton::quickReply(TemplateButton::OPT_OUT_LABEL);
        $invite = fn (string $route): array => [TemplateButton::urlWithVariable('Contestar ahora', $this->base($route), 'abc123'), $privacy, $stop];

        return $this->templates ??= $this->indexed([
            $this->template('survey_invite_v1', $language, "{$greeting} Como parte de la NOM-035, tiene pendiente contestar una evaluación sobre su trabajo. Es confidencial y puede hacerlo hasta el {{fecha_limite}}. Su enlace es personal y solo sirve una vez.", 'Su evaluación de la NOM-035', $invite('survey.show')),
            $this->template('engagement_invite_v1', $language, "{$greeting} Queremos saber cómo le va en su trabajo con esta encuesta anónima: nadie verá sus respuestas individuales. Tiene hasta el {{fecha_limite}} para contestarla y su enlace es personal.", 'Encuesta anónima de {{empresa}}', $invite('engagement.survey.show')),
        ]);
    }

    /**
     * @param  list<TemplateButton>  $buttons
     */
    private function template(string $key, string $language, string $body, ?string $header, array $buttons): TemplateDefinition
    {
        $used = [...self::variablesIn($body), ...self::variablesIn($header ?? '')];

        return new TemplateDefinition(
            key: $key,
            language: $language,
            body: $body,
            examples: array_intersect_key(self::EXAMPLES, array_flip($used)),
            header: $header,
            footer: self::FOOTER,
            buttons: $buttons,
        );
    }

    /** @return list<string> */
    private static function variablesIn(string $text): array
    {
        preg_match_all('/\{\{([^{}]*)\}\}/', $text, $matches);

        return $matches[1];
    }

    /**
     * @param  list<TemplateDefinition>  $templates
     * @return array<string, TemplateDefinition>
     */
    private function indexed(array $templates): array
    {
        $byKey = [];

        foreach ($templates as $template) {
            $byKey[$template->key] = $template;
        }

        return $byKey;
    }

    /** The route's URL with `{{1}}` where the token goes (Meta's variable of the button). */
    private function base(string $routeName): string
    {
        return str_replace('PLACEHOLDER', '{{1}}', route($routeName, 'PLACEHOLDER'));
    }
}
