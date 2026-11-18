<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\SendOutcome;
use App\Modules\Messaging\Enums\TemplateButtonType;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Sends the template through Meta's Cloud API. The message id travels in `biz_opaque_callback_data`, which Meta hands back in
 * the status webhooks, because a message's state cannot be asked for later.
 *
 * Supuesto por confirmar (Victor/Fase 2): `biz_opaque_callback_data` and the button payload follow Meta's documentation of
 * the Cloud API; the pages checked on 2026-11-18 did not restate the former.
 */
final class CloudApiWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(private readonly PhoneForMeta $phones, private readonly WhatsAppErrorMap $errors, private readonly TemplateRegistry $templates) {}

    public function send(OutboundMessage $message): SendOutcome
    {
        return $this->post($message->to, fn (string $to): array => $this->payload($message, $to));
    }

    public function sendText(string $to, string $body): SendOutcome
    {
        return $this->post($to, fn (string $meta): array => ['messaging_product' => 'whatsapp', 'to' => $meta, 'type' => 'text', 'text' => ['body' => $body]]);
    }

    /** @param Closure(string): array<string, mixed> $payload the body to send, given the phone as Meta writes it */
    private function post(string $phone, Closure $payload): SendOutcome
    {
        $to = $this->phones->toMeta($phone);

        // Not a Mexican mobile: there is no WhatsApp account to reach, and nothing leaves.
        if ($to === null) {
            return SendOutcome::failed(FailureCause::NoWhatsappAccount);
        }

        $url = sprintf('%s/%s/%s/messages', config()->string('whatsapp.base_url'), config()->string('whatsapp.api_version'), config()->string('whatsapp.phone_number_id'));

        try {
            $response = Http::timeout(config()->integer('whatsapp.timeout_seconds'))
                ->withToken(config()->string('whatsapp.access_token'))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload($to));
        } catch (ConnectionException) {
            // It is not known whether the message left.
            return SendOutcome::failed(FailureCause::TimedOut);
        }

        if ($response->successful()) {
            $id = $response->json('messages.0.id');

            return is_string($id) && $id !== '' ? SendOutcome::accepted($id) : SendOutcome::failed(FailureCause::ServerError);
        }

        $code = $response->json('error.code');

        return SendOutcome::failed($this->errors->fromResponse($response->status(), is_int($code) ? $code : null));
    }

    /** @return array<string, mixed> */
    private function payload(OutboundMessage $message, string $to): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'biz_opaque_callback_data' => $message->callbackData,
            'template' => [
                'name' => $message->template,
                'language' => ['code' => $message->language],
                'components' => $this->components($this->templates->get($message->template), $message),
            ],
        ];
    }

    /**
     * The components the definition declares: the header and the body with their named parameters, and the buttons that carry a
     * variable (at their own index). The token travels only in the button.
     *
     * @return list<array<string, mixed>>
     */
    private function components(TemplateDefinition|AuthenticationTemplate $template, OutboundMessage $message): array
    {
        // The code goes in the body and in the button that copies it; Meta's library text takes no named variables.
        if ($template instanceof AuthenticationTemplate) {
            $code = ['type' => 'text', 'text' => $message->bodyParameters[0] ?? throw new InvalidArgumentException('An authentication message carries its code.')];

            return [['type' => 'body', 'parameters' => [$code]], ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [$code]]];
        }

        $named = $template->namedParameters($message->bodyParameters);
        $parameter = fn (string $name): array => ['type' => 'text', 'parameter_name' => $name, 'text' => $named[$name]];
        $components = [];

        if ($template->headerVariable() !== null) {
            $components[] = ['type' => 'header', 'parameters' => [$parameter($template->headerVariable())]];
        }

        if ($named !== []) {
            $components[] = ['type' => 'body', 'parameters' => array_map($parameter, array_keys($named))];
        }

        foreach ($template->buttons as $index => $button) {
            if ($button->type === TemplateButtonType::Url && $button->variable) {
                $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => (string) $index, 'parameters' => [['type' => 'text', 'text' => $message->buttonSuffix]]];
            }
        }

        return $components;
    }
}
