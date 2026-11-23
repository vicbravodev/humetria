<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Enums\TemplateButtonType;
use InvalidArgumentException;

/**
 * A template as Meta's API asks for it when it is created (named variables, an example for each). It only builds the array: nothing
 * here talks to Meta or reads a credential. A button without a destination carries `_pending`, which Meta would reject: the template
 * is not sent until it has one.
 */
final class TemplateExport
{
    /** @return array<string, mixed> */
    public function build(TemplateDefinition|AuthenticationTemplate $template): array
    {
        if ($template instanceof AuthenticationTemplate) {
            return [
                'name' => $template->key,
                'language' => $template->language,
                'category' => $template->category->value,
                'components' => [
                    ['type' => 'BODY', 'add_security_recommendation' => $template->addSecurityRecommendation],
                    ['type' => 'BUTTONS', 'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => $template->button()->text]]],
                ],
            ];
        }

        return [
            'name' => $template->key,
            'category' => $template->category->value,
            'language' => $template->language,
            'parameter_format' => 'named',
            'components' => array_values(array_filter([
                $this->header($template),
                $this->body($template),
                $template->footer === null ? null : ['type' => 'FOOTER', 'text' => $template->footer],
                $template->buttons === [] ? null : ['type' => 'BUTTONS', 'buttons' => array_map($this->button(...), $template->buttons)],
            ])),
        ];
    }

    /** @return array<string, mixed>|null */
    private function header(TemplateDefinition $template): ?array
    {
        if ($template->header === null) {
            return null;
        }

        $header = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $template->header];
        $variable = $template->headerVariable();

        return $variable === null ? $header : $header + ['example' => ['header_text_named_params' => [['param_name' => $variable, 'example' => $template->examples[$variable]]]]];
    }

    /** @return array<string, mixed> */
    private function body(TemplateDefinition $template): array
    {
        $params = array_map(fn (string $name): array => ['param_name' => $name, 'example' => $template->examples[$name]], $template->bodyVariables());

        return ['type' => 'BODY', 'text' => $template->body] + ($params === [] ? [] : ['example' => ['body_text_named_params' => $params]]);
    }

    /** @return array<string, mixed> */
    private function button(TemplateButton $button): array
    {
        $json = ['type' => $button->type->value, 'text' => $button->text];

        if ($button->type === TemplateButtonType::Url && $button->url !== null) {
            // Meta takes only https in a button (it came back as an error from the real API); the host is APP_URL.
            if (! str_starts_with($button->url, 'https://')) {
                throw new InvalidArgumentException("Meta needs https in the URL of a button [{$button->url}]: set APP_URL to the public https address.");
            }

            $json['url'] = $button->url;

            if ($button->variable) {
                $json['example'] = [str_replace('{{1}}', (string) $button->example, $button->url)];
            }
        }

        if ($button->type === TemplateButtonType::Phone && $button->phone !== null) {
            $json['phone_number'] = $button->phone;
        }

        return $button->pending === null ? $json : $json + ['_pending' => $button->pending];
    }
}
