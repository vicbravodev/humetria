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

    /** The footer as submitted from 2026-11-26 on; the older templates keep theirs until Victor submits them again. */
    private const string ACCENTED_FOOTER = 'Humetría';

    private const array EXAMPLES = [
        'empresa' => 'Industrias Ejemplo',
        'fecha_limite' => 'viernes 20 de noviembre',
        'fecha' => 'lunes 16 de noviembre',
        'minutos' => '15',
    ];

    /** @var array<string, TemplateDefinition|AuthenticationTemplate>|null */
    private ?array $templates = null;

    public function get(string $key): TemplateDefinition|AuthenticationTemplate
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown WhatsApp template [{$key}].");
    }

    /** The template of an invitation type (an InvitationReference constant). */
    public function forInvitation(string $invitationType): TemplateDefinition
    {
        $template = $this->get(match ($invitationType) {
            'campaign_participant' => 'survey_invite_v1',
            'engagement_invitation' => 'engagement_invite_v1',
            'worker_access_link' => 'worker_invite_v1',
            default => throw new InvalidArgumentException("No WhatsApp template for invitation type [{$invitationType}]."),
        });

        return $template instanceof TemplateDefinition ? $template : throw new InvalidArgumentException("[{$template->key}] is not an invitation template.");
    }

    /** @return array<string, TemplateDefinition|AuthenticationTemplate> */
    public function all(): array
    {
        return $this->templates ??= $this->indexed([...$this->surveys(), ...$this->portalNotices(), ...$this->access()]);
    }

    /** @return list<TemplateDefinition> */
    private function surveys(): array
    {
        $language = config()->string('whatsapp.language');
        $greeting = 'Hola, buen día. Le escribimos de parte de {{empresa}}.';
        $privacy = TemplateButton::fixedUrl('Aviso de privacidad', route('privacy-notice'));
        $stop = TemplateButton::quickReply(TemplateButton::OPT_OUT_LABEL);
        $link = fn (string $text, string $route): TemplateButton => TemplateButton::urlWithVariable($text, $this->base($route), 'abc123');
        $invite = fn (string $route): array => [$link('Contestar ahora', $route), $privacy, $stop];
        $reminder = fn (string $what): string => "Hola, buen día. Le recordamos que su {$what} de {{empresa}} sigue pendiente. Puede contestarla hasta el {{fecha_limite}}. Su enlace sigue siendo personal.";

        return [
            $this->template('survey_invite_v1', $language, "{$greeting} Como parte de la NOM-035, tiene pendiente contestar una evaluación sobre su trabajo. Es confidencial y puede hacerlo hasta el {{fecha_limite}}. Su enlace es personal y solo sirve una vez.", 'Su evaluación de la NOM-035', $invite('survey.show')),
            $this->template('seating_invite_v1', $language, "{$greeting} Como parte de la Ley Silla, queremos saber cómo son los asientos y las pausas en su trabajo. Son unos minutos y puede contestar hasta el {{fecha_limite}}. Su enlace es personal y solo sirve una vez.", 'Encuesta sobre su puesto de trabajo', $invite('survey.show')),
            $this->template('engagement_invite_v1', $language, "{$greeting} Queremos saber cómo le va en su trabajo con esta encuesta anónima: nadie verá sus respuestas individuales. Tiene hasta el {{fecha_limite}} para contestarla y su enlace es personal.", 'Encuesta anónima de clima', $invite('engagement.survey.show')),
            $this->template('survey_reminder_v1', $language, $reminder('evaluación'), null, [$link('Continuar', 'survey.show'), $stop]),
            $this->template('engagement_reminder_v1', $language, $reminder('encuesta anónima'), null, [$link('Continuar', 'engagement.survey.show'), $stop]),
        ];
    }

    /**
     * The notices that open the worker's portal. Their routes carry the section in the middle (`/me/{slug}/telework`), and
     * Meta's URL variable goes at the end: until a route ends in the variable, the button is pending.
     *
     * @return list<TemplateDefinition>
     */
    private function portalNotices(): array
    {
        $language = config()->string('whatsapp.language');
        $stop = TemplateButton::quickReply(TemplateButton::OPT_OUT_LABEL);
        $hello = 'Hola, buen día.';
        $pending = fn (string $text): TemplateButton => TemplateButton::pendingUrl($text, 'No route ends in the variable for this notice (the portal sections have the slug in the middle); worker.home (/me/{slug}) is the nearest one.');
        $notice = fn (string $key, string $body, string $button, bool $optOut = true): TemplateDefinition => $this->template($key, $language, "{$hello} {$body}", null, [$pending($button), ...($optOut ? [$stop] : [])]);

        return [
            $notice('leave_decision_v1', '{{empresa}} ya respondió su solicitud del {{fecha}}. Puede verla en su portal.', 'Ver respuesta'),
            $notice('leave_to_decide_v1', 'Tiene una solicitud de su equipo en {{empresa}} que le toca responder. Puede hacerlo en su portal.', 'Responder'),
            $notice('policy_to_confirm_v1', '{{empresa}} publicó su política de prevención y necesita que confirme que la leyó. Puede leerla y dar su acuse en su página.', 'Leer y confirmar'),
            $notice('engagement_feedback_v1', '{{empresa}} publicó lo que hará a partir de la encuesta de clima. Puede leerlo en su página.', 'Leer'),
            $notice('seating_risks_v1', '{{empresa}} ya evaluó su puesto de trabajo. Puede leer el resultado y confirmar que se lo explicaron en su página.', 'Ver resultado'),
            $notice('seating_questions_v1', '{{empresa}} quiere hacerle dos preguntas sobre su trabajo de pie. Contestar es voluntario y toma un minuto.', 'Contestar'),
            $notice('telework_invite_v1', '{{empresa}} le pide completar su lista de verificación de teletrabajo. Puede hacerlo en su portal.', 'Completar lista'),
            $notice('telework_reminder_v1', 'Tiene pendientes en su portal de teletrabajo de {{empresa}}. Puede revisarlos cuando guste.', 'Ver pendientes'),
            $notice('telework_answered_v1', '{{empresa}} respondió el aviso que usted envió. El detalle solo se ve en su portal.', 'Ver respuesta', false),
            $notice('support_waiting_v1', '{{empresa}} le dejó un aviso en su portal personal. Solo usted puede verlo.', 'Ver aviso', false),
            $notice('attendance_correction_v1', '{{empresa}} respondió su solicitud de corrección de asistencia. Puede verla en su portal.', 'Ver respuesta'),
        ];
    }

    /** @return list<TemplateDefinition|AuthenticationTemplate> */
    private function access(): array
    {
        $language = config()->string('whatsapp.language');
        $pending = fn (string $text, string $what): TemplateButton => TemplateButton::pendingUrl($text, "The {$what} sits after the slug in the route, and Meta's variable goes at the end: a route that ends in it is missing.");

        return [
            // Submitted to Meta on 2026-11-26: the button opens the short route of the one-use link, which ends in the token.
            $this->template('worker_invite_v1', $language, 'Hola, buen día. {{empresa}} le invita a crear su acceso personal al portal. El enlace es personal y solo sirve una vez.', null, [TemplateButton::urlWithVariable('Crear mi acceso', $this->base('worker.link.short'), 'abc123')], self::ACCENTED_FOOTER),
            $this->template('access_link_v1', $language, 'Hola, buen día. Este es el enlace que pidió para entrar a su portal de {{empresa}}. Solo sirve una vez y vence en {{minutos}} minutos.', null, [$pending('Entrar al portal', 'one-use link')]),
            $this->template('key_registered_v1', $language, 'Hola, buen día. Se registró una llave de acceso nueva en su portal de {{empresa}}. Si no fue usted, avísenos.', null, [$pending('No fui yo', 'signed address that revokes the key')]),
            new AuthenticationTemplate('proof_code_v1', $language),
        ];
    }

    /**
     * @param  list<TemplateButton>  $buttons
     */
    private function template(string $key, string $language, string $body, ?string $header, array $buttons, string $footer = self::FOOTER): TemplateDefinition
    {
        $used = [...self::variablesIn($body), ...self::variablesIn($header ?? '')];

        return new TemplateDefinition(
            key: $key,
            language: $language,
            body: $body,
            examples: array_intersect_key(self::EXAMPLES, array_flip($used)),
            header: $header,
            footer: $footer,
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
     * @param  list<TemplateDefinition|AuthenticationTemplate>  $templates
     * @return array<string, TemplateDefinition|AuthenticationTemplate>
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
