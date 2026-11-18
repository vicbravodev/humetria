<?php

declare(strict_types=1);

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\OutboundMessage;
use App\Modules\Messaging\Data\SendOutcome;
use App\Modules\Messaging\Support\FakeWhatsAppGateway;

mutates(FakeWhatsAppGateway::class);

function messageTo(string $to): OutboundMessage
{
    return new OutboundMessage($to, 'survey_invite_v1', 'es_MX', ['Acme', '12 de mayo'], 'a-token', 'delivery-id');
}

it('answers the scripted outcomes in order and records what it was asked to send', function (): void {
    $gateway = new FakeWhatsAppGateway;
    $gateway->script(SendOutcome::failed(FailureCause::RateLimited), SendOutcome::accepted('wamid.2'));

    $first = $gateway->send(messageTo('+528111111111'));
    $second = $gateway->send(messageTo('+528122222222'));

    expect($first->cause)->toBe(FailureCause::RateLimited)
        ->and($second->providerMessageId)->toBe('wamid.2')
        ->and(array_map(fn (OutboundMessage $message): string => $message->to, $gateway->sent()))->toBe(['+528111111111', '+528122222222']);
});

it('accepts with a fresh id once the script runs out', function (): void {
    $gateway = new FakeWhatsAppGateway;

    $one = $gateway->send(messageTo('+528111111111'));
    $two = $gateway->send(messageTo('+528111111111'));

    expect($one->isAccepted())->toBeTrue()
        ->and($two->isAccepted())->toBeTrue()
        ->and($one->providerMessageId)->not->toBe($two->providerMessageId);
});
