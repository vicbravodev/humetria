<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\SendOutcome;

/**
 * The gateway of the tests: answers the scripted outcomes in order, then accepts with a fresh id, and remembers what it was sent.
 */
final class FakeWhatsAppGateway implements WhatsAppGateway
{
    /** @var list<SendOutcome> */
    private array $outcomes = [];

    /** @var list<OutboundMessage> */
    private array $sent = [];

    /** @var list<array{string, string}> */
    private array $texts = [];

    public function script(SendOutcome ...$outcomes): void
    {
        $this->outcomes = array_values($outcomes);
    }

    public function send(OutboundMessage $message): SendOutcome
    {
        $this->sent[] = $message;

        return array_shift($this->outcomes) ?? SendOutcome::accepted('wamid.fake-'.count($this->sent));
    }

    public function sendText(string $to, string $body): SendOutcome
    {
        $this->texts[] = [$to, $body];

        return array_shift($this->outcomes) ?? SendOutcome::accepted('wamid.fake-text-'.count($this->texts));
    }

    /** @return list<array{string, string}> the phone and the text of each service text sent */
    public function texts(): array
    {
        return $this->texts;
    }

    /** @return list<OutboundMessage> */
    public function sent(): array
    {
        return $this->sent;
    }
}
